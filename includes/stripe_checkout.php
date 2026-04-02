<?php

/**
 * Stripe Checkout — https://stripe.com/docs/payments/checkout
 */

if (!defined('FES_STRIPE_SECRET_KEY')) {
    require_once __DIR__ . '/payment_config.php';
}

require_once __DIR__ . '/payment_common.php';

$__fes_stripe_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($__fes_stripe_autoload)) {
    require_once $__fes_stripe_autoload;
}

function fes_stripe_currency_is_zero_decimal(string $currency): bool
{
    static $zero = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
    return in_array(strtolower($currency), $zero, true);
}

/** Whole major units (Malawi Kwacha) → Stripe smallest currency unit (MWK uses 2 decimals → ×100). */
function fes_stripe_to_unit_amount(int $wholeMajorUnits, string $currency): int
{
    $wholeMajorUnits = max(1, $wholeMajorUnits);
    if (fes_stripe_currency_is_zero_decimal($currency)) {
        return $wholeMajorUnits;
    }
    return $wholeMajorUnits * 100;
}

function fes_stripe_configured(): bool
{
    if (FES_STRIPE_SECRET_KEY === '' || FES_PUBLIC_BASE_URL === '') {
        return false;
    }
    return class_exists(\Stripe\Stripe::class);
}

/**
 * @return array{ok:bool, url?:string, error?:string}
 */
function fes_stripe_create_checkout_session(
    int $bookingId,
    int $userId,
    string $txRef,
    int $amountWhole,
    string $currencyUpper,
    string $email,
    string $successUrl,
    string $cancelUrl
): array {
    if (!fes_stripe_configured()) {
        return ['ok' => false, 'error' => 'Stripe is not configured'];
    }

    $currency = strtolower($currencyUpper);
    $unitAmount = fes_stripe_to_unit_amount($amountWhole, $currency);

    try {
        \Stripe\Stripe::setApiKey(FES_STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'client_reference_id' => $txRef,
            'customer_email' => $email,
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => 'FES booking #' . $bookingId,
                        'description' => 'Farm equipment service (Malawi Kwacha)',
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'booking_id' => (string)$bookingId,
                'user_id' => (string)$userId,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $url = $session->url ?? '';
        if ($url === '') {
            return ['ok' => false, 'error' => 'No checkout URL returned'];
        }
        return ['ok' => true, 'url' => $url];
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Stripe checkout: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not start checkout'];
    }
}

/**
 * @return array{ok:bool, state:string, booking_id?:int|null, message?:string}
 */
function fes_stripe_finalize_payment(mysqli $conn, string $sessionId): array
{
    $sessionId = preg_replace('/[^a-zA-Z0-9._-]/', '', $sessionId);
    if ($sessionId === '') {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Missing session'];
    }

    if (!fes_stripe_configured()) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Not configured'];
    }

    try {
        \Stripe\Stripe::setApiKey(FES_STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        error_log('Stripe finalize retrieve: ' . $e->getMessage());
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Retrieve failed'];
    }

    $txRef = (string)($session->client_reference_id ?? '');
    $txRef = preg_replace('/[^a-zA-Z0-9._-]/', '', $txRef);
    if ($txRef === '') {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Missing reference'];
    }

    $stmt = $conn->prepare('SELECT id, booking_id, user_id, amount, currency, status FROM booking_payments WHERE tx_ref = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Database error'];
    }
    $stmt->bind_param('s', $txRef);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => null, 'message' => 'Unknown payment'];
    }

    $bookingId = (int)$row['booking_id'];
    $userId = (int)$row['user_id'];
    $paymentRowId = (int)$row['id'];

    if ($row['status'] === 'paid') {
        return ['ok' => true, 'state' => 'paid', 'booking_id' => $bookingId, 'message' => 'Already recorded'];
    }

    $metaBid = (int)($session->metadata['booking_id'] ?? 0);
    $metaUid = (int)($session->metadata['user_id'] ?? 0);
    if ($metaBid !== $bookingId || $metaUid !== $userId) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => $bookingId, 'message' => 'Metadata mismatch'];
    }

    $expectedTotal = fes_stripe_to_unit_amount((int)$row['amount'], (string)$row['currency']);
    $paid = ($session->payment_status === 'paid')
        && ((int)$session->amount_total === $expectedTotal)
        && (strtolower((string)$session->currency) === strtolower((string)$row['currency']));

    if (!$paid) {
        $st = (string)$session->status;
        if ($session->payment_status === 'unpaid' && ($st === 'expired' || $st === 'complete')) {
            return ['ok' => true, 'state' => 'failed', 'booking_id' => $bookingId, 'message' => 'Not completed'];
        }
        return ['ok' => true, 'state' => 'pending', 'booking_id' => $bookingId, 'message' => 'Still pending'];
    }

    $stmt = $conn->prepare("UPDATE booking_payments SET status = 'paid', updated_at = NOW() WHERE id = ? AND status = 'pending'");
    if (!$stmt) {
        return ['ok' => false, 'state' => 'unknown', 'booking_id' => $bookingId, 'message' => 'Database error'];
    }
    $stmt->bind_param('i', $paymentRowId);
    $stmt->execute();
    $aff = $stmt->affected_rows;
    $stmt->close();

    if ($aff === 0) {
        return ['ok' => true, 'state' => 'paid', 'booking_id' => $bookingId, 'message' => 'Already processed'];
    }

    $ps = 'paid';
    $stmt = $conn->prepare('UPDATE bookings SET payment_status = ?, payment_paid_at = NOW() WHERE booking_id = ? AND customer_id = ? AND payment_tx_ref = ?');
    if ($stmt) {
        $stmt->bind_param('siis', $ps, $bookingId, $userId, $txRef);
        $stmt->execute();
        $stmt->close();
    }

    require_once __DIR__ . '/fes_admin_payment_mail.php';
    fes_send_admin_payment_received_email($conn, $bookingId, (int)$row['amount'], (string)$row['currency']);

    return ['ok' => true, 'state' => 'paid', 'booking_id' => $bookingId, 'message' => 'Recorded'];
}

function fes_stripe_abandon_checkout_session(mysqli $conn, string $sessionId): void
{
    $sessionId = preg_replace('/[^a-zA-Z0-9._-]/', '', $sessionId);
    if ($sessionId === '' || !fes_stripe_configured()) {
        return;
    }

    try {
        \Stripe\Stripe::setApiKey(FES_STRIPE_SECRET_KEY);
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
    } catch (Throwable $e) {
        error_log('Stripe abandon retrieve: ' . $e->getMessage());
        return;
    }

    if (($session->payment_status ?? '') === 'paid') {
        return;
    }

    $ref = (string)($session->client_reference_id ?? '');
    if ($ref !== '') {
        fes_mark_checkout_abandoned_by_tx_ref($conn, $ref);
    }
}
