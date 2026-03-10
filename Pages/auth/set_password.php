<?php
session_start();
require_once __DIR__ . '/../../includes/database.php';

$error = '';
$success = '';
// Prefer GET (from link click); fallback to POST (form resubmit)
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user = null;

if (empty($token)) {
    $error = 'No activation link was provided. Please use the link from your activation email, or contact your administrator.';
}

if (!empty($token)) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare('SELECT user_id, name, email, password_reset_token, password_reset_expires FROM users WHERE password_reset_token = ? AND role = \'operator\'');
        if (!$stmt) {
            throw new Exception('Database error.');
        }
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log('Set password error: ' . $e->getMessage());
        $error = 'Unable to verify the activation link. Please ensure you have run database/add_password_reset_columns.sql, or contact your administrator.';
    }

    if ($user) {
        $expires = $user['password_reset_expires'] ?? null;
        if (!$expires || strtotime($expires) < time()) {
            $user = null;
            $error = 'This link has expired. Please contact your administrator for a new activation link.';
        }
    } elseif (empty($error)) {
        $error = 'This link is invalid or has already been used. Please contact your administrator for a new activation link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $conn = getDBConnection();
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?');
            if (!$upd) {
                throw new Exception('Database error.');
            }
            $upd->bind_param('si', $hashed, $user['user_id']);
            $upd->execute();
            $upd->close();
            $conn->close();
            $success = 'Your password has been set. You can now sign in.';
            $user = null;
            $token = '';
        } catch (Exception $e) {
            error_log('Set password update error: ' . $e->getMessage());
            $error = 'Failed to set password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4 { font-family: 'Barlow Condensed', sans-serif; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div style="max-width: 420px; width: 90%; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);">
        <h1 style="margin: 0 0 8px 0; font-size: 28px; color: #212121;">Set your password</h1>
        <p style="margin: 0 0 24px 0; font-size: 14px; color: #757575;">
            <?php if ($user): ?>Create a secure password for your operator account.<?php else: ?>Activate your account.<?php endif; ?>
        </p>

        <?php if (!empty($error)): ?>
            <div style="padding: 12px 16px; background-color: #FFEBEE; border-left: 4px solid #D32F2F; border-radius: 4px; margin-bottom: 20px;">
                <p style="margin: 0; color: #C62828; font-size: 14px;"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="padding: 12px 16px; background-color: #E8F5E9; border-left: 4px solid #388E3C; border-radius: 4px; margin-bottom: 20px;">
                <p style="margin: 0; color: #2E7D32; font-size: 14px;"><?php echo htmlspecialchars($success); ?></p>
            </div>
            <a href="signin.php" style="display: inline-block; padding: 14px 24px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Sign in</a>
        <?php elseif ($user): ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">New password</label>
                    <input type="password" name="password" required minlength="8" placeholder="At least 8 characters"
                        style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #212121;">Confirm password</label>
                    <input type="password" name="password_confirm" required minlength="8" placeholder="Repeat your password"
                        style="width: 100%; padding: 14px 16px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                </div>
                <button type="submit" style="width: 100%; padding: 16px; background: #D32F2F; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer;">Set password</button>
            </form>
        <?php else: ?>
            <a href="signin.php" style="display: inline-block; padding: 14px 24px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">Back to sign in</a>
        <?php endif; ?>
    </div>
</body>
</html>
