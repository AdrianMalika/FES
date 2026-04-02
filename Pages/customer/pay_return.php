<?php

/**
 * Stripe cancel_url — customer left Checkout; release pending row if still unpaid.
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/stripe_checkout.php';

$base = rtrim(FES_PUBLIC_BASE_URL, '/');

if (!fes_stripe_configured()) {
    header('Location: ' . $base . '/Pages/customer/payments.php?payment=not_configured');
    exit;
}

$sessionId = trim((string)($_GET['session_id'] ?? ''));

if ($sessionId !== '') {
    try {
        $conn = getDBConnection();
        fes_stripe_abandon_checkout_session($conn, $sessionId);
        $conn->close();
    } catch (Throwable $e) {
        error_log('pay_return: ' . $e->getMessage());
    }
}

header('Location: ' . $base . '/Pages/customer/payments.php?payment=cancelled');
exit;
