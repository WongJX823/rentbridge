<?php
/**
 * Google Maps helpers for RentBridge.
 *
 * Landlord pin-pinpoint flow (add-property + landlord sign-up step 2), matching
 * the "Landlord Map Pinpoint" wireframe — pin-first, map collapsed by default:
 *
 *   rb_address_pin_field($address, $lat, $lng, $error)
 *        -> the "Full street address" field with a pin icon button tucked into
 *           it. Nothing is shown inline; tapping the pin opens a drawer. Once a
 *           pin exists, a green "Location pinned" row appears. Emits the hidden
 *           name="latitude"/name="longitude" inputs. Degrades to manual lat/lng
 *           number inputs when no Google Maps API key is configured.
 *   rb_maps_link_field($mapsUrl)
 *        -> the optional "or paste a Google Maps link" row, collapsed by default
 *           (auto-expanded when a link is already present).
 *   rb_map_pinpoint_assets()
 *        -> the "Pin your property" drawer markup + CSS + JS. Call ONCE per page,
 *           after the form fields. No-op without an API key.
 *
 * Read-only display (property pages):
 *   rb_map_view($lat, $lng)  -> keyless Google Maps embed + "Get directions".
 */

$rbGoogleCfg = __DIR__ . '/../config/google.php';
if (is_file($rbGoogleCfg)) require_once $rbGoogleCfg;
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

function rb_gmaps_key(): string { return (string)GOOGLE_MAPS_API_KEY; }

/** Normalise a lat/lng pair to floats, or [null, null] when unset/zero. */
function rb_coords(?float $lat, ?float $lng): array
{
    $has = ($lat !== null && (float)$lat != 0.0 && $lng !== null && (float)$lng != 0.0);
    return $has ? [(float)$lat, (float)$lng] : [null, null];
}

/**
 * "Full street address" field with the map-pin entry point (wireframe 1a / 1d).
 * Renders the label, the address textarea with an embedded pin button, the
 * pinned-confirmation row, and the hidden latitude/longitude inputs.
 *
 * Without an API key it keeps the form working by degrading to the plain
 * textarea plus manual latitude/longitude number inputs.
 */
function rb_address_pin_field(string $address, ?float $lat, ?float $lng, ?string $error = null): void
{
    [$la, $ln] = rb_coords($lat, $lng);
    $has    = ($la !== null);
    $latVal = $has ? number_format($la, 7, '.', '') : '';
    $lngVal = $has ? number_format($ln, 7, '.', '') : '';
    $coordsLabel = $has ? number_format($la, 5, '.', '') . ', ' . number_format($ln, 5, '.', '') : '';
    $invalid = $error ? ' is-invalid' : '';
    $keyed   = rb_gmaps_key() !== '';
    ?>
    <div class="mb-3">
        <label class="form-label fw-semibold" for="addressInput">
            Full street address <small class="text-danger">*</small>
        </label>

        <div class="rb-pin-address<?= $keyed ? '' : ' rb-pin-nokey' ?>">
            <textarea name="address" id="addressInput" rows="2"
                      class="form-control<?= $invalid ?>"
                      placeholder="No 23, Jalan Sutera 5, Taman Sutera" required><?= e($address) ?></textarea>
            <?php if ($keyed): ?>
                <button type="button" id="rbPinBtn"
                        class="rb-pin-btn<?= $has ? ' is-pinned' : '' ?>"
                        title="<?= $has ? 'Edit pin' : 'Pin on map' ?>"
                        aria-label="Pin your property on the map">
                    <i class="bi <?= $has ? 'bi-geo-alt-fill' : 'bi-geo-alt' ?>"></i>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="invalid-feedback d-block"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($keyed): ?>
            <!-- coordinates supplied by the pin drawer -->
            <input type="hidden" name="latitude"  id="rbLat" value="<?= e($latVal) ?>">
            <input type="hidden" name="longitude" id="rbLng" value="<?= e($lngVal) ?>">

            <div id="rbPinHint" class="rb-pin-hint text-secondary<?= $has ? ' d-none' : '' ?>">
                <i class="bi bi-info-circle"></i>
                Tap the pin icon to place your property on the map — students see this location.
            </div>
            <div id="rbPinnedRow" class="rb-pinned-row<?= $has ? '' : ' d-none' ?>">
                <div class="rb-pinned-tag">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Location pinned</span>
                    <span id="rbPinnedCoords" class="rb-pinned-coords"><?= e($coordsLabel) ?></span>
                </div>
                <a href="#" id="rbEditPin" class="rb-pinned-edit">Edit pin</a>
            </div>
            <div id="rbPinnedNote" class="rb-pin-hint text-secondary<?= $has ? '' : ' d-none' ?>">
                Your assigned agent confirms this pin during inspection.
            </div>
        <?php else: ?>
            <div class="alert alert-warning small mt-2 mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                The interactive map needs a Google Maps API key (set it in <code>config/google.php</code>).
                Meanwhile, enter coordinates manually or copy them from
                <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a>
                (right-click a spot → the lat, long appears at the top).
            </div>
            <div class="row g-2">
                <div class="col"><input type="number" step="any" name="latitude"
                       class="form-control form-control-sm" placeholder="Latitude"
                       value="<?= e($latVal) ?>"></div>
                <div class="col"><input type="number" step="any" name="longitude"
                       class="form-control form-control-sm" placeholder="Longitude"
                       value="<?= e($lngVal) ?>"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Optional "or paste a Google Maps link" row (wireframe 1a collapsed / 1d open).
 * Collapsed by default; auto-expands when a link is already saved.
 */
function rb_maps_link_field(string $mapsUrl): void
{
    $open = trim($mapsUrl) !== '';
    ?>
    <div class="col-12">
        <div class="rb-maplink">
            <button type="button" class="rb-maplink-toggle" id="rbMapLinkToggle"
                    aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="rbMapLinkBody">
                <i class="bi bi-chevron-<?= $open ? 'down' : 'right' ?>" id="rbMapLinkChevron"></i>
                or paste a Google Maps link
                <span class="rb-maplink-note">— optional, helps pricing accuracy</span>
            </button>
            <div class="rb-maplink-body<?= $open ? '' : ' d-none' ?>" id="rbMapLinkBody">
                <input type="url" name="maps_url" id="mapsUrlInput" class="form-control"
                       value="<?= e($mapsUrl) ?>"
                       placeholder="https://maps.app.goo.gl/… or https://www.google.com/maps/@2.3138,102.3192,17z">
                <small class="text-secondary d-block mt-1">
                    Open Google Maps, find your property, click "Share" → copy link.
                    The pricing benchmark uses distance to UTeM.
                </small>
                <div id="mapsUrlStatus" class="small mt-1"></div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var t = document.getElementById('rbMapLinkToggle');
        if (!t) return;
        t.addEventListener('click', function () {
            var body = document.getElementById('rbMapLinkBody');
            var chev = document.getElementById('rbMapLinkChevron');
            var open = body.classList.toggle('d-none') === false;
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
            chev.className = 'bi bi-chevron-' + (open ? 'down' : 'right');
        });
    })();
    </script>
    <?php
}

/**
 * The "Pin your property" drawer + all picker JS. Call once, after the form
 * fields. Renders nothing without an API key (the field falls back to manual
 * lat/lng entry). Google Maps JS is loaded lazily the first time the drawer
 * opens, so pages that never pin never pay for the map.
 */
function rb_map_pinpoint_assets(): void
{
    $key        = rb_gmaps_key();
    $resolveUrl = '' . BASE_PATH . '/landlord/geocode_link.php';

    // No API key: no interactive drawer, but still keep a pasted Google Maps
    // link and the manual latitude/longitude inputs in sync (link overwrites).
    if ($key === '') {
        ?>
        <script>
        (function () {
            var RESOLVE = <?= json_encode($resolveUrl) ?>;
            var input = document.getElementById('mapsUrlInput');
            if (!input) return;
            var t = null;
            function resolve() {
                var u = (input.value || '').trim();
                if (!/^https?:\/\//i.test(u)) return;
                fetch(RESOLVE + '?url=' + encodeURIComponent(u))
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.ok) return;
                        var la = document.querySelector('[name="latitude"]');
                        var ln = document.querySelector('[name="longitude"]');
                        if (la) la.value = Number(d.lat).toFixed(7);
                        if (ln) ln.value = Number(d.lng).toFixed(7);
                    }).catch(function () {});
            }
            input.addEventListener('input',  function () { clearTimeout(t); t = setTimeout(resolve, 600); });
            input.addEventListener('change', resolve);
        })();
        </script>
        <?php
        return;
    }

    $src = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($key)
         . '&loading=async&callback=rbMapsReady';
    ?>
    <style>
    .rb-pin-address{position:relative}
    .rb-pin-address textarea{padding-right:52px;min-height:62px}
    .rb-pin-btn{position:absolute;top:8px;right:8px;width:36px;height:36px;border-radius:6px;
        border:1px solid #E5E7EB;background:#F4F4EE;color:#0F2C52;font-size:18px;line-height:0;
        display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .12s}
    .rb-pin-btn:hover{background:#e9e7df}
    .rb-pin-btn.is-pinned{border-color:#2E8B57;background:#E4F2EA;color:#1F6D45}
    .rb-pin-hint{display:flex;align-items:center;gap:6px;font-size:.72rem;color:#4B5A6E;margin-top:.4rem}
    .rb-pinned-row{display:flex;align-items:center;justify-content:space-between;gap:10px;
        background:#E4F2EA;border:1px solid rgba(46,139,87,.35);border-radius:6px;padding:7px 10px;margin-top:.5rem}
    .rb-pinned-tag{display:flex;align-items:center;gap:7px;font-weight:600;font-size:.78rem;color:#1F6D45}
    .rb-pinned-coords{font:400 .68rem ui-monospace,Menlo,monospace;color:#4B5A6E}
    .rb-pinned-edit{font-weight:600;font-size:.74rem;color:#0F2C52;text-decoration:none}
    .rb-maplink{border-top:1px dashed #E5E7EB;padding-top:14px;margin-top:2px}
    .rb-maplink-toggle{display:flex;align-items:center;gap:8px;background:none;border:0;padding:0;
        font:500 .8rem 'Manrope',sans-serif;color:#0F2C52;cursor:pointer}
    .rb-maplink-note{font-weight:400;color:#6c757d}
    .rb-maplink-body{margin-top:8px}

    .rb-drawer-scrim{position:fixed;inset:0;background:rgba(15,44,82,.28);opacity:0;visibility:hidden;
        transition:opacity .2s;z-index:1080}
    .rb-drawer-scrim.open{opacity:1;visibility:visible}
    .rb-drawer{position:fixed;top:0;right:0;height:100%;width:400px;max-width:100%;background:#fff;
        border-left:1px solid #E5E7EB;box-shadow:-8px 0 24px -12px rgba(15,44,82,.25);
        display:flex;flex-direction:column;transform:translateX(100%);transition:transform .22s ease;z-index:1081}
    .rb-drawer-scrim.open .rb-drawer{transform:translateX(0)}
    .rb-drawer-head{padding:14px 16px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between}
    .rb-drawer-title{font-family:'Fraunces',Georgia,serif;font-size:17px;font-weight:600;color:#0F2C52}
    .rb-drawer-x{width:30px;height:30px;border:none;background:transparent;color:#6c757d;font-size:16px;cursor:pointer}
    .rb-drawer-search{padding:12px 16px;display:flex;flex-direction:column;gap:8px;border-bottom:1px solid #E5E7EB;position:relative}
    .rb-search-row{display:flex;gap:8px}
    .rb-search-wrap{flex:1;position:relative}
    .rb-search-wrap .bi-search{position:absolute;left:11px;top:11px;font-size:13px;color:#6c757d}
    .rb-search-input{width:100%;border:1px solid #ced4da;border-radius:6px;height:36px;padding:0 12px 0 32px;
        font:400 12.5px 'Manrope',sans-serif;color:#0E1B2C}
    .rb-search-input:focus{outline:none;border-color:#0F2C52}
    .rb-loc-btn{flex:none;border:1px solid #E5E7EB;background:#F4F4EE;border-radius:6px;height:36px;padding:0 10px;
        display:flex;align-items:center;gap:5px;font:600 11.5px 'Manrope',sans-serif;color:#0F2C52;cursor:pointer;white-space:nowrap}
    .rb-search-help{font-size:11px;color:#4B5A6E}
    .rb-results{position:absolute;left:16px;right:16px;top:52px;border:1px solid #E5E7EB;border-radius:6px;background:#fff;
        box-shadow:0 8px 20px -10px rgba(15,44,82,.3);overflow:hidden;z-index:3}
    .rb-result{padding:8px 12px;font:500 12px/1.3 'Manrope',sans-serif;color:#0E1B2C;cursor:pointer;border-top:1px solid #E5E7EB}
    .rb-result:first-child{border-top:0}
    .rb-result:hover,.rb-result.active{background:#E4F2EA}
    .rb-result small{display:block;font-weight:400;font-size:10.5px;color:#4B5A6E}
    .rb-result-foot{padding:6px 12px;border-top:1px solid #E5E7EB;font:400 10.5px 'Manrope',sans-serif;color:#6c757d}
    .rb-map{flex:1;position:relative;min-height:220px}
    .rb-map-el{position:absolute;inset:0}
    .rb-map-empty{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.94);
        border:1px dashed rgba(15,44,82,.3);border-radius:6px;padding:8px 12px;text-align:center;
        font:500 11.5px/1.4 'Manrope',sans-serif;color:#4B5A6E;pointer-events:none}
    .rb-map-empty small{font-size:10.5px;color:#6c757d}
    .rb-alert{margin:10px 16px 0;display:flex;gap:9px;border-radius:6px;padding:9px 10px;font:400 11.5px/1.45 'Manrope',sans-serif}
    .rb-alert-warn{background:#FFF4D6;border:1px solid #D4A017;color:#7C5E0A}
    .rb-alert-info{background:#FAF8F3;border:1px solid #E5E7EB;color:#4B5A6E}
    .rb-alert strong{display:block}
    .rb-drawer-foot{padding:10px 16px;border-top:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between;gap:10px}
    .rb-foot-coords{font:400 10.5px ui-monospace,Menlo,monospace;color:#4B5A6E}
    .rb-foot-btns{display:flex;gap:8px}
    .rb-btn-cancel{border:1px solid #E5E7EB;background:#fff;border-radius:999px;padding:7px 14px;
        font:600 12px 'Manrope',sans-serif;color:#0F2C52;cursor:pointer}
    .rb-btn-done{border:1px solid #0F2C52;background:#0F2C52;border-radius:999px;padding:7px 16px;
        font:600 12px 'Manrope',sans-serif;color:#fff;cursor:pointer}
    .rb-btn-done:disabled{opacity:.45;cursor:not-allowed}
    @media (max-width:576px){
        .rb-drawer{width:100%}
        .rb-drawer-foot{flex-direction:column;align-items:stretch}
        .rb-foot-btns{width:100%}
        .rb-btn-cancel,.rb-btn-done{flex:1}
    }
    </style>

    <div class="rb-drawer-scrim" id="rbDrawer" aria-hidden="true">
      <div class="rb-drawer" role="dialog" aria-modal="true" aria-label="Pin your property">
        <div class="rb-drawer-head">
            <div class="rb-drawer-title">Pin your property</div>
            <button type="button" class="rb-drawer-x" id="rbDrawerClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="rb-drawer-search">
            <div class="rb-search-row">
                <div class="rb-search-wrap">
                    <i class="bi bi-search"></i>
                    <input type="text" class="rb-search-input" id="rbSearch"
                           placeholder="Search an address" autocomplete="off">
                </div>
                <button type="button" class="rb-loc-btn" id="rbMyLocation">
                    <i class="bi bi-crosshair"></i> My location
                </button>
            </div>
            <div class="rb-search-help">Prefilled from the address you typed. Search, or click the map to drop the pin — confirming fills the address, city &amp; postcode for you.</div>
            <div class="rb-results d-none" id="rbResults"></div>
        </div>
        <div class="rb-alert rb-alert-warn d-none" id="rbGpsDenied">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Location access is off</strong>Search your address instead, or click the map to place the pin.</div>
        </div>
        <div class="rb-alert rb-alert-info d-none" id="rbNotFound">
            <i class="bi bi-search"></i>
            <div><strong>No match for that address</strong>Zoom to your area and click to drop the pin manually.</div>
        </div>
        <div class="rb-map">
            <div class="rb-map-el" id="rbMapEl"></div>
            <div class="rb-map-empty" id="rbMapEmpty">No pin yet<br><small>click anywhere to place it</small></div>
        </div>
        <div class="rb-drawer-foot">
            <div class="rb-foot-coords" id="rbFootCoords">lat —, lng —</div>
            <div class="rb-foot-btns">
                <button type="button" class="rb-btn-cancel" id="rbCancel">Cancel</button>
                <button type="button" class="rb-btn-done" id="rbDone" disabled>Done</button>
            </div>
        </div>
      </div>
    </div>

    <script>
    (function () {
        var UTEM = { lat: 2.3138, lng: 102.3210 };
        var mapsPromise = null, map = null, marker = null, geocoder = null;
        var picked = null;      // {lat,lng} confirmed into the form
        var draft  = null;      // {lat,lng} currently on the map (unconfirmed)

        var $ = function (id) { return document.getElementById(id); };
        var latI = $('rbLat'), lngI = $('rbLng');

        // ---- Google Maps loader (lazy, once) ----
        window.rbMapsReady = function () { window.__rbMapsResolve && window.__rbMapsResolve(); };
        function loadMaps() {
            if (mapsPromise) return mapsPromise;
            mapsPromise = new Promise(function (resolve, reject) {
                if (window.google && window.google.maps) { resolve(); return; }
                window.__rbMapsResolve = resolve;
                var s = document.createElement('script');
                s.src = <?= json_encode($src) ?>;
                s.async = true; s.onerror = reject;
                document.head.appendChild(s);
            });
            return mapsPromise;
        }

        function fmt(n) { return Number(n).toFixed(7); }
        function fmt5(n) { return Number(n).toFixed(5); }

        function setDraft(ll) {
            draft = { lat: ll.lat(), lng: ll.lng() };
            if (!marker) {
                marker = new google.maps.Marker({ position: ll, map: map, draggable: true });
                marker.addListener('dragend', function () { setDraft(marker.getPosition()); });
            } else {
                marker.setPosition(ll);
            }
            $('rbMapEmpty').classList.add('d-none');
            $('rbFootCoords').textContent = fmt(draft.lat) + ', ' + fmt(draft.lng);
            $('rbDone').disabled = false;
            hideResults();
        }

        function hideResults() { var r = $('rbResults'); r.classList.add('d-none'); r.innerHTML = ''; }

        function initMap(center, zoom, dropPin) {
            map = new google.maps.Map($('rbMapEl'), {
                center: center, zoom: zoom,
                mapTypeControl: false, streetViewControl: false, fullscreenControl: false
            });
            geocoder = new google.maps.Geocoder();
            map.addListener('click', function (e) { $('rbNotFound').classList.add('d-none'); setDraft(e.latLng); });
            if (dropPin) setDraft(new google.maps.LatLng(center.lat, center.lng));
        }

        function openDrawer() {
            var scrim = $('rbDrawer');
            scrim.classList.add('open');
            scrim.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // prefill the search box from the typed address
            var parts = [];
            ['address','postcode','city','state'].forEach(function (n) {
                var el = document.querySelector('[name="' + n + '"]');
                if (el && el.value.trim()) parts.push(el.value.trim());
            });
            $('rbSearch').value = parts.join(', ');

            loadMaps().then(function () {
                var startLL = picked ? new google.maps.LatLng(picked.lat, picked.lng)
                                     : new google.maps.LatLng(UTEM.lat, UTEM.lng);
                if (!map) {
                    initMap(picked || UTEM, picked ? 16 : 13, !!picked);
                } else {
                    map.setCenter(startLL);
                    map.setZoom(picked ? 16 : 13);
                    google.maps.event.trigger(map, 'resize');
                    if (picked) setDraft(startLL);
                }
                $('rbDone').disabled = !draft;
            }).catch(function () {
                $('rbMapEmpty').innerHTML = 'Map failed to load.<br><small>Check the API key / billing.</small>';
            });
        }

        function closeDrawer() {
            var scrim = $('rbDrawer');
            scrim.classList.remove('open');
            scrim.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function reflectPinnedState() {
            var btn = $('rbPinBtn');
            if (picked) {
                latI.value = fmt(picked.lat); lngI.value = fmt(picked.lng);
                $('rbPinnedCoords').textContent = fmt5(picked.lat) + ', ' + fmt5(picked.lng);
                $('rbPinnedRow').classList.remove('d-none');
                $('rbPinnedNote').classList.remove('d-none');
                $('rbPinHint').classList.add('d-none');
                btn.classList.add('is-pinned'); btn.title = 'Edit pin';
                btn.querySelector('i').className = 'bi bi-geo-alt-fill';
            }
        }

        // ---- reverse geocode: fill the address form from the confirmed pin ----
        function fillAddressFrom(latlng) {
            if (!geocoder) {
                if (!(window.google && window.google.maps)) return;
                geocoder = new google.maps.Geocoder();
            }
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status !== 'OK' || !results || !results.length) return;
                var best = results[0];
                var comp = {};
                best.address_components.forEach(function (c) {
                    c.types.forEach(function (t) { comp[t] = c; });
                });

                // street address = street number + route (+ taman/neighbourhood)
                var parts = [];
                if (comp.street_number) parts.push(comp.street_number.long_name);
                if (comp.route)         parts.push(comp.route.long_name);
                var subloc = comp.sublocality || comp.sublocality_level_1 || comp.neighborhood;
                if (subloc) parts.push(subloc.long_name);
                var street = parts.join(', ');
                if (!street) street = best.formatted_address.split(',').slice(0, 2).join(',').trim();

                var addrEl = document.querySelector('[name="address"]');
                if (addrEl && street) addrEl.value = street;

                // postcode
                if (comp.postal_code) {
                    var pc = document.querySelector('[name="postcode"]');
                    if (pc) pc.value = comp.postal_code.long_name;
                }

                // city — only when it matches one of the allowed Melaka areas
                var citySel = document.querySelector('select[name="city"]');
                if (citySel) {
                    var cands = [];
                    ['locality','sublocality','sublocality_level_1','neighborhood',
                     'administrative_area_level_2','administrative_area_level_3'].forEach(function (t) {
                        if (comp[t]) cands.push(comp[t].long_name.toLowerCase());
                    });
                    for (var i = 0; i < citySel.options.length; i++) {
                        var opt = (citySel.options[i].value || citySel.options[i].text).toLowerCase();
                        if (opt && cands.indexOf(opt) !== -1) { citySel.value = citySel.options[i].value; break; }
                    }
                }
            });
        }

        // ---- search (geocode) ----
        function runSearch() {
            var q = $('rbSearch').value.trim();
            if (!q || !geocoder) return;
            $('rbNotFound').classList.add('d-none');
            geocoder.geocode({ address: q + ', Melaka, Malaysia' }, function (results, status) {
                if (status !== 'OK' || !results || !results.length) {
                    hideResults();
                    $('rbNotFound').classList.remove('d-none');
                    return;
                }
                var box = $('rbResults');
                box.innerHTML = '';
                results.slice(0, 4).forEach(function (r, i) {
                    var loc = r.geometry.location;
                    var div = document.createElement('div');
                    div.className = 'rb-result' + (i === 0 ? ' active' : '');
                    var main = r.formatted_address.split(',')[0];
                    div.innerHTML = main + '<small>' + r.formatted_address + '</small>';
                    div.addEventListener('click', function () {
                        map.setCenter(loc); map.setZoom(17); setDraft(loc);
                    });
                    box.appendChild(div);
                });
                var foot = document.createElement('div');
                foot.className = 'rb-result-foot';
                foot.textContent = 'Not the right one? Click the map to place the pin yourself.';
                box.appendChild(foot);
                box.classList.remove('d-none');
            });
        }

        // ---- wire up (buttons exist even before Maps loads) ----
        document.addEventListener('DOMContentLoaded', function () {
            // seed picked from any coords already on the form
            if (latI && latI.value && lngI && lngI.value) {
                picked = { lat: parseFloat(latI.value), lng: parseFloat(lngI.value) };
            }

            var pinBtn = $('rbPinBtn');
            if (pinBtn) pinBtn.addEventListener('click', openDrawer);
            var editPin = $('rbEditPin');
            if (editPin) editPin.addEventListener('click', function (e) { e.preventDefault(); openDrawer(); });

            $('rbDrawerClose').addEventListener('click', closeDrawer);
            $('rbCancel').addEventListener('click', closeDrawer);
            $('rbDrawer').addEventListener('click', function (e) { if (e.target === this) closeDrawer(); });

            $('rbDone').addEventListener('click', function () {
                if (!draft) return;
                picked = { lat: draft.lat, lng: draft.lng };
                reflectPinnedState();
                fillAddressFrom(new google.maps.LatLng(draft.lat, draft.lng));
                syncLinkToPin(draft.lat, draft.lng);   // pin overwrites the link
                closeDrawer();
            });

            // ---- link <-> pin sync (last edit wins) ----
            // Pin confirmed -> rewrite the optional Google Maps link to match.
            function syncLinkToPin(lat, lng) {
                var mi = document.getElementById('mapsUrlInput');
                if (!mi) return;
                mi.dataset.rbSynced = '1';   // mark as machine-set, so it won't loop back
                mi.value = 'https://www.google.com/maps?q=' + lat.toFixed(7) + ',' + lng.toFixed(7);
                var body = document.getElementById('rbMapLinkBody');
                var chev = document.getElementById('rbMapLinkChevron');
                var tog  = document.getElementById('rbMapLinkToggle');
                if (body && body.classList.contains('d-none')) {
                    body.classList.remove('d-none');
                    if (chev) chev.className = 'bi bi-chevron-down';
                    if (tog) tog.setAttribute('aria-expanded', 'true');
                }
            }

            // Link pasted -> overwrite the pin + the address/city/postcode form.
            var mapsInput = document.getElementById('mapsUrlInput');
            if (mapsInput) {
                var lt = null;
                var resolveLink = function () {
                    if (mapsInput.dataset.rbSynced === '1') { mapsInput.dataset.rbSynced = ''; return; }
                    var u = (mapsInput.value || '').trim();
                    if (!/^https?:\/\//i.test(u)) return;
                    fetch(<?= json_encode($resolveUrl) ?> + '?url=' + encodeURIComponent(u))
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d.ok) return;
                            loadMaps().then(function () {
                                var ll = new google.maps.LatLng(Number(d.lat), Number(d.lng));
                                picked = { lat: Number(d.lat), lng: Number(d.lng) };
                                reflectPinnedState();
                                if (map) { map.setCenter(ll); map.setZoom(16); setDraft(ll); }
                                fillAddressFrom(ll);
                            });
                        }).catch(function () {});
                };
                mapsInput.addEventListener('input',  function () { clearTimeout(lt); lt = setTimeout(resolveLink, 600); });
                mapsInput.addEventListener('change', resolveLink);
            }

            var search = $('rbSearch');
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
            });

            $('rbMyLocation').addEventListener('click', function () {
                $('rbGpsDenied').classList.add('d-none');
                if (!navigator.geolocation) { $('rbGpsDenied').classList.remove('d-none'); return; }
                navigator.geolocation.getCurrentPosition(function (pos) {
                    var ll = new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                    map.setCenter(ll); map.setZoom(17); setDraft(ll);
                }, function () {
                    $('rbGpsDenied').classList.remove('d-none');
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && $('rbDrawer').classList.contains('open')) closeDrawer();
            });
        });
    })();
    </script>
    <?php
}

/**
 * Interactive location picker (legacy inline variant, kept for callers that
 * still embed a full map). New landlord flows use rb_address_pin_field() +
 * rb_map_pinpoint_assets() instead.
 */
function rb_map_picker(?float $lat, ?float $lng, string $height = '320px'): void
{
    [$la, $ln] = rb_coords($lat, $lng);
    $has    = ($la !== null);
    $latVal = $has ? number_format($la, 7, '.', '') : '';
    $lngVal = $has ? number_format($ln, 7, '.', '') : '';
    $key    = rb_gmaps_key();

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
