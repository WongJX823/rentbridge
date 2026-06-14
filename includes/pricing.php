<?php
require_once __DIR__ . '/auth.php';

// === Constants ===
const UTEM_LAT = 2.3138;
const UTEM_LNG = 102.3192;

// Amenity keywords and their RM premium (per month)
// Lowercased for matching
const AMENITY_PREMIUMS = [
    'wifi'            => 30,
    'internet'        => 30,
    'aircond'         => 60,
    'air-cond'        => 60,
    'air cond'        => 60,
    'ac'              => 60,
    'washing machine' => 40,
    'washer'          => 40,
    'kitchen'         => 50,
    'fridge'          => 30,
    'refrigerator'    => 30,
    'parking'         => 40,
    'garage'          => 50,
    'gym'             => 60,
    'pool'            => 80,
    'swimming pool'   => 80,
    'security'        => 30,
    'guarded'         => 40,
    'cctv'            => 20,
    'attached bath'   => 50,
    'private bath'    => 50,
    'balcony'         => 30,
    'garden'          => 20,
    'tv'              => 15,
];

/**
 * Haversine distance in kilometers.
 */
function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371; // earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
    return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Distance premium per month (RM).
 * Closer to UTeM = higher premium.
 *
 *   < 1 km    →  +RM 100  (walking distance)
 *   < 3 km    →  +RM 60   (short bike/grab)
 *   < 5 km    →  +RM 30   (still convenient)
 *   < 10 km   →    0      (baseline)
 *   >= 10 km  →  -RM 40   (less desirable)
 */
function distance_premium_rm(float $distanceKm): float {
    if ($distanceKm < 1)  return 100;
    if ($distanceKm < 3)  return 60;
    if ($distanceKm < 5)  return 30;
    if ($distanceKm < 10) return 0;
    return -40;
}

/**
 * Score amenities text and return premium in RM.
 * Returns ['premium' => float, 'matched' => array of matched amenity names]
 */
function score_amenities(?string $facilitiesText): array {
    if (empty($facilitiesText)) {
        return ['premium' => 0, 'matched' => []];
    }

    $text = mb_strtolower($facilitiesText);
    $premium = 0;
    $matched = [];
    $alreadyScored = [];

    foreach (AMENITY_PREMIUMS as $keyword => $amount) {
        if (strpos($text, $keyword) !== false) {
            // Avoid double-counting synonyms (wifi/internet, ac/aircond, etc.)
            $canonicalGroup = match (true) {
                in_array($keyword, ['wifi', 'internet'])                     => 'wifi',
                in_array($keyword, ['aircond', 'air-cond', 'air cond', 'ac']) => 'aircond',
                in_array($keyword, ['washing machine', 'washer'])             => 'washer',
                in_array($keyword, ['fridge', 'refrigerator'])                => 'fridge',
                in_array($keyword, ['parking', 'garage'])                     => 'parking',
                in_array($keyword, ['pool', 'swimming pool'])                 => 'pool',
                in_array($keyword, ['attached bath', 'private bath'])         => 'private_bath',
                default                                                        => $keyword,
            };
            if (in_array($canonicalGroup, $alreadyScored, true)) continue;
            $alreadyScored[] = $canonicalGroup;
            $premium += $amount;
            $matched[] = ucfirst($keyword);
        }
    }

    return ['premium' => $premium, 'matched' => $matched];
}

/**
 * Furnishing premium per month (RM).
 */
function furnishing_premium_rm(string $furnishing): float {
    return match ($furnishing) {
        'full'    => 120,
        'partial' => 0,    // baseline (most common)
        'none'    => -60,  // unfurnished discount
        default   => 0,
    };
}

/**
 * Extract lat/lng from a Google Maps URL.
 * Handles common formats:
 *   - https://www.google.com/maps/@2.3138,102.3192,17z
 *   - https://maps.app.goo.gl/abc123  (short URLs — can't extract, returns null)
 *   - https://www.google.com/maps/place/.../@2.3138,102.3192,17z
 *   - https://goo.gl/maps/...
 *   - URLs with "q=2.3138,102.3192"
 *
 * Returns ['lat' => float, 'lng' => float] or null if extraction failed.
 */
function extract_coords_from_maps_url(string $url): ?array {
    if (trim($url) === '') return null;

    // Try direct extraction first (full URLs work immediately)
    $coords = parse_coords_from_string($url);
    if ($coords !== null) return $coords;

    // Short URL? Follow the redirect and try again
    if (preg_match('#^https?://(?:maps\.app\.goo\.gl|goo\.gl/maps)/#', $url)) {
        $finalUrl = follow_redirect($url);
        if ($finalUrl !== null) {
            return parse_coords_from_string($finalUrl);
        }
    }

    return null;
}

/**
 * Extract lat/lng from a string containing them.
 */
function parse_coords_from_string(string $s): ?array {
    // Pattern 1: @lat,lng
    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $s, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    // Pattern 2: q=lat,lng or ll=lat,lng
    if (preg_match('/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $s, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    // Pattern 3: !3d{lat}!4d{lng} (some Google Maps share URL format)
    if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $s, $m)) {
        return ['lat' => (float)$m[1], 'lng' => (float)$m[2]];
    }
    return null;
}

/**
 * Follow HTTP redirects for a short URL and return the final URL.
 * Returns null on failure or timeout.
 */
function follow_redirect(string $url): ?string {
    if (!function_exists('curl_init')) {
        return null; // cURL not available
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RentBridge/1.0)',
        CURLOPT_SSL_VERIFYPEER => false, // dev env
    ]);
    curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }

    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return $finalUrl ?: null;
}

/**
 * Main pricing helper.
 *
 * Returns:
 *   [
 *     'base_market'      => float,  // median rent for similar listings
 *     'distance_premium' => float,
 *     'distance_km'      => float|null,
 *     'amenity_premium'  => float,
 *     'amenities_matched'=> array,
 *     'furnishing_premium' => float,
 *     'suggested'        => float,  // sum
 *     'count'            => int,    // sample size for market base
 *     'min'              => float,
 *     'max'              => float,
 *     'confidence'       => string,
 *     'match_tier'       => string,
 *     'has_data'         => bool,
 *   ]
 */
function get_pricing_benchmark(
    string $city,
    string $propertyType,
    string $furnishing,
    ?float $lat = null,
    ?float $lng = null,
    ?string $facilities = null
): array {
    $pdo = db();

    $city = trim($city);
    $result = [
        'base_market'        => 0,
        'distance_premium'   => 0,
        'distance_km'        => null,
        'amenity_premium'    => 0,
        'amenities_matched'  => [],
        'furnishing_premium' => 0,
        'suggested'          => 0,
        'count'              => 0,
        'min'                => 0,
        'max'                => 0,
        'confidence'         => 'low',
        'match_tier'         => '',
        'has_data'           => false,
    ];

    // If no city — skip the market base, but still compute premiums
    $skipMarketBase = ($city === '');

    // Tiered comparable search
    $tiers = [
        ['type' => true,  'furn' => true,  'label' => 'exact'],
        ['type' => true,  'furn' => false, 'label' => 'same type'],
        ['type' => false, 'furn' => false, 'label' => 'same city'],
    ];

if (!$skipMarketBase) {
        foreach ($tiers as $tier) {
            $where = ["status IN ('available','booked','rented')", "city = ?"];
            $params = [$city];

            if ($tier['type']) { $where[] = "property_type = ?"; $params[] = $propertyType; }
            if ($tier['furn']) { $where[] = "furnishing = ?"; $params[] = $furnishing; }

            $whereClause = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT monthly_rent FROM properties WHERE $whereClause
            ");
            $stmt->execute($params);
            $rents = array_column($stmt->fetchAll(), 'monthly_rent');
            $count = count($rents);

            if ($count < 3) continue;

            $floatRents = array_map('floatval', $rents);
            $median = compute_median($floatRents);
            $min = min($floatRents);
            $max = max($floatRents);

            $confidence = 'low';
            if ($count >= 10 && $tier['label'] === 'exact') $confidence = 'high';
            elseif ($count >= 5) $confidence = 'medium';

            $result['base_market'] = $median;
            $result['count'] = $count;
            $result['min'] = $min;
            $result['max'] = $max;
            $result['confidence'] = $confidence;
            $result['match_tier'] = $tier['label'];
            $result['has_data'] = true;
            break;
        }
    }

    
// Distance premium
    if ($lat !== null && $lng !== null) {
        $distanceKm = haversine_km($lat, $lng, UTEM_LAT, UTEM_LNG);
        $result['distance_km'] = round($distanceKm, 2);
        $result['distance_premium'] = distance_premium_rm($distanceKm);
    }

    // Amenity premium
    $amenityScore = score_amenities($facilities);
    $result['amenity_premium'] = $amenityScore['premium'];
    $result['amenities_matched'] = $amenityScore['matched'];

    // Furnishing premium
    if (!$result['has_data'] || $result['match_tier'] !== 'exact') {
        $result['furnishing_premium'] = furnishing_premium_rm($furnishing);
    }

    // Suggested = base + premiums (base could be 0 if no comparables)
    $result['suggested'] = $result['base_market']
                         + $result['distance_premium']
                         + $result['amenity_premium']
                         + $result['furnishing_premium'];

    // Mark "partial preview" if no market base but we have premium contributions
    $result['partial_preview'] = (!$result['has_data'] &&
        ($result['distance_premium'] != 0 ||
         $result['amenity_premium'] > 0 ||
         $result['furnishing_premium'] > 0));

    return $result;
}

function compute_median(array $values): float {
    sort($values);
    $n = count($values);
    if ($n === 0) return 0;
    if ($n % 2 === 1) return $values[(int)floor($n / 2)];
    return ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
}