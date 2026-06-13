<?php
require_once __DIR__ . '/includes/auth.php';

// Smart router — no UI of its own
if (is_logged_in()) {
    $dashboardPath = match (current_role()) {
        'student'  => '/rentbridge/student/dashboard.php',
        'landlord' => '/rentbridge/landlord/dashboard.php',
        'agent'    => '/rentbridge/agent/dashboard.php',
        'admin'    => '/rentbridge/admin/dashboard.php',
        default    => '/rentbridge/listings.php',
    };
    header('Location: ' . $dashboardPath);
    exit;
}

// Not logged in → send to browse
header('Location: /rentbridge/listings.php');
exit;