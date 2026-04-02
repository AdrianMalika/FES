<?php

/**
 * Stripe success redirect — verify Checkout Session server-side before marking paid.
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/stripe_checkout.php';

if (!fes_stripe_configured()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Payments not configured';
    exit;
}

$sessionId = trim((string)($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing session_id';
    exit;
}

$base = rtrim(FES_PUBLIC_BASE_URL, '/');

try {
    $conn = getDBConnection();
    $result = fes_stripe_finalize_payment($conn, $sessionId);
    $conn->close();
} catch (Throwable $e) {
    error_log('pay_callback: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server error';
    exit;
}

if (empty($result['ok'])) {
    header('Location: ' . $base . '/Pages/customer/payments.php?payment=issue');
    exit;
}

if (($result['state'] ?? '') === 'paid' && !empty($result['booking_id'])) {
    header('Location: ' . $base . '/Pages/customer/payments.php?payment=success&booking_id=' . (int)$result['booking_id']);
    exit;
}

if (($result['state'] ?? '') === 'pending') {
    header('Location: ' . $base . '/Pages/customer/payments.php?payment=pending');
    exit;
}

if (($result['state'] ?? '') === 'failed') {
    header('Location: ' . $base . '/Pages/customer/payments.php?payment=failed');
    exit;
}

header('Location: ' . $base . '/Pages/customer/payments.php?payment=issue');
exit;
