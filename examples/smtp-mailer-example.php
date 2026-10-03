<?php
/**
 * SmtpMailer example — replace the placeholders with real SMTP credentials.
 * Run: php smtp-mailer-example.php
 */

require __DIR__ . '/../src/SmtpMailer.php';

// Port 587 + STARTTLS (e.g. Gmail, most hosts):
$mail = new SmtpMailer(
    'smtp.example.com',   // host
    587,                  // port
    'user@example.com',   // SMTP username
    'your-app-password'   // SMTP password (use an app password for Gmail)
);

// Port 465 implicit TLS would be: new SmtpMailer('host', 465, 'user', 'pass', true);

try {
    $mail->send(
        'friend@example.com',          // to
        'Hello from SmtpMailer',       // subject
        '<h1>It works!</h1><p>No PHPMailer required.</p>', // body
        'noreply@example.com',         // from
        true                           // isHtml
    );
    echo "Mail accepted by the SMTP server.\n";
} catch (RuntimeException $e) {
    echo 'Send failed: ' . $e->getMessage() . "\n";
}
