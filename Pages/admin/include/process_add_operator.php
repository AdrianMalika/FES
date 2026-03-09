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
$created_by = (int)($_SESSION['user_id'] ?? 0);
$assign_equipment_id = (int)($_POST['assign_equipment_id'] ?? 0);

if (empty($full_name) || empty($email)) {
    header('Location: ../add_operator.php?error=All fields are required');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../add_operator.php?error=Invalid email address');
    exit();
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        header('Location: ../add_operator.php?error_code=duplicate_email&email=' . urlencode($email));
        exit();
    }
    $checkStmt->close();

    // Do NOT generate plaintext passwords. Create unverified user with temporary hash.
    $hashed_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insertStmt = $conn->prepare('INSERT INTO users (name, email, password_hash, role, created_by) VALUES (?, ?, ?, \'operator\', ?)');
    $insertStmt->bind_param('sssi', $full_name, $email, $hashed_password, $created_by);
    if (!$insertStmt->execute()) {
        throw new \Exception('Failed to create operator account.');
    }
    $insertStmt->close();
    $new_operator_id = (int)$conn->insert_id;

    // Optional: assign selected equipment to the new operator if equipment exists.
    if ($assign_equipment_id > 0) {
        $eqStmt = $conn->prepare('UPDATE equipment SET operator_id = ?, updated_at = NOW() WHERE id = ? AND (operator_id IS NULL OR operator_id = 0)');
        $eqStmt->bind_param('ii', $new_operator_id, $assign_equipment_id);
        $eqStmt->execute();
        $eqStmt->close();
    }

    $conn->commit();

    // Generate secure password-set token (24 hours)
    $user_id = $new_operator_id;
    $token = bin2hex(random_bytes(16));
    $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
    $upd = $conn->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?');
    $upd->bind_param('ssi', $token, $expires, $user_id);
    $upd->execute();
    $upd->close();

    // Send email using PHPMailer with Gmail SMTP
    $config = include '../../../includes/email_config.php';
    $mail = new PHPMailer(true);

    try {
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

        // Content (password-set link, NOT plaintext password)
        $mail->isHTML(true);
        $mail->Subject = 'Activate your FES operator account';
        $setPassUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/FES/Pages/auth/set_password.php?token=' . urlencode($token);
        
        // HTML Email body
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                <div style='background-color: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h1 style='color: #D32F2F; margin: 0; font-size: 28px; font-weight: bold;'>Welcome to FES</h1>
                        <p style='color: #666; margin: 10px 0 0 0; font-size: 16px;'>An operator account has been created for you. To activate your account and set a secure password, click the button below.</p>
                    </div>
                    
                    <div style='background-color: #f8f9fa; padding: 25px; border-radius: 8px; border-left: 4px solid #D32F2F; margin: 20px 0;'>
                        <h2 style='color: #333; margin: 0 0 20px 0; font-size: 18px;'>Your Login Details</h2>
                        
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #495057; width: 120px;'>Email:</td>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6; color: #212529;'>{$email}</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6; font-weight: bold; color: #495057;'>Set Password:</td>
                                <td style='padding: 12px; border-bottom: 1px solid #dee2e6; color: #212529;'><a href='" . $setPassUrl . "' style='display:inline-block;padding:10px 16px;background:#D32F2F;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;'>Click here to set password</a></td>
                            </tr>
                            <tr>
                                <td style='padding: 12px; font-weight: bold; color: #495057;'>Login URL:</td>
                                <td style='padding: 12px; color: #212529;'>
                                    <a href='http://{$_SERVER['HTTP_HOST']}/FES/Pages/auth/signin.php' 
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
            "An operator account has been created for you. To activate your account and set a secure password, visit: " . $setPassUrl . "\n\n" .
            "Email: {$email}\n" .
            "Login URL: http://{$_SERVER['HTTP_HOST']}/FES/Pages/auth/signin.php\n\n" .
            "This link expires in 24 hours.\n\n" .
            "Best regards,\nFES Team";

        $mail->send();
        
        // Log successful email send
        error_log("Email successfully sent to: $email for operator: $full_name");
        
    } catch (MailException $e) {
        // Detailed error logging
        $errorDetails = [
            'time' => date('Y-m-d H:i:s'),
            'to' => $email,
            'error' => $e->getMessage(),
            'smtp_error' => isset($mail) ? $mail->ErrorInfo : 'N/A'
        ];
        
        error_log('Email sending failed: ' . print_r($errorDetails, true));
        
        // Continue even if email fails - account is created
    }

    header('Location: ../add_operator.php?success=Operator account created successfully.');
    exit();

} catch (\Exception $e) {
    $conn->rollback();
    error_log('Operator creation error: ' . $e->getMessage());
    header('Location: ../add_operator.php?error=' . urlencode($e->getMessage()));
    exit();
} finally {
    if (isset($checkStmt)) $checkStmt->close();
    if (isset($insertStmt)) $insertStmt->close();
    $conn->close();
}
?>


