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
$operator_id_raw = trim((string)($_POST['operator_id'] ?? ''));
$operator_id = ($operator_id_raw === '') ? null : (int)$operator_id_raw;

if ($equipment_id <= 0) {
    $_SESSION['error'] = 'Invalid equipment selected.';
    header('Location: ../equipment.php');
    exit();
}

try {
    $conn = getDBConnection();

    $eqStmt = $conn->prepare('SELECT id, equipment_name FROM equipment WHERE id = ?');
    $eqStmt->bind_param('i', $equipment_id);
    $eqStmt->execute();
    $eqRes = $eqStmt->get_result();
    $equipment = $eqRes->fetch_assoc();
    $eqStmt->close();

    if (!$equipment) {
        $_SESSION['error'] = 'Equipment not found.';
        header('Location: ../equipment.php');
        exit();
    }

    if ($operator_id !== null) {
        $opStmt = $conn->prepare("SELECT user_id, name FROM users WHERE user_id = ? AND role = 'operator'");
        $opStmt->bind_param('i', $operator_id);
        $opStmt->execute();
        $opRes = $opStmt->get_result();
        $operator = $opRes->fetch_assoc();
        $opStmt->close();

        if (!$operator) {
            $_SESSION['error'] = 'Selected operator is invalid.';
            header('Location: ../equipment.php');
            exit();
        }

        // Busy rule: operator cannot be assigned if already on another in-use equipment.
        $busyStmt = $conn->prepare("SELECT equipment_id, equipment_name FROM equipment WHERE operator_id = ? AND status = 'in_use' AND id <> ? LIMIT 1");
        $busyStmt->bind_param('ii', $operator_id, $equipment_id);
        $busyStmt->execute();
        $busyRes = $busyStmt->get_result();
        $busy = $busyRes->fetch_assoc();
        $busyStmt->close();

        if ($busy) {
            $_SESSION['error'] = 'Cannot assign operator. This operator is already assigned to active job on ' . $busy['equipment_id'] . ' (' . $busy['equipment_name'] . ').';
            header('Location: ../equipment.php');
            exit();
        }
    }

    if ($operator_id === null) {
        $updStmt = $conn->prepare('UPDATE equipment SET operator_id = NULL, updated_at = NOW() WHERE id = ?');
        $updStmt->bind_param('i', $equipment_id);
    } else {
        $updStmt = $conn->prepare('UPDATE equipment SET operator_id = ?, updated_at = NOW() WHERE id = ?');
        $updStmt->bind_param('ii', $operator_id, $equipment_id);
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

header('Location: ../equipment.php');
exit();
?>

