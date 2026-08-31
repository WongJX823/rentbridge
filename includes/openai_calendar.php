<?php
/**
 * Academic-calendar extractor.
 *
 * Sends an academic-calendar PDF to a GPT-4o vision model via the OpenAI
 * Responses API and returns the long-semester (and short-semester) term dates
 * as structured data, ready for admin confirmation before saving to
 * `academic_terms`.
 *
 * The model output is NEVER written straight to the database — the admin
 * confirms/edits the extracted rows first.
 */

// config/openai.php is git-ignored (may hold a key). Load it if present, and
// fall back to environment variables / defaults so the app never fatals.
$rbOpenAiCfg = __DIR__ . '/../config/openai.php';
if (is_file($rbOpenAiCfg)) require_once $rbOpenAiCfg;
if (!defined('OPENAI_API_KEY')) define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
if (!defined('OPENAI_MODEL'))   define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o');

/**
 * Extract academic terms from a calendar PDF.
 *
 * @return array{
 *   ok: bool,
 *   terms: array<int,array{session:string,term:string,label:string,start_date:string,end_date:string}>,
 *   error: ?string,
 *   raw: ?string
 * }
 */
function openai_extract_calendar(string $pdfPath, bool $dryRun = false): array
{
    $fail = fn(string $msg) => ['ok' => false, 'terms' => [], 'error' => $msg, 'raw' => null];

    if (!is_file($pdfPath)) {
        return $fail("PDF not found: $pdfPath");
    }
    if (!$dryRun && OPENAI_API_KEY === '') {
        return $fail('OPENAI_API_KEY is not set. Add it to config/openai.php or the environment.');
    }

    $bytes = file_get_contents($pdfPath);
    if ($bytes === false || strlen($bytes) < 100) {
        return $fail('Could not read the PDF, or it is empty.');
    }
    // Guard against oversized uploads (base64 inflates ~33%).
    if (strlen($bytes) > 15 * 1024 * 1024) {
        return $fail('PDF is larger than 15 MB. Please upload a smaller file.');
    }

    $dataUrl = 'data:application/pdf;base64,' . base64_encode($bytes);

    $prompt = <<<TXT
You are extracting the official academic calendar for Universiti Teknikal
Malaysia Melaka (UTeM), diploma/bachelor programmes, from the attached PDF.

Identify the LONG SEMESTERS (usually "Semester 1" and "Semester 2") and any
SHORT / SPECIAL semester for the session shown. For each term, determine:
  - the session (e.g. "2025/2026"),
  - a term code: "sem1", "sem2", or "short",
  - a human label (e.g. "Semester 1"),
  - start_date: the FIRST day of the semester (start of lectures / registration
    week), in ISO format YYYY-MM-DD,
  - end_date: the LAST day of the semester (end of the final examination period),
    in ISO format YYYY-MM-DD.

Respond with ONLY a JSON object, no markdown, no commentary, in exactly this shape:
{"session":"2025/2026","terms":[
  {"term":"sem1","label":"Semester 1","start_date":"2025-10-06","end_date":"2026-02-20"},
  {"term":"sem2","label":"Semester 2","start_date":"2026-03-09","end_date":"2026-08-14"}
]}
If a value cannot be found, use an empty string for it. Do not invent dates.
TXT;

    $payload = [
        'model' => OPENAI_MODEL,
        'input' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'input_file', 'filename' => basename($pdfPath), 'file_data' => $dataUrl],
                ['type' => 'input_text', 'text' => $prompt],
            ],
        ]],
        'max_output_tokens' => 1500,
    ];

    if ($dryRun) {
        // Show the request WITHOUT the huge base64 payload, for inspection.
        $preview = $payload;
        $preview['input'][0]['content'][0]['file_data'] =
            '[data:application/pdf;base64, ' . strlen($bytes) . ' bytes omitted]';
        return [
            'ok' => true, 'terms' => [], 'error' => null,
            'raw' => json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    // ---- call OpenAI Responses API ----
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cErr = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return $fail("Network/cURL error: $cErr");
    }
    $json = json_decode($body, true);
    if ($http !== 200) {
        $apiMsg = $json['error']['message'] ?? substr((string)$body, 0, 300);
        return $fail("OpenAI API returned HTTP $http: $apiMsg");
    }

    // ---- pull the assistant text out of the Responses payload ----
    $text = $json['output_text'] ?? '';
    if ($text === '' && !empty($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $item) {
            foreach ($item['content'] ?? [] as $c) {
                if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
                    $text .= $c['text'];
                }
            }
        }
    }
    if (trim($text) === '') {
        return array_merge($fail('Model returned no text.'), ['raw' => substr($body, 0, 500)]);
    }

    // Strip markdown fences if the model added them, then decode the JSON.
    $clean = trim($text);
    $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
    $start = strpos($clean, '{');
    $end   = strrpos($clean, '}');
    if ($start !== false && $end !== false) {
        $clean = substr($clean, $start, $end - $start + 1);
    }
    $parsed = json_decode($clean, true);
    if (!is_array($parsed) || !isset($parsed['terms']) || !is_array($parsed['terms'])) {
        return ['ok' => false, 'terms' => [], 'error' => 'Could not parse JSON from the model.', 'raw' => $text];
    }

    // Normalise + light validation (dates that don't parse are flagged, not dropped).
    $session = (string)($parsed['session'] ?? '');
    $terms = [];
    foreach ($parsed['terms'] as $t) {
        $terms[] = [
            'session'    => (string)($t['session'] ?? $session),
            'term'       => (string)($t['term'] ?? ''),
            'label'      => (string)($t['label'] ?? ''),
            'start_date' => (string)($t['start_date'] ?? ''),
            'end_date'   => (string)($t['end_date'] ?? ''),
        ];
    }

    return ['ok' => true, 'terms' => $terms, 'error' => null, 'raw' => $text];
}
