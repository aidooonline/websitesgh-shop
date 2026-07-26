<?php

namespace App\Services\Costs;

use App\Models\AttributionEvent;
use App\Models\OrderItem;
use App\Models\ProductCost;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The dealer cost sheet: out as a CSV, back in filled.
 *
 * WHY A CSV AND NOT A SCREEN
 * The screen is sprint 4 and it does not exist. Waiting for it would leave the
 * single most important number in the system unmeasurable for weeks, and it is
 * the number that gates every KEEP and every KILL. A spreadsheet works today,
 * works on a phone, and can be filled in while sitting in front of a supplier
 * rather than remembered and typed up later.
 *
 * It also survives the screen being built. Entering forty products one form at
 * a time is worse than one paste, and supplier prices arrive in batches.
 *
 * WHAT IT REFUSES TO DO
 * A blank cost is left blank. It is never read as zero, because a zero cost
 * makes a product look like pure profit and every verdict that touches it comes
 * out wrong in the flattering direction. Missing costs are reported as missing
 * and the affected margins stay null.
 */
class CostSheet
{
    private const HEADER = [
        'product_id',
        'product_name',
        'sell_price_ghs',
        'dealer_cost_ghs',
        'delivery_cost_ghs',
        'supplier',
        'confirmed',
        // Not read back on import. It exists so the person filling this in on a
        // phone knows which rows out of fifty are worth the supplier call, and
        // does not start at row one and give up at row twelve.
        'why_this_matters',
    ];

    /**
     * Write the sheet, seeded with everything the system has seen sold.
     *
     * @return array{path: string, rows: int, already_costed: int}
     */
    /**
     * Every product the system knows about, ranked by how much a cost on it
     * would change a decision.
     *
     * @return list<array<string, mixed>>
     */
    private function ranked(): array
    {
        // Units sold, per product.
        $sold = OrderItem::query()
            ->selectRaw('woo_product_id, MAX(product_name) AS product_name,
                SUM(qty) AS units, AVG(unit_price_ghs) AS avg_price')
            ->groupBy('woo_product_id')
            ->get()
            ->keyBy('woo_product_id');

        /*
         * Ad interest, per product: taps and baskets that never became a sale.
         *
         * These are the rows the first version missed entirely, and they are
         * arguably the most urgent of all. A product pulling WhatsApp taps and
         * closing none of them is either priced wrong or sold at a loss, and
         * there is no way to tell which without its dealer cost. Ranking them
         * below "has sold" but above the untouched catalogue puts the open
         * questions where they get answered.
         */
        $interest = AttributionEvent::query()
            ->where('product_id', '>', 0)
            // Crawlers walking add-to-cart links are not interest, and the
            // owner testing his own shop is not either. Counting them ranked
            // products by how thoroughly a bot had crawled them.
            ->where('visitor', 'human')
            /*
             * SUM(CASE ...), not COUNT(*) FILTER (...).
             *
             * FILTER is Postgres only. Development runs on Postgres and the
             * live server runs on MariaDB, so the whole suite went green on
             * syntax the production database rejects outright. Every raw
             * expression in this codebase has to be portable across both.
             */
            ->selectRaw("product_id,
                SUM(CASE WHEN status = 'cart' THEN 1 ELSE 0 END) AS baskets,
                SUM(CASE WHEN status <> 'cart' THEN 1 ELSE 0 END) AS taps")
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $existing = ProductCost::all()->keyBy('woo_product_id');

        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];

        $touch = function (int $id, ?string $name, ?string $price) use (&$rows, $existing) {
            if (isset($rows[$id])) {
                return;
            }

            $known = $existing->get($id);

            $rows[$id] = [
                'id' => $id,
                'name' => $known->product_name ?? $name ?? ('#'.$id),
                'price' => $known?->sell_price_ghs ?? $price,
                'dealer' => $known?->dealer_cost_ghs ?? '',
                'delivery' => $known?->delivery_cost_ghs ?? '',
                'supplier' => $known?->supplier ?? '',
                'confirmed' => $known && ! $known->is_estimate ? 'yes' : '',
                'units' => 0,
                'events' => 0,
                'taps' => 0,
                'baskets' => 0,
            ];
        };

        foreach ($sold as $s) {
            $id = (int) $s->woo_product_id;
            $touch($id, $s->product_name, number_format((float) $s->avg_price, 2, '.', ''));
            $rows[$id]['units'] = (int) $s->units;
        }

        foreach ($interest as $productId => $row) {
            $id = (int) $productId;
            $touch($id, null, null);
            // A WhatsApp message is far stronger evidence of intent than a
            // basket, so the two are kept apart rather than added together.
            $rows[$id]['taps'] = (int) $row->taps;
            $rows[$id]['baskets'] = (int) $row->baskets;
            $rows[$id]['events'] = (int) $row->taps + (int) $row->baskets;
        }

        // The catalogue, so a product can be costed before it ever sells.
        foreach ($existing as $known) {
            $touch((int) $known->woo_product_id, $known->product_name, $known->sell_price_ghs);
        }

        /*
         * Sold first, by units. Then products drawing ad interest but no sale.
         * Then the rest of the catalogue. Sorting alphabetically would bury the
         * three products carrying the business under forty that are not.
         */
        $rows = array_values($rows);
        usort($rows, function ($a, $b) {
            // Sold, then messaged about, then merely basketed, then the rest.
            $rank = fn ($r) => $r['units'] > 0 ? 0 : ($r['taps'] > 0 ? 1 : ($r['baskets'] > 0 ? 2 : 3));

            return $rank($a) <=> $rank($b)
                ?: $b['units'] <=> $a['units']
                ?: $b['taps'] <=> $a['taps']
                ?: $b['baskets'] <=> $a['baskets']
                ?: strcmp((string) $a['name'], (string) $b['name']);
        });

        return $rows;
    }

    /**
     * Product ids in the order they are worth costing, most urgent first.
     *
     * Shared with the interactive entry command, so the spreadsheet and the
     * terminal never disagree about which product matters most. Two rankings
     * for one question is how a person ends up costing the wrong ten.
     *
     * @return list<int>
     */
    public function priorityOrder(): array
    {
        return array_map(fn ($r) => (int) $r['id'], $this->ranked());
    }

    public function export(string $dir): array
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = rtrim($dir, '/').'/wgh-product-costs.csv';
        $rows = $this->ranked();

        $fh = fopen($path, 'w');
        fputcsv($fh, self::HEADER);

        $costed = 0;
        $needed = 0;

        foreach ($rows as $r) {
            $hasCost = $r['dealer'] !== '' && $r['dealer'] !== null;

            if ($hasCost) {
                $costed++;
            }

            if ($r['units'] > 0) {
                $why = sprintf('SOLD %d unit%s. Cost this one first.', $r['units'], $r['units'] === 1 ? '' : 's');
            } elseif ($r['taps'] > 0) {
                $why = sprintf(
                    '%d WhatsApp message%s, no sale yet. Cannot tell a bad price from a bad product without this.',
                    $r['taps'], $r['taps'] === 1 ? '' : 's'
                );
            } elseif ($r['baskets'] > 0) {
                $why = sprintf(
                    'Put in a basket %d time%s, never messaged about. Worth knowing what it earns before promoting it.',
                    $r['baskets'], $r['baskets'] === 1 ? '' : 's'
                );
            } else {
                $why = 'Not sold or advertised yet. Fill in when you get to it.';
            }

            if (! $hasCost && ($r['units'] > 0 || $r['events'] > 0)) {
                $needed++;
            }

            fputcsv($fh, [
                $r['id'],
                $r['name'],
                // The observed selling price is pre-filled so the only columns
                // needing a human are the two the supplier actually tells you.
                $r['price'],
                $r['dealer'],
                $r['delivery'],
                $r['supplier'],
                $r['confirmed'],
                $why,
            ]);
        }

        fclose($fh);

        return [
            'path' => $path,
            'rows' => count($rows),
            'already_costed' => $costed,
            'sold' => count(array_filter($rows, fn ($r) => $r['units'] > 0)),
            'messaged_only' => count(array_filter($rows, fn ($r) => $r['units'] === 0 && $r['taps'] > 0)),
            'basketed_only' => count(array_filter($rows, fn ($r) => $r['units'] === 0 && $r['taps'] === 0 && $r['baskets'] > 0)),
            'needed_now' => $needed,
        ];
    }

    /**
     * Read a filled sheet back.
     *
     * @return array{saved: int, complete: int, blank: int, confirmed: int, problems: list<string>}
     */
    public function import(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read {$path}");
        }

        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = array_values(array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", $raw)), fn ($l) => trim($l) !== ''));

        if (! $lines) {
            throw new RuntimeException('That file is empty.');
        }

        $header = array_map(fn ($c) => mb_strtolower(trim($c, " \t\"'")), str_getcsv($lines[0]));

        if (! in_array('product_id', $header, true) || ! in_array('dealer_cost_ghs', $header, true)) {
            throw new RuntimeException(
                'That does not look like the cost sheet. It needs at least a product_id and a '
                .'dealer_cost_ghs column. Re-export a fresh one with: php artisan wgh:costs --export'
            );
        }

        $idx = array_flip($header);
        $now = CarbonImmutable::now('UTC');

        $saved = 0;
        $complete = 0;
        $blank = 0;
        $confirmed = 0;
        $problems = [];

        foreach (array_slice($lines, 1) as $n => $line) {
            $cells = str_getcsv($line);
            $get = function (string $col) use ($cells, $idx) {
                $i = $idx[$col] ?? null;

                return $i !== null ? trim((string) ($cells[$i] ?? '')) : '';
            };

            $id = (int) $get('product_id');

            if ($id <= 0) {
                continue;
            }

            $dealer = $this->money($get('dealer_cost_ghs'));
            $delivery = $this->money($get('delivery_cost_ghs'));
            $price = $this->money($get('sell_price_ghs'));

            if ($dealer === null) {
                $blank++;
            }

            // A dealer cost at or above the selling price is almost always a
            // typo, and silently accepting it would turn a healthy product into
            // a KILL verdict on a keystroke.
            if ($dealer !== null && $price !== null && $dealer >= $price) {
                $problems[] = sprintf(
                    'Row %d, product %d: dealer cost %s is not below the selling price %s. Selling at a loss, or a typo?',
                    $n + 2, $id, number_format($dealer, 2), number_format($price, 2)
                );
            }

            $isConfirmed = in_array(mb_strtolower($get('confirmed')), ['yes', 'y', 'true', '1'], true);

            ProductCost::updateOrCreate(
                ['woo_product_id' => $id],
                [
                    'product_name' => mb_substr($get('product_name') ?: ('#'.$id), 0, 191),
                    'sell_price_ghs' => $price,
                    'dealer_cost_ghs' => $dealer,
                    'delivery_cost_ghs' => $delivery,
                    'supplier' => mb_substr($get('supplier'), 0, 120) ?: null,
                    'is_estimate' => ! $isConfirmed,
                    'confirmed_at' => $isConfirmed ? $now : null,
                    'updated_at' => $now,
                ]
            );

            $saved++;

            if ($dealer !== null && $price !== null) {
                $complete++;
            }
            if ($isConfirmed) {
                $confirmed++;
            }
        }

        return [
            'saved' => $saved,
            'complete' => $complete,
            'blank' => $blank,
            'confirmed' => $confirmed,
            'problems' => $problems,
        ];
    }

    private function money(string $raw): ?float
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '-') {
            return null;   // Blank means unknown. Never zero.
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw)) ?? '';

        return $clean === '' ? null : round((float) $clean, 2);
    }
}
