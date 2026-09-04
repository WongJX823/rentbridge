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
define('DB_NAME', getenv('RB_DB_NAME') ?: 'dbrb_2026');
define('DB_USER', getenv('RB_DB_USER') ?: 'root');
define('DB_PASS', getenv('RB_DB_PASS') !== false ? getenv('RB_DB_PASS') : '');

/**
 * Returns a singleton PDO connection.
 * "Singleton" means we only create ONE connection per request,
 * even if many files call this function.
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            // RB_DEBUG=1 shows the real error for local troubleshooting; production
            // (RB_DEBUG unset) shows a generic message instead of DB/host details.
            die(getenv('RB_DEBUG') ? 'Database connection failed: ' . $e->getMessage()
                                    : 'Sorry, something went wrong. Please try again later.');
        }
    }

    return $pdo;
}