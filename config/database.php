<?php
/**
 * Database connection
 * This file is included anywhere we need to talk to MySQL.
 */

// Database credentials (XAMPP defaults).
// Each can be overridden by an environment variable so the automated test
// suite can point at a throwaway database (dbrb_2026_test) without touching
// development data. Defaults are unchanged for normal app use.
define('DB_HOST', getenv('RB_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('RB_DB_PORT') ?: '3306');
define('DB_NAME', getenv('RB_DB_NAME') ?: 'dbrb_2026');
define('DB_USER', getenv('RB_DB_USER') ?: 'root');
define('DB_PASS', getenv('RB_DB_PASS') !== false ? getenv('RB_DB_PASS') : '');
// Managed MySQL providers (e.g. Aiven) require TLS and hand you a CA cert.
// Set RB_DB_SSL_CA to the absolute path of that cert file on the server to
// enable it; left unset, the connection is made without TLS (fine for
// localhost/XAMPP and most shared hosts).
define('DB_SSL_CA', getenv('RB_DB_SSL_CA') ?: null);

/**
 * Returns a singleton PDO connection.
 * "Singleton" means we only create ONE connection per request,
 * even if many files call this function.
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            if (DB_SSL_CA !== null) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                $options
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            // 503 (not the default 200) so uptime monitors and browsers see
            // this as actually down, not a normal page — a DB outage
            // previously returned HTTP 200 on the "something went wrong"
            // text, which any status-code-based monitor would have missed.
            http_response_code(503);
            header('Retry-After: 60');
            // RB_DEBUG=1 shows the real error for local troubleshooting; production
            // (RB_DEBUG unset) shows a generic message instead of DB/host details.
            die(getenv('RB_DEBUG') ? 'Database connection failed: ' . $e->getMessage()
                                    : 'Sorry, something went wrong. Please try again later.');
        }
    }

    return $pdo;
}