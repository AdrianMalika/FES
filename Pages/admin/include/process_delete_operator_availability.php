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
$slot_id = (int)($_POST['slot_id'] ?? 0);

if ($operator_id <= 0 || $slot_id <= 0) {
    $_SESSION['error'] = 'Invalid availability slot or operator.';
    header('Location: ../users.php');
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare('DELETE FROM operator_availability WHERE id = ? AND operator_id = ?');
    if (!$stmt) {
        throw new Exception('Could not prepare availability delete.');
    }
    $stmt->bind_param('ii', $slot_id, $operator_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = 'Availability slot removed.';
        } else {
            $_SESSION['error'] = 'Availability slot not found.';
        }
    } else {
        $_SESSION['error'] = 'Failed to remove availability slot.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Delete operator availability error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while removing availability.';
}

header('Location: ../operator_manage.php?id=' . $operator_id);
exit();


