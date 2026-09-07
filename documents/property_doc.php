<?php
/**
 * Auth-gated download for a property's ownership documents (IC copies,
 * geran, utility bills). These are private — landlord, their assigned
 * agent, and admin only — never served as plain static files under
 * uploads/property_docs/ (blocked there by .htaccess).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_login();

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    die('Invalid document ID.');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM property_documents WHERE id = ? LIMIT 1");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

if (!can_view_property_documents((int)$doc['property_id'], current_user_id(), current_role())) {
    http_response_code(403);
    die('You are not authorized to view this document.');
}

$bytes = rb_storage_get_contents($doc['file_path']);
if ($bytes === null) {
    http_response_code(404);
    die('File missing on disk.');
}

$mime = $doc['mime_type'] ?: (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($doc['original_name'] ?: $doc['file_path']) . '"');
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
echo $bytes;
