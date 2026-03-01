<?php
// Simple fallback email function using PHP's mail() when PHPMailer is not available
function sendOperatorEmail($to, $full_name, $email, $plain_password) {
    $subject = 'Your FES Operator Account';
    $message = "Hello $full_name,\n\nYour operator account has been created.\n\nEmail: $email\nPassword: $plain_password\n\nPlease change your password after first login.\n\nRegards,\nFES Administration";
    $headers = 'From: adrianmalika01@gmail.com' . "\r\n" .
               'Reply-To: adrianmalika01@gmail.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    return mail($to, $subject, $message, $headers);
}
?>
