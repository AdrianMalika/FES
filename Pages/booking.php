<?php
session_start();

// Only customers can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: auth/signin.php?redirect=equipment.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';

$customerName = $_SESSION['name'] ?? 'Customer';
$equipmentId  = $_GET['equipment_id'] ?? '';
$equipment    = null;
$error        = '';

if ($equipmentId === '') {
    $error = 'No equipment selected. Please choose equipment from the catalogue first.';
} else {
    try {
        $conn = getDBConnection();
        $sql = "SELECT equipment_id, equipment_name, category, location, daily_rate, status, description
                FROM equipment
                WHERE equipment_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $equipmentId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $equipment = $row;
            } else {
                $error = 'The selected equipment could not be found.';
            }
            $stmt->close();
        } else {
            $error = 'Could not prepare equipment lookup.';
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Booking page error: ' . $e->getMessage());
        $error = 'An unexpected error occurred while loading the equipment details.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Equipment - FES</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- TomTom Maps CSS -->
    <link rel="stylesheet" type="text/css" href="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fes: { red: '#D32F2F', dark: '#424242' }
                    },
                    boxShadow: {
                        card: '0 4px 15px rgba(0,0,0,0.05)'
                    }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }
        #fes-booking-map { height: 380px; width: 100%; border-radius: 10px; border: 1px solid #e0e0e0; overflow: hidden; }
        .fes-map-wrapper { position: relative; }
        .fes-map-overlay {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 5;
            background: rgba(255,255,255,0.96);
            padding: 10px 12px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
            max-width: 260px;
        }
        .fes-map-overlay label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .fes-map-overlay input {
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../includes/header.php'; ?>

<main class="max-w-5xl mx-auto px-4 py-10">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-[0.18em] text-gray-400 mb-1">Customer Booking</p>
        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
            Book Equipment
        </h1>
        <p class="text-sm text-gray-600 mt-1">
            Logged in as <span class="font-semibold"><?php echo htmlspecialchars($customerName); ?></span>
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-start gap-3">
            <i class="fas fa-exclamation-circle mt-1"></i>
            <div>
                <?php echo htmlspecialchars($error); ?>
                <div class="mt-2">
                    <a href="equipment.php" class="inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:underline">
                        <i class="fas fa-arrow-left"></i> Back to equipment catalogue
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <!-- Map section: select field location / area to be worked on -->
            <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-map-marked-alt text-fes-red"></i>
                            Field Location
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Click on the map to drop a pin where the equipment will work.
                        </p>
                    </div>
                </div>
                <div class="fes-map-wrapper">
                    <div id="fes-booking-map"></div>
                    <div class="fes-map-overlay">
                        <label class="block text-[11px] font-semibold text-gray-600 mb-1">Selected area / location</label>
                        <input type="text" id="fes-location-display"
                               class="w-full border border-gray-300 rounded-md px-2.5 py-1.5 text-xs text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red"
                               placeholder="Click on the map to choose location" readonly>
                        <p class="mt-1 text-[10px] text-gray-500" id="fes-location-coords"></p>
                        <button type="button"
                                id="fes-clear-area"
                                class="mt-2 inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-medium text-gray-600 hover:bg-gray-50">
                            <i class="fas fa-undo-alt text-[10px]"></i> Clear area
                        </button>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: equipment summary -->
            <section class="lg:col-span-1 bg-white rounded-xl shadow-card border border-gray-100 p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-3 flex items-center gap-2">
                    <i class="fas fa-tractor text-fes-red"></i>
                    Equipment Summary
                </h2>
                <dl class="text-sm text-gray-800 space-y-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Equipment ID</dt>
                        <dd class="mt-0.5 font-semibold">
                            <?php echo htmlspecialchars($equipment['equipment_id']); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Name</dt>
                        <dd class="mt-0.5">
                            <?php echo htmlspecialchars($equipment['equipment_name']); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Category</dt>
                        <dd class="mt-0.5">
                            <?php echo htmlspecialchars(ucfirst($equipment['category'] ?? '')); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Location</dt>
                        <dd class="mt-0.5">
                            <?php echo htmlspecialchars($equipment['location'] ?? '-'); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Daily rate</dt>
                        <dd class="mt-0.5 font-semibold text-fes-red">
                            MK <?php echo number_format((float)($equipment['daily_rate'] ?? 0)); ?> / day
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-500">Status</dt>
                        <dd class="mt-0.5">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $equipment['status'] ?? 'unknown'))); ?>
                        </dd>
                    </div>
                </dl>
                <?php if (!empty($equipment['description'])): ?>
                    <div class="mt-4">
                        <dt class="text-xs uppercase tracking-wide text-gray-500 mb-1">Description</dt>
                        <p class="text-sm text-gray-700 leading-relaxed line-clamp-4">
                            <?php echo htmlspecialchars($equipment['description']); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Right: booking form, styled similar to ride booking UI -->
            <section class="lg:col-span-2 bg-white rounded-xl shadow-card border border-gray-100 p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    <i class="fas fa-calendar-check text-fes-red"></i>
                    Booking Details
                </h2>

                <form method="post" action="#" class="space-y-5">
                    <input type="hidden" name="equipment_id" value="<?php echo htmlspecialchars($equipment['equipment_id']); ?>">
                    <!-- Hidden fields to capture map selection -->
                    <input type="hidden" name="field_lat" id="field_lat">
                    <input type="hidden" name="field_lng" id="field_lng">
                    <input type="hidden" name="field_address" id="field_address">
                    <!-- Stores polygon vertices (JSON array of [lng,lat]) when user marks an area -->
                    <input type="hidden" name="field_polygon" id="field_polygon">
                    <!-- Stores calculated area in hectares for the marked field -->
                    <input type="hidden" name="field_hectares" id="field_hectares">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Booking date</label>
                        <input type="date" name="booking_date" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service type</label>
                            <select name="service_type" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                <option value="land_prep">Land Preparation</option>
                                <option value="planting">Planting</option>
                                <option value="harvesting">Harvesting</option>
                                <option value="irrigation">Irrigation</option>
                                <option value="other">Other service</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment method</label>
                            <select name="payment_method" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                <option value="cash">Cash</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Credit / Debit Card</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service location</label>
                        <input type="text" name="service_location" placeholder="Where should this equipment be used?" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact phone</label>
                        <input type="tel" name="contact_phone" placeholder="Phone number for the operator to reach you" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Special instructions (optional)</label>
                        <textarea name="notes" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="Any access notes, crop type, field conditions, or timing preferences..."></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3">
                        <a href="equipment.php" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="px-5 py-2.5 bg-fes-red hover:bg-[#b71c1c] text-white rounded-lg text-sm font-semibold shadow">
                            Submit Booking Request
                        </button>
                    </div>
                </form>

                <p class="mt-3 text-xs text-gray-500">
                    This page currently mirrors the ride-booking style layout. We can wire it to save bookings and process payments next.
                </p>
            </section>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>

<!-- TomTom Maps JS (used to pick field location) -->
<script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps-web.min.js"></script>
<script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/services/services-web.min.js"></script>
<!-- Turf.js for polygon area calculation -->
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var mapContainer = document.getElementById('fes-booking-map');
    if (!mapContainer || typeof tt === 'undefined') {
        return;
    }

    // Initialise map (center roughly on Malawi) with satellite imagery
    var map = tt.map({
        key: 'UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0',
        container: 'fes-booking-map',
        center: [34.3, -13.9],
        zoom: 19,
        language: 'en-GB',
        style: {
            version: 8,
            sources: {
                'raster-tiles': {
                    type: 'raster',
                    tiles: [
                        'https://api.tomtom.com/map/1/tile/sat/main/{z}/{x}/{y}.jpg?key=UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0'
                    ],
                    tileSize: 256
                }
            },
            layers: [{
                id: 'simple-tiles',
                type: 'raster',
                source: 'raster-tiles',
                minzoom: 0,
                maxzoom: 22
            }]
        }
    });

    map.addControl(new tt.NavigationControl());

    // Try to show user's current location (if browser permission is granted)
    try {
        var geo = new tt.GeolocateControl({
            positionOptions: { enableHighAccuracy: true },
            trackUserLocation: true
        });
        map.addControl(geo);
        if (typeof geo.trigger === 'function') {
            geo.trigger();
        }
    } catch (e) {
        console.warn('Geolocation not available:', e);
    }

    var marker = null;
    var latInput = document.getElementById('field_lat');
    var lngInput = document.getElementById('field_lng');
    var addrInput = document.getElementById('field_address');
    var displayInput = document.getElementById('fes-location-display');
    var coordsLabel = document.getElementById('fes-location-coords');
    var polygonInput = document.getElementById('field_polygon');
    var hectaresInput = document.getElementById('field_hectares');
    var clearBtn = document.getElementById('fes-clear-area');

    // For area marking
    var polygonCoords = [];
    var polygonSourceId = 'fes-field-polygon';
    var polygonLayerId = 'fes-field-polygon-layer';

    function updateAddress(position) {
        tt.services.reverseGeocode({
            key: 'UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0',
            position: position
        }).go().then(function (response) {
            if (response.addresses && response.addresses[0]) {
                var a = response.addresses[0].address;
                var text = a.freeformAddress || '';
                displayInput.value = text;
                addrInput.value = text;
            }
        }).catch(function () {
            displayInput.value = 'Location selected (address lookup failed)';
        });
    }

    function renderPolygon() {
        if (polygonCoords.length < 3) {
            if (map.getLayer(polygonLayerId)) {
                map.removeLayer(polygonLayerId);
            }
            if (map.getSource(polygonSourceId)) {
                map.removeSource(polygonSourceId);
            }
            polygonInput.value = '';
            if (hectaresInput) {
                hectaresInput.value = '';
            }
            return;
        }

        var closed = polygonCoords.concat([polygonCoords[0]]);
        var data = {
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [closed]
                }
            }]
        };

        if (map.getSource(polygonSourceId)) {
            map.getSource(polygonSourceId).setData(data);
        } else {
            map.addSource(polygonSourceId, {
                type: 'geojson',
                data: data
            });
            map.addLayer({
                id: polygonLayerId,
                type: 'fill',
                source: polygonSourceId,
                paint: {
                    'fill-color': 'rgba(211,47,47,0.25)',
                    'fill-outline-color': '#D32F2F'
                }
            });
        }

        polygonInput.value = JSON.stringify(polygonCoords);

        // Calculate area in hectares using Turf.js (if available)
        if (typeof turf !== 'undefined' && hectaresInput) {
            try {
                var areaSqm = turf.area(data.features[0]); // square meters
                var ha = areaSqm / 10000; // 10,000 m² per hectare
                hectaresInput.value = ha.toFixed(2);
                coordsLabel.textContent = polygonCoords.length + ' points · approx ' + ha.toFixed(2) + ' ha';
            } catch (e) {
                console.error('Area calculation failed:', e);
                hectaresInput.value = '';
                coordsLabel.textContent = polygonCoords.length + ' points selected for field boundary';
            }
        } else {
            coordsLabel.textContent = polygonCoords.length + ' points selected for field boundary';
        }
    }

    function clearArea() {
        polygonCoords = [];
        if (map.getLayer(polygonLayerId)) {
            map.removeLayer(polygonLayerId);
        }
        if (map.getSource(polygonSourceId)) {
            map.removeSource(polygonSourceId);
        }
        if (marker) {
            marker.remove();
            marker = null;
        }
        latInput.value = '';
        lngInput.value = '';
        addrInput.value = '';
        displayInput.value = '';
        coordsLabel.textContent = '';
        polygonInput.value = '';
        if (hectaresInput) {
            hectaresInput.value = '';
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearArea();
        });
    }

    map.on('click', function (e) {
        var lngLat = [e.lngLat.lng, e.lngLat.lat];

        // Add vertex to polygon
        polygonCoords.push(lngLat);

        // Show small marker at last clicked point
        if (marker) {
            marker.setLngLat(lngLat);
        } else {
            marker = new tt.Marker({ color: '#D32F2F' })
                .setLngLat(lngLat)
                .addTo(map);
        }

        // Save last point as representative location
        latInput.value = lngLat[1];
        lngInput.value = lngLat[0];

        renderPolygon();
        updateAddress(lngLat);
    });
});
</script>
</body>
</html>

