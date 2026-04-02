<?php

/**
 * Format date/time safely from any parseable date string.
 */
function fes_format_date_safe($value, string $format, string $fallback = '—'): string
{
    if ($value === null) {
        return $fallback;
    }
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $fallback;
    }
    return date($format, $ts);
}

