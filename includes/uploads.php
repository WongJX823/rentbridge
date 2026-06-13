<?php
/**
 * File upload helpers
 * Centralizes validation + safe filename generation.
 */

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_UPLOAD_BYTES    = 5 * 1024 * 1024;  // 5 MB

/**
 * Validate a single file from $_FILES (already moved into a slot).
 * Returns null if OK, or error message string.
 */
function validate_image_upload(array $file): ?string {
    // Did the upload itself succeed?
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed (error code: ' . $file['error'] . ').';
    }

    // Size check
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return 'File is too large (max 5 MB).';
    }

    // Real MIME type check (not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realType, ALLOWED_IMAGE_TYPES, true)) {
        return 'Only JPG, PNG, and WEBP images are allowed.';
    }

    return null;  // valid
}

/**
 * Save an uploaded file to /uploads/properties with a safe name.
 * Returns the relative path saved (for the DB), e.g. "uploads/properties/prop_xxx.jpg"
 */
function save_property_image(array $file): string {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $ext = 'jpg';
    }

    $filename = uniqid('prop_', true) . '.' . $ext;
    $relPath  = 'uploads/properties/' . $filename;
    $absPath  = __DIR__ . '/../' . $relPath;
    $absDir   = dirname($absPath);

    // Ensure target folder exists (create recursively if missing)
    if (!is_dir($absDir)) {
        if (!mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Failed to create upload directory: ' . $absDir);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return $relPath;
}

/**
 * Save an inspection photo upload to disk.
 * Returns the relative path (e.g. uploads/inspections/insp_abc123.jpg)
 */
function save_inspection_photo(array $file): string {
    $dir = __DIR__ . '/../uploads/inspections';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $filename = 'insp_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $fullPath = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new RuntimeException('Failed to save photo.');
    }

    return 'uploads/inspections/' . $filename;
}

/**
 * Save an uploaded property document.
 * Returns ['ok' => bool, 'error' => string|null, 'doc_id' => int|null]
 */
function save_property_document(
    int $propertyId,
    int $uploaderId,
    string $documentType,
    array $fileUpload,
    ?string $notes = null
): array {
    $allowedTypes = ['ownership_proof', 'utility_bill', 'other'];
    if (!in_array($documentType, $allowedTypes, true)) {
        return ['ok' => false, 'error' => 'Invalid document type', 'doc_id' => null];
    }

    if (!isset($fileUpload['error']) || $fileUpload['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (error code: ' . ($fileUpload['error'] ?? 'unknown') . ')', 'doc_id' => null];
    }

    // Validate size — 5 MB
    if ($fileUpload['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'File too large (max 5MB)', 'doc_id' => null];
    }

    // Validate MIME type — be strict
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($fileUpload['tmp_name']);
    $allowedMimes = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];
    if (!isset($allowedMimes[$mime])) {
        return ['ok' => false, 'error' => 'Invalid file type (must be PDF, JPG, PNG, or WebP)', 'doc_id' => null];
    }
    $ext = $allowedMimes[$mime];

    // Build safe filename
    $newName = $propertyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/property_docs';
    $destPath = $destDir . '/' . $newName;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (!move_uploaded_file($fileUpload['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Failed to save file', 'doc_id' => null];
    }

    // Save DB record
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO property_documents
            (property_id, document_type, file_path, original_name, file_size, mime_type, uploaded_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $propertyId,
        $documentType,
        'uploads/property_docs/' . $newName,
        substr($fileUpload['name'], 0, 150),
        $fileUpload['size'],
        $mime,
        $uploaderId,
        $notes ?: null,
    ]);

    return ['ok' => true, 'error' => null, 'doc_id' => (int)$pdo->lastInsertId()];
}

/**
 * Delete a property document (file + DB row).
 * Authorization should be checked BEFORE calling.
 */
function delete_property_document(int $docId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT file_path FROM property_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    if (!$doc) return false;

    $fullPath = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }

    $stmt = $pdo->prepare("DELETE FROM property_documents WHERE id = ?");
    return $stmt->execute([$docId]);
}

/**
 * Get all documents for a property.
 */
function get_property_documents(int $propertyId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT * FROM property_documents
         WHERE property_id = ?
         ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$propertyId]);
    return $stmt->fetchAll();
}

/**
 * Check if a user is allowed to view a property's documents.
 * Returns true for: owner landlord, admin, assigned agent.
 */
function can_view_property_documents(int $propertyId, int $userId, string $role): bool {
    if ($role === 'admin') return true;

    $pdo = db();

    if ($role === 'landlord') {
        // Landlord can view their own property's docs
        $stmt = $pdo->prepare("SELECT 1 FROM properties WHERE id = ? AND landlord_id = ?");
        $stmt->execute([$propertyId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    if ($role === 'agent') {
        // Agent can view if they're currently assigned to a booking on this property
        $stmt = $pdo->prepare("
            SELECT 1 FROM bookings
             WHERE property_id = ?
               AND agent_id = ?
               AND status IN ('agent_assigned','agent_verifying','agent_verified','contract_pending','active')
             LIMIT 1
        ");
        $stmt->execute([$propertyId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    return false;
}