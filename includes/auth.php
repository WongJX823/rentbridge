<?php
require_once __DIR__ . '/../config/database.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
 *  CSRF protection
 * ============================================================ */

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        http_response_code(419);
        die('CSRF token mismatch. Please refresh and try again.');
    }
}

/* ============================================================
 *  Output escaping (prevents XSS)
 * ============================================================ */

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* ============================================================
 *  Flash messages (one-time messages stored in session)
 * ============================================================ */

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

/* ============================================================
 *  Login / logout helpers
 * ============================================================ */

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function login_user(array $user): void {
    // Regenerate session ID on login (security: prevents session fixation)
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['primary_role'];
}

function logout_user(): void {
    // Wipe session data
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

function dashboard_url_for(string $role): string {
    return '/rentbridge/' . $role . '/dashboard.php';
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /rentbridge/auth/login.php');
        exit;
    }
}

function require_role(string $role): void {
    require_login();
    if (current_role() !== $role) {
        http_response_code(403);
        die('Access denied — wrong role for this page.');
    }
}

/* ============================================================
 *  Password validation (NIST SP 800-63B-aligned hybrid policy)
 * ============================================================ */

/**
 * Top 50 most common leaked passwords (and obvious patterns).
 * Source: HaveIBeenPwned + Top 100 worst passwords lists, condensed.
 * Rejected outright regardless of length/composition.
 */
const COMMON_PASSWORDS = [
    'password', 'password1', 'password123', 'password!', 'p@ssw0rd',
    '12345678', '123456789', '1234567890', 'qwerty123', 'qwertyuiop',
    'abc12345', 'iloveyou', 'admin123', 'admin@123', 'letmein123',
    'welcome1', 'welcome123', 'monkey123', 'football1', 'baseball1',
    'sunshine1', 'princess1', 'dragon123', 'master123', 'superman1',
    'batman123', 'trustno1', 'shadow123', 'qwerty1234', 'asdfghjkl',
    'zaq12wsx', '1qaz2wsx', 'q1w2e3r4t5', 'passw0rd', 'p@ssword1',
    'welcome2024', 'welcome2025', 'welcome2026', 'student123', 'utem1234',
    'rentbridge1', 'rentbridge!', 'changeme1', 'temppass1', 'helloworld1',
    'abcd1234', 'abcd@1234', 'abcdefgh', '11111111', '00000000',
];

/**
 * Validate a password against the hybrid policy.
 *
 * Rules:
 *   1. Minimum 8 characters
 *   2. Must contain at least 3 of these 4 character classes:
 *      - lowercase letter
 *      - uppercase letter
 *      - digit
 *      - symbol
 *   3. Not in the common-password blacklist
 *
 * Returns NULL if valid, or an error message string if not.
 */
function validate_password(string $password): ?string {
    // Rule 1: Length
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    // Rule 3: Common-password blacklist (case-insensitive)
    if (in_array(strtolower($password), COMMON_PASSWORDS, true)) {
        return 'This password is too common. Please choose a stronger one.';
    }

    // Rule 2: Character class diversity
    $classes = 0;
    if (preg_match('/[a-z]/', $password))                $classes++;
    if (preg_match('/[A-Z]/', $password))                $classes++;
    if (preg_match('/[0-9]/', $password))                $classes++;
    if (preg_match('/[^a-zA-Z0-9]/', $password))         $classes++;

    if ($classes < 3) {
        return 'Password must include at least 3 of: lowercase, UPPERCASE, numbers, symbols.';
    }

    return null;  // valid
}

/**
 * Calculate password strength score 0–100 (for live meter on the form).
 */
function password_strength_score(string $password): int {
    $score = 0;
    $len   = strlen($password);

    // Length contribution (max 40 points)
    if ($len >= 8)  $score += 15;
    if ($len >= 12) $score += 15;
    if ($len >= 16) $score += 10;

    // Character classes (max 40 points)
    if (preg_match('/[a-z]/', $password))         $score += 10;
    if (preg_match('/[A-Z]/', $password))         $score += 10;
    if (preg_match('/[0-9]/', $password))         $score += 10;
    if (preg_match('/[^a-zA-Z0-9]/', $password))  $score += 10;

    // No repeated characters (max 10)
    if (!preg_match('/(.)\1{2,}/', $password))    $score += 10;

    // Not in blacklist (max 10)
    if (!in_array(strtolower($password), COMMON_PASSWORDS, true)) $score += 10;

    return min($score, 100);
}
/* ============================================================
 *  Notifications (dashboard banner; email channel coming later)
 * ============================================================ */

/**
 * Create a notification for a user.
 * Currently writes to the database only.
 * In a later module we'll add an email channel here.
 */
function notify(int $userId, string $type, string $title, string $message = '', ?string $linkUrl = null): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link_url)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $title, $message, $linkUrl]);
    } catch (Throwable $e) {
        // Notifications failing should NEVER break the calling action.
        // Log silently in a future module.
    }
}

/**
 * Count unread notifications (for the badge in the navbar).
 */
function unread_notifications_count(int $userId): int {
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Get the current user's preferred "first name" for navbar display.
 *
 * Heuristics for Malaysian context:
 *   1. If name contains "Bin"/"Binti"/"A/L"/"A/P" → take last word BEFORE that connector
 *      ("Muhammad Ali Bin Abdullah" → "Ali")
 *   2. If 3+ words and no connector → take last 2 words
 *      ("Wong Jia Xi" → "Jia Xi" — Chinese convention)
 *   3. If 2 words → take first word
 *      ("John Smith" → "John" — Western convention)
 *   4. If 1 word → as-is
 *      ("Sarah" → "Sarah")
 *   5. Email prefix fallback for admin / missing profile.
 */
function current_user_display_name(): string {
    $userId = current_user_id();
    $role   = current_role();
    if (!$userId || !$role) return 'Guest';

    static $cache = null;
    if ($cache !== null) return $cache;

    $pdo = db();
    $name = null;

    try {
        if (in_array($role, ['student', 'landlord', 'agent'], true)) {
            $table = $role . 's';
            $stmt  = $pdo->prepare("SELECT full_name FROM $table WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $name = $stmt->fetchColumn() ?: null;
        }

        if (!$name) {
            $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $email = $stmt->fetchColumn();
            $name  = $email ? explode('@', $email)[0] : 'User';
        }
    } catch (Throwable $e) {
        $name = 'User';
    }

    $cache = derive_first_name(trim($name));
    return $cache;
}

/**
 * Pure function to derive a display-friendly first name from a full name.
 * Pure = no DB, no globals — easy to unit-test.
 */
function derive_first_name(string $fullName): string {
    if ($fullName === '') return 'User';

    // Split into words
    $words = preg_split('/\s+/', $fullName);
    $count = count($words);

    if ($count === 1) return $words[0];

    // Look for Malay connector words: Bin / Binti / A/L / A/P
    $connectors = ['bin', 'binti', 'a/l', 'a/p', 's/o', 'd/o'];
    foreach ($words as $i => $w) {
        if (in_array(strtolower(rtrim($w, '.')), $connectors, true)) {
            // Take the word(s) just before the connector
            if ($i === 1) return $words[0];               // "Ali Bin X" → "Ali"
            if ($i >= 2)  return $words[$i - 1];          // "Muhammad Ali Bin X" → "Ali"
        }
    }

    // No connector — apply length heuristic
    if ($count === 2) {
        // 2 words: assume Western "First Last" → take first
        return $words[0];
    }

    // 3+ words: assume Chinese "Surname Given Given" → take last 2
    return $words[$count - 2] . ' ' . $words[$count - 1];
}

/**
 * Build a wa.me URL for opening WhatsApp with a pre-filled message.
 *
 * @param string $whatsappNo  Malaysian phone (with or without country code)
 * @param string $message     Message to pre-fill (will be URL-encoded)
 * @return string             Full https://wa.me/... URL
 */
function whatsapp_link(string $whatsappNo, string $message = ''): string {
    // Strip non-digit characters
    $digits = preg_replace('/\D/', '', $whatsappNo);

    // Malaysian numbers: add country code 60 if not present
    // Common formats: "0123456789" → "60123456789"
    //                 "60123456789" → "60123456789"
    //                 "+60123456789" → "60123456789"
    if (strlen($digits) > 0 && $digits[0] === '0') {
        $digits = '60' . substr($digits, 1);
    }

    $url = 'https://wa.me/' . $digits;
    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}