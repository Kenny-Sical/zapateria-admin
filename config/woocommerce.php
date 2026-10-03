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

    /*
    |--------------------------------------------------------------------------
    | WordPress Uploads Directory
    |--------------------------------------------------------------------------
    |
    | Ruta física absoluta al directorio wp-content/uploads de WordPress.
    |
    */
    'wp_uploads_path' => env('WORDPRESS_UPLOADS_PATH', base_path('../zapateria-wordpress/wp-content/uploads')),

    /*
    |--------------------------------------------------------------------------
    | Inventory Cache TTL
    |--------------------------------------------------------------------------
    |
    | Tiempo de vida (en segundos) para el caché del listado de inventario.
    | Un valor de 0 desactiva el caché.
    |
    */
    'inventory_cache_ttl' => (int) env('WOOCOMMERCE_INVENTORY_CACHE_TTL', 60),
];
