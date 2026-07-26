<?php

namespace App\Services\Woo;

use App\Models\ProductCost;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Pulls the shop catalogue so every product can hold a dealer cost.
 *
 * WHY THIS EXISTS
 * The cost sheet was seeded from products that had already sold, which on a
 * young shop meant three rows out of fifty. That is backwards twice over. The
 * products worth costing FIRST are the ones ad money is being spent on, and a
 * product cannot be sold profitably before anybody knows what it costs. Waiting
 * for a sale to learn the margin means learning it after the money is spent.
 *
 * WHERE THE COST NOW LIVES
 * Dealer costs are entered at WooCommerce > Product Costs and stored as product
 * meta, next to the price they are measured against. They arrive here with the
 * catalogue. That is deliberate: one place to change a cost rather than two
 * that can disagree, entry happens where the owner already works instead of in
 * a terminal he has to be talked through, and a cost survives this database
 * being rebuilt from scratch.
 *
 * The shop is therefore the source of truth for any cost it HAS. It is not the
 * source of truth for a cost it does not have: a blank on the shop leaves
 * whatever is here alone, so a cost typed with wgh:costs --quick before the
 * screen existed is not wiped out by the first sync that follows.
 *
 * THE SHELF PRICE IS REFRESHED, DELIBERATELY
 * A price change moves the margin without anybody touching the cost, and a
 * margin measured against last month's price is wrong in whichever direction
 * the price moved. So the sell price follows the shop.
 */
class CatalogueSync
{
    private int $pageSize;

    private int $maxPages;

    public function __construct(private readonly SignedClient $client)
    {
        $this->pageSize = max(1, min(500, (int) config('wgh.shop.page_size', 200)));
        $this->maxPages = max(1, (int) config('wgh.shop.max_pages', 200));
    }

    /**
     * @return array{seen:int, created:int, price_updated:int, untouched:int, costs_pulled:int, already_costed:int}
     */
    public function run(): array
    {
        $now = CarbonImmutable::now('UTC');
        $offset = 0;
        $pages = 0;

        $seen = 0;
        $created = 0;
        $priceUpdated = 0;
        $untouched = 0;
        $costsPulled = 0;

        do {
            $payload = $this->client->fetch([
                'streams' => 'products',
                'limit' => $this->pageSize,
                'product_offset' => $offset,
            ]);

            $stream = $payload['products'] ?? null;

            if (! is_array($stream)) {
                throw new RuntimeException(
                    'The shop did not return a products stream. It is running an older copy of the theme: '
                    .'pull the latest and try again.'
                );
            }

            foreach ($stream['rows'] ?? [] as $row) {
                $id = (int) ($row['product_id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $seen++;

                $name = mb_substr(trim((string) ($row['name'] ?? '')) ?: ('#'.$id), 0, 191);
                $price = $this->money($row['price'] ?? null);

                // Blank on the shop means not entered. It must never arrive
                // here as zero: a zero dealer cost makes a product look like
                // pure profit and bends every verdict that touches it.
                $dealer = $this->money($row['dealer_cost'] ?? null);
                $delivery = $this->money($row['delivery_cost'] ?? null);
                $supplier = trim((string) ($row['supplier'] ?? '')) ?: null;
                $quoted = ! empty($row['cost_quoted']);

                $known = ProductCost::where('woo_product_id', $id)->first();

                if (! $known) {
                    ProductCost::create([
                        'woo_product_id' => $id,
                        'product_name' => $name,
                        'sell_price_ghs' => $price,
                        'dealer_cost_ghs' => $dealer,
                        'delivery_cost_ghs' => $delivery,
                        'supplier' => $supplier ? mb_substr($supplier, 0, 120) : null,
                        'is_estimate' => ! $quoted,
                        'confirmed_at' => $quoted ? $now : null,
                        'updated_at' => $now,
                    ]);

                    $created++;

                    if ($dealer !== null) {
                        $costsPulled++;
                    }

                    continue;
                }

                $changed = false;

                if ($price !== null && (string) $known->sell_price_ghs !== (string) $price) {
                    $known->sell_price_ghs = $price;
                    $changed = true;
                }

                if ($known->product_name !== $name) {
                    $known->product_name = $name;
                    $changed = true;
                }

                /*
                 * A cost on the shop wins, because that is where it is entered
                 * now. A BLANK on the shop does not: it leaves whatever is here
                 * alone, so a cost typed with wgh:costs --quick before this
                 * screen existed is not wiped out by the next sync.
                 */
                if ($dealer !== null) {
                    if ((string) $known->dealer_cost_ghs !== (string) $dealer) {
                        $known->dealer_cost_ghs = $dealer;
                        $changed = true;
                    }

                    if ((string) $known->delivery_cost_ghs !== (string) $delivery) {
                        $known->delivery_cost_ghs = $delivery;
                        $changed = true;
                    }

                    if ($supplier !== null && $known->supplier !== $supplier) {
                        $known->supplier = mb_substr($supplier, 0, 120);
                        $changed = true;
                    }

                    if ($known->is_estimate === $quoted) {
                        $known->is_estimate = ! $quoted;
                        $known->confirmed_at = $quoted ? $now : null;
                        $changed = true;
                    }

                    $costsPulled++;
                }

                if ($changed) {
                    $known->updated_at = $now;
                    $known->save();
                    $priceUpdated++;
                } else {
                    $untouched++;
                }
            }

            $offset = (int) ($stream['next_offset'] ?? ($offset + $this->pageSize));
            $pages++;
        } while (! empty($stream['has_more']) && $pages < $this->maxPages);

        return [
            'seen' => $seen,
            'created' => $created,
            'price_updated' => $priceUpdated,
            'untouched' => $untouched,
            'costs_pulled' => $costsPulled,
            'already_costed' => ProductCost::whereNotNull('dealer_cost_ghs')->count(),
        ];
    }

    private function money(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $raw)) ?? '';

        return $clean === '' ? null : number_format((float) $clean, 2, '.', '');
    }
}
