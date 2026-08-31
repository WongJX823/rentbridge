<?php
/**
 * Manual test harness for the academic-calendar extractor.
 *
 *   php tests/manual/extract_calendar.php <path-to-calendar.pdf> [--dry-run]
 *
 * --dry-run  : build and print the request (base64 omitted) WITHOUT calling the
 *              API. Use it to sanity-check wiring with no key / no cost.
 *
 * A live run needs your OpenAI key in config/openai.php or the OPENAI_API_KEY
 * environment variable.
 */

require_once __DIR__ . '/../../includes/openai_calendar.php';

$pdf    = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

if ($pdf === '') {
    fwrite(STDERR, "Usage: php tests/manual/extract_calendar.php <calendar.pdf> [--dry-run]\n");
    exit(1);
}

echo "PDF   : $pdf\n";
echo "Model : " . OPENAI_MODEL . "\n";
echo "Key   : " . (OPENAI_API_KEY !== '' ? 'set (' . strlen(OPENAI_API_KEY) . " chars)" : 'NOT set') . "\n";
echo "Mode  : " . ($dryRun ? 'dry-run (no API call)' : 'live') . "\n";
echo str_repeat('-', 60) . "\n";

$res = openai_extract_calendar($pdf, $dryRun);

if (!$res['ok']) {
    echo "FAILED: {$res['error']}\n";
    if ($res['raw']) echo "\nRaw:\n{$res['raw']}\n";
    exit(1);
}

if ($dryRun) {
    echo "Request preview:\n{$res['raw']}\n";
    exit(0);
}

echo "Extracted terms:\n";
printf("  %-9s %-14s %-12s %-12s\n", 'term', 'label', 'start', 'end');
foreach ($res['terms'] as $t) {
    printf("  %-9s %-14s %-12s %-12s  [%s]\n",
        $t['term'], $t['label'], $t['start_date'], $t['end_date'], $t['session']);
}
echo "\nRaw model output:\n{$res['raw']}\n";
