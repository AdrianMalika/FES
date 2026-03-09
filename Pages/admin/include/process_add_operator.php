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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../add_operator.php?error=Invalid request method');
    exit();
}

$full_name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$assign_equipment_id = (int)($_POST['assign_equipment_id'] ?? 0);

if (empty($full_name) || empty($email) || empty($password)) {
    header('Location: ../add_operator.php?error=' . urlencode('All fields are required'));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../add_operator.php?error=' . urlencode('Invalid email address'));
    exit();
}

if (strlen($password) < 8) {
    header('Location: ../add_operator.php?error=' . urlencode('Password must be at least 8 characters long'));
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
        header('Location: ../add_operator.php?error_code=duplicate_email&email=' . urlencode($email));
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, \'operator\')');
    if (!$insertStmt) {
        throw new \Exception('Database error preparing operator insert: ' . $conn->error);
    }
    $insertStmt->bind_param('sss', $full_name, $email, $hashed_password);
    if (!$insertStmt->execute()) {
        throw new \Exception('Failed to create operator account.');
    }
    $new_operator_id = (int)$conn->insert_id;

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

    // Content: send login details with the password provided by admin
    $mail->isHTML(true);
    $mail->Subject = 'Your FES operator account';
    
    $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/FES/Pages/auth/signin.php';
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

    // HTML Email body
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
            <div style='background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h1 style='color: #D32F2F; margin: 0; font-size: 28px; font-weight: bold;'>Welcome to FES</h1>
                    <p style='color: #666; margin: 10px 0 0 0; font-size: 16px;'>An operator account has been created for you. Use the credentials below to sign in.</p>
                </div>
                
                <div style='background-color: #f8f9fa; padding: 25px; border-radius: 8px; border-left: 4px solid #D32F2F; margin: 20px 0;'>
                    <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>Your Login Details</h2>
                    
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #495057; width: 120px;'>Email:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #dee2e6; color: #212529;'>{$safeEmail}</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #495057;'>Password:</td>
                            <td style='padding: 12px; border-bottom: 1px solid #dee2e6; color: #212529;'>{$safePassword}</td>
                        </tr>
                        <tr>
                            <td style='padding: 12px; font-weight: bold; color: #495057;'>Login URL:</td>
                            <td style='padding: 12px; color: #212529;'>
                                <a href='{$loginUrl}' 
                                   style='color: #D32F2F; text-decoration: none; font-weight: bold;'>
                                   Click here to login →
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div style='background-color: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;'>
                    <p style='margin: 0; color: #856404; font-weight: bold;'>
                        <i style='margin-right: 8px;'>⚠️</i>
                        <strong>Security Notice:</strong> For your security, please change your password after first login.
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0; color: #666; font-size: 14px;'>
                        Best regards,<br>
                        <strong style='color: #D32F2F;'>FES Team</strong>
                    </p>
                </div>
            </div>
        </div>
    ";
    
    // Plain text alternative
    $mail->AltBody = "Welcome to FES!\n\n" .
        "An operator account has been created for you. Use the credentials below to sign in.\n\n" .
        "Email: {$email}\n" .
        "Password: {$password}\n" .
        "Login URL: {$loginUrl}\n\n" .
        "Best regards,\nFES Team";

    // Will throw a MailException on failure
    $mail->send();

    // Log successful email send
    error_log("Email successfully sent to: $email for operator: $full_name");

    // All good: commit transaction and redirect with success
    $conn->commit();

    header('Location: ../add_operator.php?success=' . urlencode('Operator account created and email sent successfully.'));
    exit();

} catch (\Exception $e) {
    // Roll back any DB changes if something failed (DB error or email failure)
    if ($conn instanceof \mysqli) {
        $conn->rollback();
    }

    error_log('Operator creation error: ' . $e->getMessage());

    // Show a friendlier message if it's an email problem
    $message = stripos($e->getMessage(), 'SMTP Error') !== false
        ? 'Operator account could not be created because the email could not be sent. Please check email settings.'
        : $e->getMessage();

    header('Location: ../add_operator.php?error=' . urlencode($message));
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


