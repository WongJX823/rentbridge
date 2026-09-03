<?php
/**
 * PHPUnit bootstrap — builds a throwaway `dbrb_2026_test` database from the
 * schema + new migrations, then loads the real application code pointed at it.
 *
 * It NEVER touches the development database (dbrb_2026): the app reads
 * RB_DB_NAME (see config/database.php), which we set to dbrb_2026_test here.
 *
 * Requirements: the `mysql` client must be reachable. On XAMPP/Windows the
 * default C:\xampp\mysql\bin\mysql.exe is auto-detected; otherwise set RB_MYSQL.
 */

putenv('RB_DB_NAME=dbrb_2026_test');
$_ENV['RB_DB_NAME'] = 'dbrb_2026_test';

$root = dirname(__DIR__, 2); // project root

$host = getenv('RB_DB_HOST') ?: 'localhost';
$user = getenv('RB_DB_USER') ?: 'root';
$pass = getenv('RB_DB_PASS');
$pass = ($pass !== false) ? $pass : '';

$mysql = getenv('RB_MYSQL');
if (!$mysql) {
    // Derive XAMPP's mysql client from the project location (…/<xampp>/htdocs/rentbridge),
    // then fall back to the common Windows path, then to PATH.
    $candidates = [
        dirname($root, 2) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql.exe',
        'C:\\xampp\\mysql\\bin\\mysql.exe',
    ];
    $mysql = 'mysql';
    foreach ($candidates as $cand) {
        if (is_file($cand)) { $mysql = $cand; break; }
    }
}

$cred = '--host=' . escapeshellarg($host) . ' --user=' . escapeshellarg($user);
if ($pass !== '') {
    $cred .= ' --password=' . escapeshellarg($pass);
}

/** Run a shell command; abort the whole test run on failure. */
function rb_run(string $cmd): void {
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "\n[bootstrap] command failed (exit $code):\n$cmd\n"
            . implode("\n", $out) . "\n");
        exit(1);
    }
}

// 1. Drop + recreate the test database (via a temp .sql to avoid -e quoting).
$create = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rb_create_' . getmypid() . '.sql';
file_put_contents($create,
    "DROP DATABASE IF EXISTS dbrb_2026_test;\n" .
    "CREATE DATABASE dbrb_2026_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
rb_run(sprintf('"%s" %s < "%s"', $mysql, $cred, $create));
@unlink($create);

// 2. Import schema + seed — strip any USE / CREATE DATABASE lines so the import
//    can only ever land in dbrb_2026_test.
$schema = file_get_contents($root . '/db/dbrb_2026.sql');
$schema = preg_replace('/^\s*(USE|CREATE\s+DATABASE|DROP\s+DATABASE)\b.*$/mi', '', $schema);
$schemaTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rb_schema_' . getmypid() . '.sql';
file_put_contents($schemaTmp, $schema);
rb_run(sprintf('"%s" %s dbrb_2026_test < "%s"', $mysql, $cred, $schemaTmp));
@unlink($schemaTmp);

// 3. Apply the migrations that are NOT yet folded into dbrb_2026.sql.
foreach (['migrations/add_user_roles.sql', 'migrations/add_audit_log.sql',
          'migrations/add_academic_terms.sql', 'migrations/add_cotenant_sign_token.sql',
          'migrations/add_soft_delete_and_restrict_cascade.sql',
          'migrations/add_login_attempts.sql'] as $m) {
    $path = $root . '/' . $m;
    if (is_file($path)) {
        rb_run(sprintf('"%s" %s dbrb_2026_test < "%s"', $mysql, $cred, $path));
    }
}

// 4. Load the real application code (db() now targets dbrb_2026_test).
require $root . '/vendor/autoload.php';
require $root . '/includes/auth.php';      // defines db(), notify(), session/csrf helpers
require $root . '/includes/contracts.php'; // functions under test
