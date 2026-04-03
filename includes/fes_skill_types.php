<?php

/**
 * Canonical operator skill types — values match bookings.service_type (see Pages/booking.php).
 */
function fes_operator_skill_types(): array
{
    return [
        'land_prep'  => 'Land preparation',
        'planting'   => 'Planting',
        'harvesting' => 'Harvesting',
        'irrigation' => 'Irrigation',
        'other'      => 'Other service',
    ];
}

function fes_operator_skill_type_keys(): array
{
    return array_keys(fes_operator_skill_types());
}

function fes_is_operator_skill_type(string $value): bool
{
    return in_array($value, fes_operator_skill_type_keys(), true);
}

/** Human label for stored skill_name (known key or legacy free text). */
function fes_operator_skill_type_label(string $stored): string
{
    $types = fes_operator_skill_types();
    if (isset($types[$stored])) {
        return $types[$stored];
    }

    return ucwords(str_replace('_', ' ', $stored));
}
