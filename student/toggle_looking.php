<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

verify_csrf();

$userId  = current_user_id();
$looking = isset($_POST['looking']) ? (int)$_POST['looking'] : 0;
$looking = $looking ? 1 : 0;

try {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE students SET looking_for_housing = ? WHERE user_id = ?");
    $stmt->execute([$looking, $userId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
