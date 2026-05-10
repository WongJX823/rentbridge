<?php
/**
 * Database connection
 * This file is included anywhere we need to talk to MySQL.
 */

// Database credentials (XAMPP defaults)
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbrb_2026');
define('DB_USER', 'root');
define('DB_PASS', '');

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
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}