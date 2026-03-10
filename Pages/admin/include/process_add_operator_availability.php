<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../users.php');
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../users.php');
    exit();
}

require_once '../../../includes/database.php';

$operator_id = (int)($_POST['operator_id'] ?? 0);
$day_of_week = (int)($_POST['day_of_week'] ?? 1);
$start_time = trim($_POST['start_time'] ?? '');
$end_time = trim($_POST['end_time'] ?? '');
$is_available = isset($_POST['is_available']) ? 1 : 0;

if ($operator_id <= 0 || $start_time === '' || $end_time === '') {
    $_SESSION['error'] = 'Operator and time range are required.';
    header('Location: ../users.php');
    exit();
}

if ($day_of_week < 0 || $day_of_week > 6) {
    $day_of_week = 1;
}

if ($start_time >= $end_time) {
    $_SESSION['error'] = 'End time must be after start time.';
    header('Location: ../operator_manage.php?id=' . $operator_id);
    exit();
}

try {
    $conn = getDBConnection();

    // Ensure operator exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'operator'");
    if ($stmt) {
        $stmt->bind_param('i', $operator_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            $_SESSION['error'] = 'Operator not found.';
            header('Location: ../users.php');
            $conn->close();
            exit();
        }
    }

    $stmt = $conn->prepare('INSERT INTO operator_availability (operator_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Could not prepare availability insert.');
    }
    $stmt->bind_param('iissi', $operator_id, $day_of_week, $start_time, $end_time, $is_available);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Availability slot added.';
    } else {
        $_SESSION['error'] = 'Failed to add availability.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Add operator availability error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while adding availability.';
}

header('Location: ../operator_manage.php?id=' . $operator_id);
exit();

