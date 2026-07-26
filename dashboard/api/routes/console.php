<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('wgh:about', function () {
    $this->info('WGH Intelligence API. Shop: '.config('wgh.shop.base_url'));
    $this->line('Sprint 1: foundation and the WooCommerce connector.');
})->purpose('Show what this application is');
