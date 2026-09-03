<?php
/**
 * Resolve a pasted Google Maps link to coordinates, so the add-property /
 * sign-up form can keep the map pin and the link in sync (last edit wins).
 * Handles full URLs directly and follows maps.app.goo.gl / goo.gl short links.
 * No login required (the landlord sign-up step 2 runs before the account exists).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing.php';

header('Content-Type: application/json');

$url    = trim($_GET['url'] ?? '');
$coords = $url !== '' ? extract_coords_from_maps_url($url) : null;

echo json_encode(
    $coords
        ? ['ok' => true, 'lat' => $coords['lat'], 'lng' => $coords['lng']]
        : ['ok' => false]
);
