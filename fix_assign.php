
<?php
require_once __DIR__ . '/includes/bookings.php';

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) die('Pass ?id=N');

$result = auto_assign_agent($bookingId);
echo $result ? "✅ Assigned to user_id $result" : "❌ Still no eligible agent.";