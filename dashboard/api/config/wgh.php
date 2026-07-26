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

    /*
     * The numbers the decision engine judges by.
     *
     * profit_per_order_usd is the single most important figure in this file:
     * it is the line between a keyword that earns and one that bleeds. It
     * starts at the spec's estimate and MUST be replaced with the real margin
     * once dealer costs are entered, at which point every verdict sharpens.
     * Until then verdicts are directionally right, not exact, and the engine
     * says so in its evidence.
     */
    'decisions' => [
        'profit_per_order_usd' => (float) env('WGH_PROFIT_PER_ORDER_USD', 8.75),

        // Nothing is killed before BOTH are true. Time alone kills a keyword
        // that has barely spent; spend alone kills one that has had two days.
        'min_days_to_judge' => (int) env('WGH_MIN_DAYS', 14),
        'min_clicks_to_judge' => (int) env('WGH_MIN_CLICKS', 100),

        // A keyword drawing taps but no sale is a landing page or price
        // problem, not a keyword problem. Killing it is the expensive mistake
        // this threshold exists to prevent.
        'fix_min_taps' => (int) env('WGH_FIX_MIN_TAPS', 3),
    ],

    /*
     * The offline conversion loop. These drive the milestone ladder, and they
     * come from how Smart Bidding actually behaves: it needs roughly 30
     * conversions in 30 days to stabilise, and it wants uploads at least
     * weekly, because consistency matters more than volume early on.
     */
    'loop' => [
        'smart_bidding_floor' => 30,
        'export_overdue_days' => 7,
        'match_rate_strong' => 0.60,
        'match_rate_thin' => 0.40,
    ],

    'display_timezone' => 'Africa/Accra',

];
