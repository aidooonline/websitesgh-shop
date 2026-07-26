<?php

return [

    /*
     * The shop connector. The secret is the one shown at
     * Tools > WGH Dashboard Access on the shop, or the WGHS_DASHBOARD_SECRET
     * constant in the shop's wp-config.php, which is the better home for it.
     */
    'shop' => [
        'base_url' => rtrim((string) env('WGH_SHOP_URL', 'https://shop.websitesgh.com'), '/'),
        'export_path' => '/wp-json/wghs/v1/export',
        'secret' => (string) env('WGH_SHOP_SECRET', ''),

        // Rows per page, per stream. The shop caps this at 500.
        'page_size' => (int) env('WGH_SYNC_PAGE_SIZE', 200),

        // Hard stop on pages per run, so a cursor bug can never turn into an
        // unbounded loop against the shop.
        'max_pages' => (int) env('WGH_SYNC_MAX_PAGES', 200),

        'timeout' => (int) env('WGH_SYNC_TIMEOUT', 30),
        'retries' => (int) env('WGH_SYNC_RETRIES', 3),

        /*
         * Seconds of overlap re-requested on every run. The cursor is already
         * inclusive; this widens it further so a shop clock that drifts a few
         * seconds behind the dashboard cannot open a gap. Re-reading a row is
         * free because every write is a content-hash guarded upsert.
         */
        'cursor_overlap' => (int) env('WGH_SYNC_OVERLAP', 120),
    ],

    /*
     * Ad spend arrives in USD, the shop sells in GHS. Nothing in this system
     * ever compares the two without going through a dated fx_rates row, so a
     * historic month keeps the rate that was true that month.
     */
    'currency' => [
        'sales' => 'GHS',
        'spend' => 'USD',
    ],

    'display_timezone' => 'Africa/Accra',

];
