<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../bookings.php');
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../bookings.php');
    exit();
}

require_once '../../../includes/database.php';

$booking_id = (int)($_POST['booking_id'] ?? 0);
$operator_id_raw = trim((string)($_POST['operator_id'] ?? ''));
$operator_id = ($operator_id_raw === '') ? null : (int)$operator_id_raw;

if ($booking_id <= 0) {
    $_SESSION['error'] = 'Invalid booking selected.';
    header('Location: ../booking-details.php?id=' . $booking_id);
    exit();
}

try {
    $conn = getDBConnection();

    if ($operator_id !== null) {
        $opStmt = $conn->prepare("SELECT user_id, name FROM users WHERE user_id = ? AND role = 'operator'");
        $opStmt->bind_param('i', $operator_id);
        $opStmt->execute();
        $opRes = $opStmt->get_result();
        $operator = $opRes->fetch_assoc();
        $opStmt->close();

        if (!$operator) {
            $_SESSION['error'] = 'Selected operator is invalid.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }

        // One-to-one rule: operator cannot be assigned if already linked to any other booking.
        $busyStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE operator_id = ? AND booking_id <> ? LIMIT 1");
        $busyStmt->bind_param('ii', $operator_id, $booking_id);
        $busyStmt->execute();
        $busyRes = $busyStmt->get_result();
        $busy = $busyRes->fetch_assoc();
        $busyStmt->close();

        if ($busy) {
            $_SESSION['error'] = 'Cannot assign operator. This operator is already assigned to another booking. Unassign them there first.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }
    }

    if ($operator_id === null) {
        $updStmt = $conn->prepare('UPDATE bookings SET operator_id = NULL, updated_at = NOW() WHERE booking_id = ?');
        $updStmt->bind_param('i', $booking_id);
    } else {
        $updStmt = $conn->prepare('UPDATE bookings SET operator_id = ?, updated_at = NOW() WHERE booking_id = ?');
        $updStmt->bind_param('ii', $operator_id, $booking_id);
    }

    if ($updStmt->execute()) {
        $_SESSION['success'] = 'Operator assignment updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update operator assignment.';
    }
    $updStmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Operator assignment error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while updating assignment.';
}

header('Location: ../booking-details.php?id=' . $booking_id);
exit();
?>

