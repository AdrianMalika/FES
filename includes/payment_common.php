<?php

/**
 * Shared booking payment row updates (Stripe Checkout).
 */

function fes_booking_payment_reset_pending(
    mysqli $conn,
    int $paymentRowId,
    int $bookingId,
    int $userId,
    string $rowTerminalStatus
): void {
    $stmt = $conn->prepare('UPDATE booking_payments SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?');
    if (!$stmt) {
        return;
    }
    $pending = 'pending';
    $stmt->bind_param('sis', $rowTerminalStatus, $paymentRowId, $pending);
    $stmt->execute();
    $stmt->close();

    $unpaid = 'unpaid';
    $stmt = $conn->prepare('UPDATE bookings SET payment_status = ?, payment_tx_ref = NULL WHERE booking_id = ? AND customer_id = ?');
    if ($stmt) {
        $stmt->bind_param('sii', $unpaid, $bookingId, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/** Pending checkout abandoned — reset so customer can pay again. */
function fes_mark_checkout_abandoned_by_tx_ref(mysqli $conn, string $txRef): void
{
    $txRef = preg_replace('/[^a-zA-Z0-9._-]/', '', $txRef);
    if ($txRef === '') {
        return;
    }

    $stmt = $conn->prepare("SELECT id, booking_id, user_id, status FROM booking_payments WHERE tx_ref = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $txRef);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || $row['status'] !== 'pending') {
        return;
    }

    fes_booking_payment_reset_pending($conn, (int)$row['id'], (int)$row['booking_id'], (int)$row['user_id'], 'cancelled');
}
