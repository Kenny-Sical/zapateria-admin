<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WooCommerce Store URL
    |--------------------------------------------------------------------------
    |
    | La URL base de la tienda de WordPress (ejemplo: http://localhost:8080).
    |
    */
    'store_url' => env('WOOCOMMERCE_STORE_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | WooCommerce REST API Credentials
    |--------------------------------------------------------------------------
    |
    | Las claves de API generadas desde WooCommerce > Ajustes > Avanzado > REST API.
    | Deben tener permisos de Lectura/Escritura (Read/Write).
    |
    */
    'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY', ''),
    'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | API Version & Options
    |--------------------------------------------------------------------------
    |
    | Versión de la API de WooCommerce y opciones de conexión HTTP.
    |
    */
    'version' => env('WOOCOMMERCE_VERSION', 'wc/v3'),
    'verify_ssl' => env('WOOCOMMERCE_VERIFY_SSL', false),
    'timeout' => env('WOOCOMMERCE_TIMEOUT', 30),
];
