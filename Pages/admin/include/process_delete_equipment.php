<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../equipment.php');
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../equipment.php');
    exit();
}

require_once '../../../includes/database.php';

$equipment_id = (int)($_POST['equipment_id'] ?? 0);
if ($equipment_id <= 0) {
    $_SESSION['error'] = 'Invalid equipment selected for deletion.';
    header('Location: ../equipment.php');
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare('DELETE FROM equipment WHERE id = ?');
    $stmt->bind_param('i', $equipment_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = 'Equipment deleted successfully.';
        } else {
            $_SESSION['error'] = 'Equipment not found or already deleted.';
        }
    } else {
        $_SESSION['error'] = 'Failed to delete equipment.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Equipment delete error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while deleting equipment.';
}

header('Location: ../equipment.php');
exit();
?>


