<?php
/**
 * Google Maps helpers.
 *
 *   rb_map_picker($lat, $lng)  -> interactive Google map + hidden latitude/
 *                                 longitude inputs; click or drag to place the
 *                                 pin (needs a Google Maps API key). Falls back
 *                                 to manual lat/long inputs if no key is set.
 *   rb_map_view($lat, $lng)    -> keyless Google Maps embed with a pin +
 *                                 "Get directions" link.
 */

$rbGoogleCfg = __DIR__ . '/../config/google.php';
if (is_file($rbGoogleCfg)) require_once $rbGoogleCfg;
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

function rb_gmaps_key(): string { return (string)GOOGLE_MAPS_API_KEY; }

/**
 * Interactive location picker (Google Maps JS API). Renders a map and two
 * hidden inputs (name="latitude" / name="longitude"). Default centre is UTeM,
 * Melaka. Without an API key it degrades to manual latitude/longitude inputs.
 */
function rb_map_picker(?float $lat, ?float $lng, string $height = '320px'): void
{
    $has    = ($lat !== null && (float)$lat != 0.0 && $lng !== null && (float)$lng != 0.0);
    $latVal = $has ? number_format((float)$lat, 7, '.', '') : '';
    $lngVal = $has ? number_format((float)$lng, 7, '.', '') : '';
    $key    = rb_gmaps_key();

    // ---- No API key: manual entry fallback (keeps the form working) ----
    if ($key === '') {
        ?>
        <div class="alert alert-warning small mb-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Interactive map needs a Google Maps API key (set it in <code>config/google.php</code>).
            Meanwhile, enter the coordinates manually or copy them from
            <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a>
            (right-click a spot &rarr; the lat, long appears at the top).
        </div>
        <div class="row g-2">
            <div class="col"><input type="number" step="any" name="latitude"  class="form-control form-control-sm"
                   placeholder="Latitude"  value="<?= htmlspecialchars($latVal, ENT_QUOTES) ?>"></div>
            <div class="col"><input type="number" step="any" name="longitude" class="form-control form-control-sm"
                   placeholder="Longitude" value="<?= htmlspecialchars($lngVal, ENT_QUOTES) ?>"></div>
        </div>
        <?php
        return;
    }

    $id       = 'gmap_' . substr(md5(uniqid('', true)), 0, 6);
    $cb       = 'init_' . $id;
    $startLat = $has ? $latVal : '2.3138';
    $startLng = $has ? $lngVal : '102.3210';
    $hasJs    = $has ? 'true' : 'false';
    $src      = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($key)
              . '&callback=' . $cb . '&loading=async';
    ?>
    <div id="<?= $id ?>" style="height:<?= $height ?>;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;"></div>
    <input type="hidden" name="latitude"  id="<?= $id ?>_lat" value="<?= htmlspecialchars($latVal, ENT_QUOTES) ?>">
    <input type="hidden" name="longitude" id="<?= $id ?>_lng" value="<?= htmlspecialchars($lngVal, ENT_QUOTES) ?>">
    <div class="small text-secondary mt-1">
        <i class="bi bi-geo-alt"></i> Click the map to drop a pin, or drag it.
        <a href="#" id="<?= $id ?>_locate">Find my typed address on the map</a>.
        <span id="<?= $id ?>_coords" class="ms-1"></span>
    </div>
    <script>
    window['<?= $cb ?>'] = function () {
        var start = { lat: <?= $startLat ?>, lng: <?= $startLng ?> };
        var map = new google.maps.Map(document.getElementById('<?= $id ?>'), {
            center: start, zoom: <?= $has ? 16 : 13 ?>,
            mapTypeControl: false, streetViewControl: false, fullscreenControl: false
        });
        var marker = new google.maps.Marker({ position: start, map: map, draggable: true });
        var latI = document.getElementById('<?= $id ?>_lat');
        var lngI = document.getElementById('<?= $id ?>_lng');
        var out  = document.getElementById('<?= $id ?>_coords');
        function setLL(ll) {
            marker.setPosition(ll);
            latI.value = ll.lat().toFixed(7);
            lngI.value = ll.lng().toFixed(7);
            out.textContent = '(' + ll.lat().toFixed(5) + ', ' + ll.lng().toFixed(5) + ')';
        }
        if (<?= $hasJs ?>) setLL(new google.maps.LatLng(start.lat, start.lng));
        map.addListener('click', function (e) { setLL(e.latLng); });
        marker.addListener('dragend', function () { setLL(marker.getPosition()); });

        document.getElementById('<?= $id ?>_locate').addEventListener('click', function (ev) {
            ev.preventDefault();
            var parts = [];
            ['address', 'postcode', 'city', 'state'].forEach(function (n) {
                var el = document.querySelector('[name="' + n + '"]');
                if (el && el.value.trim()) parts.push(el.value.trim());
            });
            if (!parts.length) { alert('Please fill in the address first.'); return; }
            new google.maps.Geocoder().geocode({ address: parts.join(', ') + ', Malaysia' },
                function (results, status) {
                    if (status === 'OK' && results[0]) {
                        var loc = results[0].geometry.location;
                        map.setCenter(loc); map.setZoom(16); setLL(loc);
                    } else {
                        alert('Address not found automatically. Please place the pin manually.');
                    }
                });
        });
    };
    </script>
    <script async src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"></script>
    <?php
}

/** Read-only Google Maps embed (no API key) with a marker + directions link. */
function rb_map_view(?float $lat, ?float $lng, string $label = '', string $height = '300px'): void
{
    if ($lat === null || $lng === null || ((float)$lat == 0.0 && (float)$lng == 0.0)) {
        return; // no coordinates to show
    }
    $la = number_format((float)$lat, 7, '.', '');
    $ln = number_format((float)$lng, 7, '.', '');
    $q  = urlencode($la . ',' . $ln);
    ?>
    <iframe width="100%" height="<?= htmlspecialchars($height, ENT_QUOTES) ?>"
            style="border:0;border-radius:8px;" loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps?q=<?= $q ?>&z=16&output=embed"></iframe>
    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $la ?>,<?= $ln ?>"
       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="bi bi-signpost-2 me-1"></i> Get directions
    </a>
    <?php
}
