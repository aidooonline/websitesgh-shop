<?php

namespace App\Services\Costs;

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
    ];

    /**
     * Write the sheet, seeded with everything the system has seen sold.
     *
     * @return array{path: string, rows: int, already_costed: int}
     */
    public function export(string $dir): array
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = rtrim($dir, '/').'/wgh-product-costs.csv';

        // Products that have actually been sold, most-sold first, so the ones
        // worth costing first are at the top of the file rather than buried
        // alphabetically halfway down.
        $sold = OrderItem::query()
            ->selectRaw('woo_product_id, MAX(product_name) AS product_name,
                SUM(qty) AS units, AVG(unit_price_ghs) AS avg_price')
            ->groupBy('woo_product_id')
            ->orderByDesc('units')
            ->get();

        $existing = ProductCost::all()->keyBy('woo_product_id');

        $fh = fopen($path, 'w');
        fputcsv($fh, self::HEADER);

        $costed = 0;

        foreach ($sold as $s) {
            $known = $existing->get($s->woo_product_id);

            if ($known && $known->isComplete()) {
                $costed++;
            }

            fputcsv($fh, [
                $s->woo_product_id,
                $known->product_name ?? $s->product_name,
                // The observed selling price is pre-filled so the only columns
                // needing a human are the two the supplier actually tells you.
                $known?->sell_price_ghs ?? number_format((float) $s->avg_price, 2, '.', ''),
                $known?->dealer_cost_ghs ?? '',
                $known?->delivery_cost_ghs ?? '',
                $known?->supplier ?? '',
                $known && ! $known->is_estimate ? 'yes' : '',
            ]);
        }

        // Anything already costed but not yet sold stays in the file, or a
        // re-export would quietly drop work already done.
        foreach ($existing as $known) {
            if ($sold->firstWhere('woo_product_id', $known->woo_product_id)) {
                continue;
            }

            fputcsv($fh, [
                $known->woo_product_id,
                $known->product_name,
                $known->sell_price_ghs,
                $known->dealer_cost_ghs,
                $known->delivery_cost_ghs,
                $known->supplier,
                $known->is_estimate ? '' : 'yes',
            ]);
        }

        fclose($fh);

        return [
            'path' => $path,
            'rows' => $sold->count() + max(0, $existing->count() - $sold->count()),
            'already_costed' => $costed,
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
