<?php
/**
 * RentBridge — run all automated test suites with one command.
 *
 *   php tests/run_all.php [flags]
 *
 * Flags:
 *   --unit-only    only the PHPUnit backend suite
 *   --e2e-only     only the Playwright E2E suite
 *   --skip-unit    skip PHPUnit
 *   --skip-e2e     skip Playwright
 *   --skip-smoke   skip the extractor dry-run smoke test
 *
 * It preflight-checks prerequisites and SKIPS (not fails) any suite whose
 * requirements are missing, so the output makes the gap obvious. Exit code is
 * 0 only if every suite that ran passed (usable in CI).
 */

$root = dirname(__DIR__);
chdir($root);

$args     = array_slice($argv, 1);
$has      = fn(string $f): bool => in_array($f, $args, true);
$unitOnly = $has('--unit-only');
$e2eOnly  = $has('--e2e-only');
$runUnit  = !$e2eOnly  && !$has('--skip-unit');
$runE2e   = !$unitOnly && !$has('--skip-e2e');
$runSmoke = !$unitOnly && !$e2eOnly && !$has('--skip-smoke');

function hr(): void { echo str_repeat('=', 66) . "\n"; }

function run_suite(string $label, string $cmd): array {
    echo "\n"; hr(); echo ">> $label\n   $cmd\n"; hr();
    $start = microtime(true);
    passthru($cmd, $code);
    return ['label' => $label, 'status' => $code === 0 ? 'PASS' : 'FAIL',
            'dur' => round(microtime(true) - $start, 1)];
}
function skip(string $label, string $why): array {
    echo "\n[SKIP] $label — $why\n";
    return ['label' => $label, 'status' => 'SKIP', 'dur' => 0.0, 'why' => $why];
}

/* ---- preflight ---------------------------------------------------------- */
function check_mysql(): bool {
    try {
        new PDO('mysql:host=' . (getenv('RB_DB_HOST') ?: 'localhost'),
                getenv('RB_DB_USER') ?: 'root',
                getenv('RB_DB_PASS') !== false ? getenv('RB_DB_PASS') : '',
                [PDO::ATTR_TIMEOUT => 3]);
        return true;
    } catch (Throwable $e) { return false; }
}
function check_url(string $url): ?bool {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 4,
                            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true]);
    curl_exec($ch);
    $ok = curl_errno($ch) === 0;
    curl_close($ch);
    return $ok;
}

$appUrl      = 'http://localhost/rentbridge';
$mysqlOk     = check_mysql();
$phpunitOk   = is_file('vendor/phpunit/phpunit/phpunit');
$pwInstalled = is_dir('node_modules/@playwright/test')
            || is_file('node_modules/.bin/playwright')
            || is_file('node_modules/.bin/playwright.cmd');
$appOk       = check_url($appUrl);

$mark = fn(?bool $b, string $bad): string => $b === null ? 'unknown' : ($b ? 'OK' : $bad);

echo "RentBridge — automated test runner\n";
hr();
printf("  %-24s %s\n", 'MySQL server',        $mark($mysqlOk,     'NOT reachable  (unit tests need it)'));
printf("  %-24s %s\n", 'PHPUnit installed',   $mark($phpunitOk,   'missing  (run: composer install)'));
printf("  %-24s %s\n", 'Playwright installed',$mark($pwInstalled, 'missing  (run: npm install && npx playwright install)'));
printf("  %-24s %s\n", 'App @ localhost',     $mark($appOk,       'NOT reachable  (start Apache; E2E needs it)'));
hr();

$results = [];

/* ---- 1. extractor dry-run smoke (no API key / no cost) ------------------ */
if ($runSmoke) {
    $pdf = null;
    foreach (['uploads/contracts/*.pdf', 'uploads/generated_contracts/*.pdf',
              '.playwright-mcp/*.pdf', '*.pdf'] as $g) {
        $found = glob($root . '/' . $g);
        if ($found) { $pdf = $found[0]; break; }
    }
    if ($pdf) {
        $results[] = run_suite('Extractor smoke (dry-run)',
            'php ' . escapeshellarg('tests/manual/extract_calendar.php') . ' ' .
            escapeshellarg($pdf) . ' --dry-run');
    } else {
        $results[] = skip('Extractor smoke (dry-run)', 'no PDF found to dry-run against');
    }
}

/* ---- 2. PHPUnit backend ------------------------------------------------- */
if ($runUnit) {
    if (!$phpunitOk)      $results[] = skip('PHPUnit backend', 'PHPUnit not installed (composer install)');
    elseif (!$mysqlOk)    $results[] = skip('PHPUnit backend', 'MySQL not reachable — cannot build test DB');
    else                  $results[] = run_suite('PHPUnit backend',
                              'php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml');
}

/* ---- 3. Playwright E2E -------------------------------------------------- */
if ($runE2e) {
    if (!$pwInstalled)    $results[] = skip('Playwright E2E', 'Playwright not installed (npm install && npx playwright install)');
    elseif ($appOk === false) $results[] = skip('Playwright E2E', "app not reachable at $appUrl (start Apache)");
    else                  $results[] = run_suite('Playwright E2E', 'npx playwright test');
}

/* ---- summary ------------------------------------------------------------ */
echo "\n"; hr(); echo "SUMMARY\n"; hr();
$fail = 0; $ran = 0;
foreach ($results as $r) {
    printf("  [%-4s] %-30s %s\n", $r['status'], $r['label'],
        !empty($r['dur']) ? '(' . $r['dur'] . 's)' : ($r['status'] === 'SKIP' ? '- ' . ($r['why'] ?? '') : ''));
    if ($r['status'] === 'FAIL') $fail++;
    if ($r['status'] !== 'SKIP') $ran++;
}
hr();
if ($fail > 0)   { echo "RESULT: FAIL — $fail suite(s) failed\n"; exit(1); }
if ($ran === 0)  { echo "RESULT: nothing ran — check the prerequisites above\n"; exit(2); }
echo "RESULT: PASS — all $ran suite(s) passed\n";
exit(0);
