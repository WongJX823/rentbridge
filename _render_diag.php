<?php
// Temporary diagnostic page for the Render + Aiven SSL connection issue.
// Reveals no secret VALUES — only whether vars are set, and the CA file's
// first line (a public cert marker, not sensitive). Delete once diagnosed.
header('Content-Type: text/plain');

$vars = ['RB_DB_HOST', 'RB_DB_PORT', 'RB_DB_NAME', 'RB_DB_USER', 'RB_DB_PASS', 'RB_DB_SSL_CA', 'RB_BASE_PATH'];
foreach ($vars as $v) {
    $val = getenv($v);
    echo "$v: " . ($val === false ? "NOT SET" : ($v === 'RB_DB_PASS' ? '(set, ' . strlen($val) . ' chars)' : $val)) . "\n";
}

echo "\n--- CA cert file check ---\n";
echo "running as: " . get_current_user() . " (uid=" . getmyuid() . ")\n";
$ca = getenv('RB_DB_SSL_CA');
if ($ca === false) {
    echo "RB_DB_SSL_CA not set, nothing to check\n";
} elseif (!file_exists($ca)) {
    echo "$ca : DOES NOT EXIST\n";
} else {
    echo "$ca : exists, size=" . filesize($ca) . " bytes, perms=" . substr(sprintf('%o', fileperms($ca)), -4) . "\n";
    echo "is_readable: " . (is_readable($ca) ? 'yes' : 'NO') . "\n";
    $contents = @file_get_contents($ca);
    if ($contents === false) {
        $err = error_get_last();
        echo "file_get_contents FAILED: " . ($err['message'] ?? 'unknown error') . "\n";
    } else {
        $firstLine = strtok($contents, "\n");
        echo "first line: " . trim($firstLine) . "\n";
    }
}

echo "\n--- raw PDO connect attempt ---\n";
try {
    $host = getenv('RB_DB_HOST');
    $port = getenv('RB_DB_PORT') ?: '3306';
    $name = getenv('RB_DB_NAME');
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8];
    if ($ca !== false && file_exists($ca)) {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('RB_DB_USER'), getenv('RB_DB_PASS'), $opts);
    echo "CONNECTED OK: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . get_class($e) . ": " . $e->getMessage() . "\n";
}
