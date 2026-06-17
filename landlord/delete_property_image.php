<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

header('Content-Type: application/json');

verify_csrf();

$imageId = (int)($_POST['image_id'] ?? 0);
if ($imageId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid image ID']);
    exit;
}

$userId = current_user_id();
$pdo = db();

// Verify the image belongs to a property owned by this landlord
$stmt = $pdo->prepare("
    SELECT pi.image_path, pi.property_id, pi.is_primary
      FROM property_images pi
      JOIN properties p ON p.id = pi.property_id
     WHERE pi.id = ? AND p.landlord_id = ?
");
$stmt->execute([$imageId, $userId]);
$img = $stmt->fetch();
$imageCount = (int)$stmt->fetchColumn();

if ($imageCount <= 1) {
    echo json_encode([
        'ok' => false,
        'error' => 'Property must have at least one image.'
    ]);
    exit;
}

if (!$img) {
    echo json_encode(['ok' => false, 'error' => 'Image not found or not yours']);
    exit;
}



try {
    $pdo->beginTransaction();

    // Delete DB row
    $stmt = $pdo->prepare("DELETE FROM property_images WHERE id = ?");
    $stmt->execute([$imageId]);

    // If this was the primary image, promote another one
    if ((int)$img['is_primary'] === 1) {
        $stmt = $pdo->prepare("
            UPDATE property_images
               SET is_primary = 1
             WHERE property_id = ?
             ORDER BY id ASC
             LIMIT 1
        ");
        $stmt->execute([$img['property_id']]);
    }

    $pdo->commit();

    // Delete file from disk (skip placeholder)
    if ($img['image_path'] !== 'uploads/properties/placeholder.jpg') {
        $absPath = __DIR__ . '/../' . $img['image_path'];
        if (file_exists($absPath)) {
            @unlink($absPath);
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}