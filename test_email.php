<?php
require_once 'vendor/autoload.php';
require_once 'includes/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h1>Email Test Script</h1>";

$config = include 'includes/email_config.php';

echo "<h2>Configuration:</h2>";
echo "<pre>";
echo "Host: " . $config['host'] . "\n";
echo "Username: " . $config['username'] . "\n";
echo "Password: " . str_repeat('*', strlen($config['password'])) . "\n";
echo "Port: " . $config['port'] . "\n";
echo "Encryption: " . $config['encryption'] . "\n";
echo "From: " . $config['from_email'] . "\n";
echo "</pre>";

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 2; // Enable verbose debug output
    $mail->isSMTP();
    $mail->Host       = $config['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = $config['encryption'];
    $mail->Port       = $config['port'];

    // Recipients
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['username'], 'Test Recipient'); // Send to yourself

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from FES';
    $mail->Body    = '<h1>Test Email</h1><p>This is a test email from your FES system.</p>';
    $mail->AltBody = 'This is a test email from your FES system.';

    echo "<h2>Attempting to send email...</h2>";
    $mail->send();
    echo '<div style="color: green;"><h3>✅ Email sent successfully!</h3></div>';
    
} catch (Exception $e) {
    echo '<div style="color: red;"><h3>❌ Email failed to send</h3></div>';
    echo '<h3>Error Details:</h3>';
    echo '<pre>';
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'SMTP Error: ' . $mail->ErrorInfo . "\n";
    echo '</pre>';
    
    echo '<h3>Troubleshooting Steps:</h3>';
    echo '<ol>';
    echo '<li>Make sure 2-Step Verification is enabled on your Google Account</li>';
    echo '<li>Create an App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>';
    echo '<li>Select "Mail" and your device, then use the 16-character password</li>';
    echo '<li>Check if "Less secure app access" is enabled (if not using App Password)</li>';
    echo '<li>Try changing the password in email_config.php to the App Password</li>';
    echo '</ol>';
}
?>
