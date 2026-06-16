<?php
require_once __DIR__ . '/auth.php';

const AVATAR_DIR = __DIR__ . '/../uploads/avatars';
const AVATAR_MAX_SIZE = 5 * 1024 * 1024; // 5MB
const AVATAR_ALLOWED_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Save uploaded avatar file for a user.
 * Returns ['ok' => bool, 'path' => string|null, 'error' => string|null]
 */
function save_avatar(array $file, int $userId, string $role): array {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'error' => 'Upload failed'];
    }

    if ($file['size'] > AVATAR_MAX_SIZE) {
        return ['ok' => false, 'path' => null, 'error' => 'File too large (max 5MB)'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset(AVATAR_ALLOWED_MIMES[$mime])) {
        return ['ok' => false, 'path' => null, 'error' => 'Only JPG, PNG, WebP allowed'];
    }

    $ext = AVATAR_ALLOWED_MIMES[$mime];

    if (!is_dir(AVATAR_DIR)) {
        mkdir(AVATAR_DIR, 0755, true);
    }

    $filename = $role . '_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destAbs = AVATAR_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save file'];
    }

    // Path relative to project root, for use in <img src="">
    $relPath = 'uploads/avatars/' . $filename;

    // Update the role table
    $tableMap = [
        'student'  => 'students',
        'landlord' => 'landlords',
        'agent'    => 'agents',
    ];
    if (!isset($tableMap[$role])) {
        @unlink($destAbs);
        return ['ok' => false, 'path' => null, 'error' => 'Invalid role'];
    }

    $pdo = db();

    // Delete old avatar file (cleanup)
    $stmt = $pdo->prepare("SELECT avatar_path FROM {$tableMap[$role]} WHERE user_id = ?");
    $stmt->execute([$userId]);
    $oldPath = $stmt->fetchColumn();
    if ($oldPath) {
        $oldAbs = __DIR__ . '/../' . $oldPath;
        if (file_exists($oldAbs)) @unlink($oldAbs);
    }

    // Save new path
    $stmt = $pdo->prepare("UPDATE {$tableMap[$role]} SET avatar_path = ? WHERE user_id = ?");
    $stmt->execute([$relPath, $userId]);

    return ['ok' => true, 'path' => $relPath, 'error' => null];
}

/**
 * Get avatar path for a user, or null if none.
 */
function get_avatar_path(int $userId, string $role): ?string {
    $tableMap = [
        'student'  => 'students',
        'landlord' => 'landlords',
        'agent'    => 'agents',
    ];
    if (!isset($tableMap[$role])) return null;

    $pdo = db();
    $stmt = $pdo->prepare("SELECT avatar_path FROM {$tableMap[$role]} WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Render an avatar img or fallback initial circle.
 */
function render_avatar(?string $avatarPath, string $name, int $sizePx = 44, string $bgColor = '#E4F2EA'): void {
    if (!empty($avatarPath)) {
        ?>
        <img src="/rentbridge/<?= e($avatarPath) ?>"
             alt="<?= e($name) ?>"
             style="width:<?= $sizePx ?>px; height:<?= $sizePx ?>px;
                    border-radius:50%; object-fit:cover; flex-shrink:0;">
        <?php
    } else {
        $initial = mb_strtoupper(mb_substr($name, 0, 1));
        $fontSize = round($sizePx * 0.4);
        ?>
        <div style="width:<?= $sizePx ?>px; height:<?= $sizePx ?>px;
                    border-radius:50%; background: <?= $bgColor ?>;
                    display:inline-flex; align-items:center; justify-content:center;
                    font-weight:600; color:#0F2C52; font-size:<?= $fontSize ?>px;
                    flex-shrink:0;">
            <?= e($initial) ?>
        </div>
        <?php
    }
}