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
require_once '../../../includes/fes_skill_types.php';

$operator_id = (int)($_POST['operator_id'] ?? 0);
$skill_name = trim($_POST['skill_name'] ?? '');
$skill_level = trim($_POST['skill_level'] ?? 'intermediate');

$allowed_levels = ['beginner', 'intermediate', 'advanced', 'expert'];

if ($operator_id <= 0 || $skill_name === '' || !fes_is_operator_skill_type($skill_name)) {
    $_SESSION['error'] = 'Choose a valid skill type from the list.';
    $redir = $operator_id > 0 ? '../operator_manage.php?id=' . $operator_id : '../users.php';
    header('Location: ' . $redir);
    exit();
}

if (!in_array($skill_level, $allowed_levels, true)) {
    $skill_level = 'intermediate';
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

    $stmt = $conn->prepare('INSERT INTO operator_skills (operator_id, skill_name, skill_level) VALUES (?, ?, ?)');
    if (!$stmt) {
        throw new Exception('Could not prepare skill insert.');
    }
    $stmt->bind_param('iss', $operator_id, $skill_name, $skill_level);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Skill added successfully.';
    } else {
        $_SESSION['error'] = 'Failed to add skill.';
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Add operator skill error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while adding skill.';
}

header('Location: ../operator_manage.php?id=' . $operator_id);
exit();


