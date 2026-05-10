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