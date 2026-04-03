<?php

// Centralized rate constants for booking cost calculation.

if (!defined('FES_RATE_TRANSPORT_PER_KM')) {
    define('FES_RATE_TRANSPORT_PER_KM', 5000);
}

if (!defined('FES_RATE_OPERATOR_PER_HOUR')) {
    define('FES_RATE_OPERATOR_PER_HOUR', 6000);
}

if (!defined('FES_RATE_BASE_FEE')) {
    define('FES_RATE_BASE_FEE', 15000);
}

if (!defined('FES_EQUIPMENT_RATES')) {
    // Values are MK amounts used as defaults/fallbacks.
    // Categories are matched via substring checks in Pages/booking-confirmation.php.
    define('FES_EQUIPMENT_RATES', [
        'tractor' => [
            'hourly' => 25000,
            'areas' => 15000,
            'daily' => 180000,
        ],
        'plow' => [
            'hourly' => 15000,
            'areas' => 8000,
            'daily' => 100000,
        ],
        'harvester' => [
            'hourly' => 35000,
            'areas' => 20000,
            'daily' => 250000,
        ],
        'irrigation' => [
            'hourly' => 20000,
            'areas' => 12000,
            'daily' => 140000,
        ],
        'default' => [
            'hourly' => 18000,
            'areas' => 10000,
            'daily' => 120000,
        ],
    ]);
}

/**
 * @return array{
 *   transport_per_km:int,
 *   operator_per_hour:int,
 *   base_fee:int,
 *   equipment: array<string, array{hourly:int, areas:int, daily:int}>
 * }
 */
function fes_get_rates(): array
{
    return [
        'transport_per_km' => FES_RATE_TRANSPORT_PER_KM,
        'operator_per_hour' => FES_RATE_OPERATOR_PER_HOUR,
        'base_fee' => FES_RATE_BASE_FEE,
        'equipment' => FES_EQUIPMENT_RATES,
    ];
}

