<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing.php';
require_role('landlord');

header('Content-Type: application/json');

$city = trim($_GET['city'] ?? '');
$type = trim($_GET['type'] ?? 'room');
$furnishing = trim($_GET['furnishing'] ?? 'partial');
$facilities = trim($_GET['facilities'] ?? '');
$mapsUrl = trim($_GET['maps_url'] ?? '');

$validTypes = ['room', 'studio', 'whole_unit'];
$validFurnish = ['none', 'partial', 'full'];
if (!in_array($type, $validTypes, true)) $type = 'room';
if (!in_array($furnishing, $validFurnish, true)) $furnishing = 'partial';

$lat = $lng = null;
if ($mapsUrl !== '') {
    $coords = extract_coords_from_maps_url($mapsUrl);
    if ($coords) {
        $lat = $coords['lat'];
        $lng = $coords['lng'];
    }
}

$result = get_pricing_benchmark($city, $type, $furnishing, $lat, $lng, $facilities);
$result['coords_extracted'] = ($lat !== null);
echo json_encode($result);