<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/contracts.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Pass ?id=N');

$path = generate_contract_pdf($id);
echo $path ? "✅ Generated: $path" : "❌ Generation failed (check error log)";