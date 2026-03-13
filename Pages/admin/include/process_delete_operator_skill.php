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
$skill_id = (int)($_POST['skill_id'] ?? 0);

if ($operator_id <= 0 || $skill_id <= 0) {
    $_SESSION['error'] = 'Invalid skill or operator.';
    header('Location: ../users.php');
    exit();
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare('DELETE FROM operator_skills WHERE id = ? AND operator_id = ?');
    if (!$stmt) {
        throw new Exception('Could not prepare skill delete.');
    }
    $stmt->bind_param('ii', $skill_id, $operator_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = 'Skill removed.';
        } else {
            $_SESSION['error'] = 'Skill not found.';
        }
    } else {
        $_SESSION['error'] = 'Failed to remove skill.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Delete operator skill error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while removing skill.';
}

header('Location: ../operator_manage.php?id=' . $operator_id);
exit();


