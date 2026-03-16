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

    $bkStmt = $conn->prepare('SELECT booking_id, booking_date, service_type, equipment_id FROM bookings WHERE booking_id = ?');
    $bkStmt->bind_param('i', $booking_id);
    $bkStmt->execute();
    $bkRes = $bkStmt->get_result();
    $booking = $bkRes->fetch_assoc();
    $bkStmt->close();

    if (!$booking) {
        $_SESSION['error'] = 'Booking not found.';
        header('Location: ../booking-details.php?id=' . $booking_id);
        exit();
    }

    $eqStmt = $conn->prepare('SELECT category FROM equipment WHERE equipment_id = ?');
    $eqStmt->bind_param('s', $booking['equipment_id']);
    $eqStmt->execute();
    $eqRes = $eqStmt->get_result();
    $equipment = $eqRes->fetch_assoc();
    $eqStmt->close();

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

        // Workload rule: operator cannot be assigned to another active booking on the same date.
        $busyStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE operator_id = ? AND booking_date = ? AND status IN ('pending','confirmed','in_progress') AND booking_id <> ? LIMIT 1");
        $busyStmt->bind_param('isi', $operator_id, $booking['booking_date'], $booking_id);
        $busyStmt->execute();
        $busyRes = $busyStmt->get_result();
        $busy = $busyRes->fetch_assoc();
        $busyStmt->close();

        if ($busy) {
            $_SESSION['error'] = 'Cannot assign operator. This operator already has an active booking on the same date.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }

        // Availability rule: if availability records exist, operator must be available on booking day.
        $availStmt = $conn->prepare('SELECT day_of_week, is_available FROM operator_availability WHERE operator_id = ?');
        $availStmt->bind_param('i', $operator_id);
        $availStmt->execute();
        $availRes = $availStmt->get_result();
        $hasAvailability = false;
        $isAvailable = true;
        $dayOfWeek = (int)date('N', strtotime($booking['booking_date']));
        while ($row = $availRes->fetch_assoc()) {
            $hasAvailability = true;
            if ((int)$row['day_of_week'] === $dayOfWeek && (int)$row['is_available'] === 1) {
                $isAvailable = true;
                break;
            }
            $isAvailable = false;
        }
        $availStmt->close();
        if ($hasAvailability && !$isAvailable) {
            $_SESSION['error'] = 'Cannot assign operator. Operator is not available on the booking date.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }

        // Skills rule: if skills exist, operator must match equipment category or service type.
        $skillStmt = $conn->prepare('SELECT skill_name FROM operator_skills WHERE operator_id = ?');
        $skillStmt->bind_param('i', $operator_id);
        $skillStmt->execute();
        $skillRes = $skillStmt->get_result();
        $skills = [];
        while ($row = $skillRes->fetch_assoc()) {
            $skills[] = strtolower($row['skill_name']);
        }
        $skillStmt->close();

        if (!empty($skills)) {
            $category = strtolower($equipment['category'] ?? '');
            $serviceType = strtolower($booking['service_type'] ?? '');
            $matched = false;
            foreach ($skills as $skill) {
                if (($category !== '' && strpos($skill, $category) !== false) ||
                    ($serviceType !== '' && strpos($skill, $serviceType) !== false)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $_SESSION['error'] = 'Cannot assign operator. Operator skills do not match this booking.';
                header('Location: ../booking-details.php?id=' . $booking_id);
                exit();
            }
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

