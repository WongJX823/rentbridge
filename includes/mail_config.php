<?php
// Mailtrap SMTP credentials
// Get these from https://mailtrap.io → Email Testing → Inboxes → SMTP Settings
return [
    'host'       => 'sandbox.smtp.mailtrap.io',
    'port'       => 2525,
    'username'   => '2c1d8623879d5e',  // ← from Mailtrap
    'password'   => 'c34ecb98bea239',  // ← from Mailtrap
    'encryption' => 'tls',  // or '' for none
    'from_email' => 'noreply@rentbridge.com',
    'from_name'  => 'RentBridge',
];