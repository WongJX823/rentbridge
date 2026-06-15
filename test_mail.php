<?php
require_once __DIR__ . '/includes/mailer.php';

$result = send_email(
    'test@example.com',
    'Test User',
    'RentBridge SMTP test',
    '<h1>Hello</h1><p>If you see this in Mailtrap, SMTP works.</p>'
);

echo '<pre>';
print_r($result);
echo '</pre>';