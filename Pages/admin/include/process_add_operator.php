<?php
require_once '../../../includes/database.php';
require_once '../../../includes/email_config.php';
require_once '../../../vendor/autoload.php';

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\SMTP;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

// Build absolute URL to add_operator.php (avoids relative-path 404s)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$adminBase = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$addOperatorUrl = rtrim($scheme . '://' . $host . $adminBase, '/') . '/add_operator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $addOperatorUrl . '?error=' . urlencode('Invalid request method'));
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$assign_equipment_id = (int)($_POST['assign_equipment_id'] ?? 0);

if (empty($full_name) || empty($email)) {
    header('Location: ' . $addOperatorUrl . '?error=' . urlencode('Name and email are required'));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $addOperatorUrl . '?error=' . urlencode('Invalid email address'));
    exit();
}

$conn = getDBConnection();
$checkStmt = null;
$insertStmt = null;

try {
    $conn->begin_transaction();

    $checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
    if (!$checkStmt) {
        throw new \Exception('Database error preparing email check: ' . $conn->error);
    }
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        header('Location: ' . $addOperatorUrl . '?error_code=duplicate_email&email=' . urlencode($email));
        exit();
    }

    // Use random unguessable password until operator sets their own via secure token link
    $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, \'operator\')');
    if (!$insertStmt) {
        throw new \Exception('Database error preparing operator insert: ' . $conn->error);
    }
    $insertStmt->bind_param('sss', $full_name, $email, $hashed_password);
    if (!$insertStmt->execute()) {
        throw new \Exception('Failed to create operator account.');
    }
    $new_operator_id = (int)$conn->insert_id;

    // Generate secure password-set token (24 hours validity)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $updStmt = $conn->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?');
    if ($updStmt) {
        $updStmt->bind_param('ssi', $token, $expires, $new_operator_id);
        $updStmt->execute();
        $updStmt->close();
    }

    // Optional: assign selected equipment to the new operator if equipment exists.
    if ($assign_equipment_id > 0) {
        $eqStmt = $conn->prepare('UPDATE equipment SET operator_id = ?, updated_at = NOW() WHERE id = ? AND (operator_id IS NULL OR operator_id = 0)');
        if ($eqStmt) {
            $eqStmt->bind_param('ii', $new_operator_id, $assign_equipment_id);
            $eqStmt->execute();
            $eqStmt->close();
        }
    }

    // Send email using PHPMailer with Gmail SMTP (part of the same transaction)
    $config = include '../../../includes/email_config.php';
    $mail = new PHPMailer(true);

    // Server settings - reduce debug to avoid output issues
    $mail->SMTPDebug = 0; // Set to 0 for production, 2 for debugging
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
    };
    
    $mail->isSMTP();
    $mail->Host       = $config['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = $config['encryption'];
    $mail->Port       = $config['port'];

    // Additional settings for better compatibility
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    $mail->Timeout = 30;

    // Recipients
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($email, $full_name);

    // Content: secure activation link (no plaintext password)
    $mail->isHTML(true);
    $mail->Subject = 'Activate your FES operator account';
    $pagesDir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))); // include->admin->Pages
    $setPassUrl = $scheme . '://' . $host . $pagesDir . '/auth/set_password.php?token=' . urlencode($token);
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
            <div style='background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #D32F2F; margin: 0; font-size: 28px; font-weight: bold;'>Welcome to FES</h1>
                    <p style='color: #666; margin: 10px 0 0 0; font-size: 16px;'>An operator account has been created for you. Click the button below to set your password and activate your account.</p>
                </div>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $setPassUrl . "' style='display: inline-block; padding: 14px 28px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>Set your password</a>
                </div>
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 14px; color: #495057;'>Email: {$safeEmail}</p>
                    <p style='margin: 8px 0 0 0; font-size: 12px; color: #6c757d;'>This link expires in 24 hours. If it expires, contact your administrator for a new link.</p>
                </div>
                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0; color: #666; font-size: 14px;'>Best regards,<br><strong style='color: #D32F2F;'>FES Team</strong></p>
                </div>
            </div>
        </div>
    ";

    $mail->AltBody = "Welcome to FES!\n\n" .
        "An operator account has been created for you. To activate your account and set a secure password, visit:\n" .
        $setPassUrl . "\n\n" .
        "Email: {$email}\n" .
        "This link expires in 24 hours.\n\n" .
        "Best regards,\nFES Team";

    // Will throw a MailException on failure
    $mail->send();

    // Log successful email send
    error_log("Email successfully sent to: $email for operator: $full_name");

    // All good: commit transaction and redirect with success
    $conn->commit();

    header('Location: ' . $addOperatorUrl . '?success=' . urlencode('Operator account created. Activation email sent—operator must set password via the secure link.'));
    exit();

} catch (\Exception $e) {
    // Roll back any DB changes if something failed (DB error or email failure)
    if ($conn instanceof \mysqli) {
        $conn->rollback();
    }

    error_log('Operator creation error: ' . $e->getMessage());

    $msg = $e->getMessage();
    if (stripos($msg, 'SMTP Error') !== false) {
        $message = 'Operator account could not be created because the email could not be sent. Please check email settings.';
    } elseif (stripos($msg, 'password_reset') !== false || stripos($msg, 'Unknown column') !== false) {
        $message = 'Database is missing password_reset_token columns. Run database/add_password_reset_columns.sql and try again.';
    } else {
        $message = $msg;
    }

    header('Location: ' . $addOperatorUrl . '?error=' . urlencode($message));
    exit();
} finally {
    if ($checkStmt instanceof \mysqli_stmt) {
        $checkStmt->close();
    }
    if ($insertStmt instanceof \mysqli_stmt) {
        $insertStmt->close();
    }
    if ($conn instanceof \mysqli) {
        $conn->close();
    }
}
?>


