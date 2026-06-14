<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
require_role('student');

header('Content-Type: application/json');

$bookingId = (int)($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) { echo json_encode([]); exit; }

$userId = current_user_id();
$pdo = db();

// Verify user is the primary tenant on this booking
$stmt = $pdo->prepare("
    SELECT ct.full_name, ct.ic_number, ct.home_address
      FROM co_tenants ct
     WHERE ct.booking_id = ? AND ct.student_id = ? AND ct.is_primary = 1
");
$stmt->execute([$bookingId, $userId]);
$row = $stmt->fetch();
echo json_encode($row ?: []);