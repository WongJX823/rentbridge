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