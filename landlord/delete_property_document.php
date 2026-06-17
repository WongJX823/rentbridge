<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

header('Content-Type: application/json');

verify_csrf();

$docId = (int)($_POST['document_id'] ?? 0);
if ($docId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid document ID']);
    exit;
}

$userId = current_user_id();
$pdo = db();

// Verify the document belongs to a property owned by this landlord
$stmt = $pdo->prepare("
    SELECT pd.file_path
      FROM property_documents pd
      JOIN properties p ON p.id = pd.property_id
     WHERE pd.id = ? AND p.landlord_id = ?
");
$stmt->execute([$docId, $userId]);
$doc = $stmt->fetch();

if (!$doc) {
    echo json_encode(['ok' => false, 'error' => 'Document not found or not yours']);
    exit;
}

try {
    // Delete DB row
    $stmt = $pdo->prepare("DELETE FROM property_documents WHERE id = ?");
    $stmt->execute([$docId]);

    // Delete file from disk (skip placeholder)
    if ($doc['file_path'] !== 'uploads/property_docs/placeholder.pdf') {
        $absPath = __DIR__ . '/../' . $doc['file_path'];
        if (file_exists($absPath)) {
            @unlink($absPath);
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}