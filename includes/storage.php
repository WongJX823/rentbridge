<?php
/**
 * Object storage abstraction (Cloudflare R2, S3-compatible).
 *
 * Every caller in this codebase uses these functions instead of touching
 * uploads/ directly. When R2 credentials aren't configured (local XAMPP dev,
 * or before this is wired up), everything degrades to the local uploads/
 * folder — same behavior as before this existed. In production (Render),
 * R2 credentials are set, so uploads survive restarts/redeploys instead of
 * living on Render's ephemeral disk.
 *
 * Keys are the same relative paths already used everywhere else, e.g.
 * 'uploads/properties/prop_xxx.jpg' — no path format changed, so DB columns
 * that already store these paths need no migration.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/storage.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function rb_storage_enabled(): bool {
    return RB_R2_ACCOUNT_ID !== '' && RB_R2_ACCESS_KEY !== ''
        && RB_R2_SECRET_KEY !== '' && RB_R2_BUCKET !== '';
}

function rb_storage_client(): ?S3Client {
    static $client = null;
    static $tried = false;
    if (!rb_storage_enabled()) return null;
    if ($client === null && !$tried) {
        $tried = true;
        $client = new S3Client([
            'version'                 => 'latest',
            'region'                  => 'auto',
            'endpoint'                => 'https://' . RB_R2_ACCOUNT_ID . '.r2.cloudflarestorage.com',
            'credentials'             => [
                'key'    => RB_R2_ACCESS_KEY,
                'secret' => RB_R2_SECRET_KEY,
            ],
            'use_path_style_endpoint' => true,
        ]);
    }
    return $client;
}

function rb_storage_local_path(string $key): string {
    return __DIR__ . '/../' . ltrim($key, '/');
}

/**
 * Save a local file (an $_FILES tmp upload, or any readable file path) under
 * $key. Returns true on success.
 */
function rb_storage_put_file(string $sourcePath, string $key): bool {
    $client = rb_storage_client();
    if ($client === null) {
        $abs = rb_storage_local_path($key);
        $dir = dirname($abs);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
        return is_uploaded_file($sourcePath)
            ? move_uploaded_file($sourcePath, $abs)
            : copy($sourcePath, $abs);
    }
    try {
        $client->putObject([
            'Bucket'     => RB_R2_BUCKET,
            'Key'        => $key,
            'SourceFile' => $sourcePath,
        ]);
        return true;
    } catch (AwsException $e) {
        error_log('R2 upload failed (' . $key . '): ' . $e->getMessage());
        return false;
    }
}

/** Save in-memory bytes (e.g. a generated PDF, a decoded signature PNG) under $key. */
function rb_storage_put_contents(string $contents, string $key): bool {
    $client = rb_storage_client();
    if ($client === null) {
        $abs = rb_storage_local_path($key);
        $dir = dirname($abs);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
        return file_put_contents($abs, $contents) !== false;
    }
    try {
        $client->putObject([
            'Bucket' => RB_R2_BUCKET,
            'Key'    => $key,
            'Body'   => $contents,
        ]);
        return true;
    } catch (AwsException $e) {
        error_log('R2 upload failed (' . $key . '): ' . $e->getMessage());
        return false;
    }
}

/** Read a stored file's bytes, or null if missing/unreadable. */
function rb_storage_get_contents(string $key): ?string {
    $client = rb_storage_client();
    if ($client === null) {
        $abs = rb_storage_local_path($key);
        return is_file($abs) ? file_get_contents($abs) : null;
    }
    try {
        $result = $client->getObject(['Bucket' => RB_R2_BUCKET, 'Key' => $key]);
        return (string)$result['Body'];
    } catch (AwsException $e) {
        return null;
    }
}

/** Whether a stored file exists. */
function rb_storage_exists(string $key): bool {
    $client = rb_storage_client();
    if ($client === null) {
        return is_file(rb_storage_local_path($key));
    }
    try {
        return $client->doesObjectExist(RB_R2_BUCKET, $key);
    } catch (AwsException $e) {
        return false;
    }
}

/** Delete a stored file. Safe to call on a file that doesn't exist. */
function rb_storage_delete(string $key): void {
    $client = rb_storage_client();
    if ($client === null) {
        $abs = rb_storage_local_path($key);
        if (is_file($abs)) @unlink($abs);
        return;
    }
    try {
        $client->deleteObject(['Bucket' => RB_R2_BUCKET, 'Key' => $key]);
    } catch (AwsException $e) {
        error_log('R2 delete failed (' . $key . '): ' . $e->getMessage());
    }
}

/** A data: URI for embedding a stored image directly in HTML (e.g. mPDF). */
function rb_storage_data_uri(string $key, string $mime = 'image/png'): ?string {
    $bytes = rb_storage_get_contents($key);
    if ($bytes === null) return null;
    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
}
