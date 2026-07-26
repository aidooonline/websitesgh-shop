<?php

return [

    'name' => env('APP_NAME', 'WGH Intelligence'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
     * Storage is UTC everywhere, always. Display converts to Africa/Accra in
     * the React layer. The single most common reporting bug in this class of
     * system is a local timestamp landing in a UTC column and quietly shifting
     * a day boundary, which moves revenue between periods.
     */
    'timezone' => 'UTC',

    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_GB',

    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    ],

];
