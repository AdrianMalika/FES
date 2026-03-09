<?php
// SMTP Configuration for local development (XAMPP)
return [
    'host' => 'smtp.gmail.com',           // Using Gmail SMTP for testing
    'username' => 'adrianmalika01@gmail.com',  // Your Gmail address
    'password' => 'vdaowauvlgrrdfvn',     // App Password (no spaces)
    'port' => 465,                        // Gmail SMTP port for SSL
    'encryption' => 'ssl',                // ssl for port 465
    'from_email' => 'adrianmalika01@gmail.com',
    'from_name' => 'FES System',
    'debug' => 2,                         // Enable verbose debug output (0=off, 1=client, 2=client and server)
];

/*
To set up Gmail SMTP:
1. Go to your Google Account: https://myaccount.google.com/
2. Enable 2-Step Verification (if not already enabled)
3. Create an App Password:
   - Go to: https://myaccount.google.com/apppasswords
   - Select 'Mail' and your device, then generate
   - Use this 16-character password in the config above
   
For production, consider using a professional SMTP service like:
- SendGrid
- Mailgun
- Amazon SES
- Your hosting provider's SMTP

For local development without SMTP, you can use:
1. MailHog (https://github.com/mailhog/MailHog)
2. Mailtrap (https://mailtrap.io/)
3. XAMPP's built-in mail server (less reliable)
*/
