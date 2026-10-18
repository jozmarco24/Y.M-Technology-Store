<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Modo de Facturación Electrónica (Nubefact)
    |--------------------------------------------------------------------------
    |
    | Opciones:
    | - 'simulado' : Emisor simulado sin conexión externa (desarrollo y tests).
    | - 'demo'     : Entorno demo de Nubefact con conexión real a sandbox.
    | - 'produccion' : Entorno de producción en vivo con SUNAT.
    |
    */
    'modo' => env('NUBEFACT_MODO', 'simulado'),
    'ruta' => env('NUBEFACT_RUTA', ''),
    'token' => env('NUBEFACT_TOKEN', ''),
    'timeout' => (int) env('NUBEFACT_TIMEOUT', 15),
    'retries' => (int) env('NUBEFACT_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Credenciales API Nubefact
    |--------------------------------------------------------------------------
    */
    'nubefact' => [
        'ruta' => env('NUBEFACT_RUTA', ''),
        'token' => env('NUBEFACT_TOKEN', ''),
        'timeout' => (int) env('NUBEFACT_TIMEOUT', 15),
        'retries' => (int) env('NUBEFACT_RETRIES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Colas y Reintentos
    |--------------------------------------------------------------------------
    */
    'cola' => 'facturacion',
    'max_intentos' => 5,
    'backoff' => [60, 300, 900, 3600, 10800], // 1m, 5m, 15m, 1h, 3h
];
