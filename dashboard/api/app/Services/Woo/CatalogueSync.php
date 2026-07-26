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
 * WHAT IT WILL NEVER DO
 * It never touches a dealer cost, a delivery cost, a supplier or a confirmation.
 * Those are the owner's, entered by hand, sometimes after a phone call to a
 * supplier, and an automated pull that overwrote them would destroy work that
 * cannot be regenerated. It fills the shelf price and the name, and nothing else
 * on a row that already exists.
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
     * @return array{seen:int, created:int, price_updated:int, untouched:int, already_costed:int}
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

                $known = ProductCost::where('woo_product_id', $id)->first();

                if (! $known) {
                    ProductCost::create([
                        'woo_product_id' => $id,
                        'product_name' => $name,
                        'sell_price_ghs' => $price,
                        // Blank, not zero. A zero dealer cost makes a product
                        // look like pure profit and bends every verdict that
                        // touches it in the flattering direction.
                        'dealer_cost_ghs' => null,
                        'delivery_cost_ghs' => null,
                        'is_estimate' => true,
                        'updated_at' => $now,
                    ]);

                    $created++;

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
