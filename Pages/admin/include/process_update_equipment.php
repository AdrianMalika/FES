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
$equipment_name = trim($_POST['equipment_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$status = trim($_POST['status'] ?? '');
$location = trim($_POST['location'] ?? '');

$allowed_status = ['available', 'in_use', 'maintenance', 'retired'];
if ($equipment_id <= 0 || $equipment_name === '' || $category === '' || $location === '' || !in_array($status, $allowed_status, true)) {
    $_SESSION['error'] = 'Invalid equipment update data.';
    header('Location: ../equipment.php');
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare('UPDATE equipment SET equipment_name = ?, category = ?, status = ?, location = ?, updated_at = NOW() WHERE id = ?');
    $stmt->bind_param('ssssi', $equipment_name, $category, $status, $location, $equipment_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Equipment updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update equipment.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Equipment update error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while updating equipment.';
}

header('Location: ../equipment.php');
exit();
?>

