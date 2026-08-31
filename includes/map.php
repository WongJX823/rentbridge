<?php
/**
 * Reusable Leaflet map helpers (OpenStreetMap tiles — no API key required).
 *
 *   rb_map_picker($lat, $lng)  -> interactive map + hidden latitude/longitude
 *                                 inputs; click or drag to place the pin.
 *   rb_map_view($lat, $lng)    -> read-only map with a marker + directions link.
 */

/** Print Leaflet CSS/JS from CDN once per request. */
function rb_map_assets(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" '
       . 'integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>' . "\n";
    echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" '
       . 'integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>' . "\n";
}

/**
 * Interactive location picker. Renders a map, two hidden inputs
 * (name="latitude" / name="longitude"), and a link that geocodes the typed
 * address via OpenStreetMap Nominatim. Default centre is UTeM, Melaka.
 */
function rb_map_picker(?float $lat, ?float $lng, string $height = '320px'): void
{
    rb_map_assets();
    $defLat = 2.3138; $defLng = 102.3210;                 // UTeM, Ayer Keroh
    $has    = ($lat !== null && (float)$lat != 0.0 && $lng !== null && (float)$lng != 0.0);
    $latVal = $has ? number_format((float)$lat, 7, '.', '') : '';
    $lngVal = $has ? number_format((float)$lng, 7, '.', '') : '';
    $id     = 'rbmap_' . substr(md5(uniqid('', true)), 0, 6);
    $startLat = $has ? $latVal : $defLat;
    $startLng = $has ? $lngVal : $defLng;
    $hasJs  = $has ? 'true' : 'false';
    ?>
    <div id="<?= $id ?>" style="height:<?= $height ?>;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;"></div>
    <input type="hidden" name="latitude"  id="<?= $id ?>_lat" value="<?= htmlspecialchars($latVal, ENT_QUOTES) ?>">
    <input type="hidden" name="longitude" id="<?= $id ?>_lng" value="<?= htmlspecialchars($lngVal, ENT_QUOTES) ?>">
    <div class="small text-secondary mt-1">
        <i class="bi bi-geo-alt"></i> Click the map to drop a pin, or drag it to fine-tune.
        <a href="#" id="<?= $id ?>_locate">Find my typed address on the map</a>.
        <span id="<?= $id ?>_coords" class="ms-1"></span>
    </div>
    <script>
    (function () {
        var startLat = <?= $startLat ?>, startLng = <?= $startLng ?>, has = <?= $hasJs ?>;
        var map = L.map('<?= $id ?>').setView([startLat, startLng], has ? 16 : 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        var marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
        var latI = document.getElementById('<?= $id ?>_lat');
        var lngI = document.getElementById('<?= $id ?>_lng');
        var out  = document.getElementById('<?= $id ?>_coords');
        function set(ll) {
            marker.setLatLng(ll);
            latI.value = ll.lat.toFixed(7);
            lngI.value = ll.lng.toFixed(7);
            out.textContent = '(' + ll.lat.toFixed(5) + ', ' + ll.lng.toFixed(5) + ')';
        }
        if (has) { set({ lat: startLat, lng: startLng }); }
        map.on('click', function (e) { set(e.latlng); });
        marker.on('dragend', function () { set(marker.getLatLng()); });

        var locate = document.getElementById('<?= $id ?>_locate');
        locate.addEventListener('click', function (ev) {
            ev.preventDefault();
            var parts = [];
            ['address', 'postcode', 'city', 'state'].forEach(function (n) {
                var el = document.querySelector('[name="' + n + '"]');
                if (el && el.value.trim()) parts.push(el.value.trim());
            });
            if (!parts.length) { alert('Please fill in the address first.'); return; }
            var q = parts.join(', ') + ', Malaysia';
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d[0]) { var p = { lat: parseFloat(d[0].lat), lng: parseFloat(d[0].lon) }; map.setView([p.lat, p.lng], 16); set(p); }
                    else { alert('Address not found automatically. Please place the pin manually on the map.'); }
                })
                .catch(function () { alert('Could not reach the map search service. Please place the pin manually.'); });
        });
        setTimeout(function () { map.invalidateSize(); }, 200);
    })();
    </script>
    <?php
}

/** Read-only map view with a marker and a "Get directions" link. */
function rb_map_view(?float $lat, ?float $lng, string $label = '', string $height = '300px'): void
{
    if ($lat === null || $lng === null || ((float)$lat == 0.0 && (float)$lng == 0.0)) {
        return; // no coordinates to show
    }
    rb_map_assets();
    $id  = 'rbview_' . substr(md5(uniqid('', true)), 0, 6);
    $la  = number_format((float)$lat, 7, '.', '');
    $ln  = number_format((float)$lng, 7, '.', '');
    $lbl = json_encode($label, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>
    <div id="<?= $id ?>" style="height:<?= $height ?>;border:1px solid #dee2e6;border-radius:8px;overflow:hidden;"></div>
    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $la ?>,<?= $ln ?>"
       target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="bi bi-signpost-2 me-1"></i> Get directions
    </a>
    <script>
    (function () {
        var map = L.map('<?= $id ?>', { scrollWheelZoom: false }).setView([<?= $la ?>, <?= $ln ?>], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        var m = L.marker([<?= $la ?>, <?= $ln ?>]).addTo(map);
        var lbl = <?= $lbl ?>;
        if (lbl) { m.bindPopup(lbl); }
        setTimeout(function () { map.invalidateSize(); }, 200);
    })();
    </script>
    <?php
}
