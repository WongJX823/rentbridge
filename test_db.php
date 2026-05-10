<?php
require_once 'config/database.php';

try {
    $pdo = db();
    echo "✅ Connected to dbrb_2026!<br><br>";

    // Check what tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "<br>";
    foreach ($tables as $t) {
        echo "&nbsp;&nbsp;• " . $t . "<br>";
    }

    echo "<br>";

    // Show users
    $stmt = $pdo->query("SELECT id, email, role, status FROM users");
    $users = $stmt->fetchAll();
    echo "Users: " . count($users) . "<br>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}