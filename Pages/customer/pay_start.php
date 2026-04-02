<?php

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../auth/signin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['booking_id'])) {
    header('Location: payments.php');
    exit;
}

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/stripe_checkout.php';

if (!fes_stripe_configured()) {
    header('Location: payments.php?payment=not_configured');
    exit;
}

$bookingId = (int)$_POST['booking_id'];
$userId = (int)$_SESSION['user_id'];

try {
    $conn = getDBConnection();
} catch (Throwable $e) {
    error_log('pay_start DB: ' . $e->getMessage());
    header('Location: payments.php?payment=error');
    exit;
}

$stmt = $conn->prepare('SELECT booking_id, status, estimated_total_cost, payment_status FROM bookings WHERE booking_id = ? AND customer_id = ? LIMIT 1');
if (!$stmt) {
    $conn->close();
    header('Location: payments.php?payment=error');
    exit;
}
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$booking = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$booking) {
    $conn->close();
    header('Location: payments.php?payment=invalid');
    exit;
}

if (($booking['status'] ?? '') === 'cancelled') {
    $conn->close();
    header('Location: payments.php?payment=cancelled_booking');
    exit;
}

if (($booking['payment_status'] ?? '') === 'paid') {
    $conn->close();
    header('Location: payments.php?payment=already_paid');
    exit;
}

$cost = (float)($booking['estimated_total_cost'] ?? 0);
$amount = (int)max(1, (int)ceil($cost));
if ($cost <= 0) {
    $conn->close();
    header('Location: payments.php?payment=no_amount');
    exit;
}

$currency = strtoupper(FES_STRIPE_CURRENCY);
$txRef = 'fes' . bin2hex(random_bytes(16));

$cancel = $conn->prepare("UPDATE booking_payments SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ? AND user_id = ? AND status = 'pending'");
if ($cancel) {
    $cancel->bind_param('ii', $bookingId, $userId);
    $cancel->execute();
    $cancel->close();
}

$ins = $conn->prepare('INSERT INTO booking_payments (booking_id, user_id, tx_ref, amount, currency, provider, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
if (!$ins) {
    $conn->close();
    header('Location: payments.php?payment=error');
    exit;
}
$provider = 'stripe';
$pend = 'pending';
$ins->bind_param('iisisss', $bookingId, $userId, $txRef, $amount, $currency, $provider, $pend);
if (!$ins->execute()) {
    $ins->close();
    $conn->close();
    header('Location: payments.php?payment=error');
    exit;
}
$ins->close();

$pending = 'pending';
$upd = $conn->prepare('UPDATE bookings SET payment_status = ?, payment_tx_ref = ? WHERE booking_id = ? AND customer_id = ?');
if (!$upd) {
    $conn->query("UPDATE booking_payments SET status = 'failed', updated_at = NOW() WHERE tx_ref = '" . $conn->real_escape_string($txRef) . "'");
    $conn->close();
    header('Location: payments.php?payment=error');
    exit;
}
$upd->bind_param('ssii', $pending, $txRef, $bookingId, $userId);
if (!$upd->execute()) {
    $upd->close();
    $conn->query("UPDATE booking_payments SET status = 'failed', updated_at = NOW() WHERE tx_ref = '" . $conn->real_escape_string($txRef) . "'");
    $conn->close();
    header('Location: payments.php?payment=error');
    exit;
}
$upd->close();

$base = rtrim(FES_PUBLIC_BASE_URL, '/');
$successUrl = $base . '/Pages/customer/pay_callback.php?session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = $base . '/Pages/customer/pay_return.php?session_id={CHECKOUT_SESSION_ID}';

$email = trim((string)($_SESSION['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'customer@example.com';
}

$checkout = fes_stripe_create_checkout_session(
    $bookingId,
    $userId,
    $txRef,
    $amount,
    $currency,
    $email,
    $successUrl,
    $cancelUrl
);

if (!$checkout['ok'] || empty($checkout['url'])) {
    $fail = 'failed';
    $unpaid = 'unpaid';
    $stmt = $conn->prepare("UPDATE booking_payments SET status = ?, updated_at = NOW() WHERE tx_ref = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $fail, $txRef);
        $stmt->execute();
        $stmt->close();
    }
    $stmt = $conn->prepare('UPDATE bookings SET payment_status = ?, payment_tx_ref = NULL WHERE booking_id = ? AND customer_id = ?');
    if ($stmt) {
        $stmt->bind_param('sii', $unpaid, $bookingId, $userId);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    $msg = rawurlencode($checkout['error'] ?? 'Could not start checkout');
    header('Location: payments.php?payment=checkout_error&detail=' . $msg);
    exit;
}

$conn->close();
header('Location: ' . $checkout['url']);
exit;
