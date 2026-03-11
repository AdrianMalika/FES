<?php
session_start();

// Only customers can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: auth/signin.php?redirect=equipment.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';

// FES Depot Configuration (Kaoshiung, Blantyre)
define('FES_DEPOT_LAT', -15.791381197859343);
define('FES_DEPOT_LNG', 35.00946109783795);
define('FES_DEPOT_ADDRESS', 'Kaoshiung, Blantyre, Malawi');

// Hardcoded Rates Configuration
$RATES = [
    'transport_per_km' => 5000,     // MK per km (increased)
    'operator_per_hour' => 2000,    // MK per hour  
    'base_fee' => 15000,            // MK base booking fee (increased)
    
    // Equipment rates per category (increased)
    'equipment' => [
        'tractor' => [
            'hourly' => 25000,       // MK per hour
            'areas' => 15000,        // MK per area
            'daily' => 180000        // MK per day
        ],
        'plow' => [
            'hourly' => 15000,       // MK per hour
            'areas' => 8000,         // MK per area
            'daily' => 100000        // MK per day
        ],
        'harvester' => [
            'hourly' => 35000,       // MK per hour
            'areas' => 20000,        // MK per area
            'daily' => 250000        // MK per day
        ],
        'irrigation' => [
            'hourly' => 20000,       // MK per hour
            'areas' => 12000,        // MK per area
            'daily' => 140000        // MK per day
        ],
        'default' => [
            'hourly' => 18000,       // MK per hour
            'areas' => 10000,        // MK per area
            'daily' => 120000        // MK per day
        ]
    ]
];

// Calculate distance using Haversine formula
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $latDiff = deg2rad($lat2 - $lat1);
    $lngDiff = deg2rad($lng2 - $lng1);
    
    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lngDiff / 2) * sin($lngDiff / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c; // Distance in kilometers
}

// Get equipment rates based on category and database values
function getEquipmentRates($category, $hourlyRate, $rates) {
    $category = strtolower($category);
    
    // Use database hourly rate if available, otherwise use hardcoded rates
    $dbHourlyRate = floatval($hourlyRate ?? 0);
    
    if ($dbHourlyRate > 0) {
        // Use database rates with standard markup for per-area pricing
        return [
            'hourly' => $dbHourlyRate,
            'areas' => $dbHourlyRate * 0.5, // Per-area is typically 50% of hourly rate
            'daily' => $dbHourlyRate * 8 * 0.9 // Daily rate with 10% discount
        ];
    }
    
    // Fallback to hardcoded rates if database rate is 0
    foreach ($rates['equipment'] as $key => $rate) {
        if ($key !== 'default' && strpos($category, $key) !== false) {
            return $rate;
        }
    }
    
    return $rates['equipment']['default'];
}

// Calculate comprehensive cost
function calculateBookingCost($equipment, $fieldLat, $fieldLng, $fieldAreas, $rates) {
    $costBreakdown = [];
    
    // 1. Calculate travel distance and cost
    $distance = 0;
    $travelCost = 0;
    
    if (!empty($fieldLat) && !empty($fieldLng)) {
        $distance = calculateDistance(
            FES_DEPOT_LAT, 
            FES_DEPOT_LNG,
            floatval($fieldLat), 
            floatval($fieldLng)
        );
        $travelCost = $distance * $rates['transport_per_km'];
    }
    
    $costBreakdown['distance_km'] = round($distance, 2);
    $costBreakdown['travel_cost'] = $travelCost;
    
    // 2. Get equipment rates
    $equipmentRates = getEquipmentRates($equipment['category'] ?? '', $equipment['hourly_rate'] ?? 0, $rates);
    
    // 3. Calculate equipment cost based on available data
    $equipmentCost = 0;
    $serviceDuration = 8; // Standard 8 hours per day
    
    $landAreas = floatval($fieldAreas ?? 0);
    
    if ($landAreas > 0) {
        // Area-based pricing (priority)
        $equipmentCost = $landAreas * $equipmentRates['areas'];
        $costBreakdown['pricing_model'] = 'per_area';
        $costBreakdown['land_area_areas'] = $landAreas;
        $costBreakdown['rate_per_area'] = $equipmentRates['areas'];
        
        // Apply minimum equipment cost (minimum MK 25,000 or cost of 1 area)
        $minimumEquipmentCost = max(25000, $equipmentRates['areas'] * 1);
        if ($equipmentCost < $minimumEquipmentCost) {
            $equipmentCost = $minimumEquipmentCost;
            $costBreakdown['pricing_model'] = 'minimum_charge';
            $costBreakdown['minimum_cost'] = $minimumEquipmentCost;
        }
    } else {
        // Hourly pricing (fallback)
        $equipmentCost = $serviceDuration * $equipmentRates['hourly'];
        $costBreakdown['pricing_model'] = 'per_hour';
        $costBreakdown['service_hours'] = $serviceDuration;
        $costBreakdown['rate_per_hour'] = $equipmentRates['hourly'];
    }
    
    $costBreakdown['equipment_cost'] = $equipmentCost;
    
    // 4. Calculate operator cost
    $operatorCost = $serviceDuration * $rates['operator_per_hour'];
    $costBreakdown['operator_cost'] = $operatorCost;
    
    // 5. Add base fee
    $costBreakdown['base_fee'] = $rates['base_fee'];
    
    // 6. Calculate total cost
    $totalCost = $equipmentCost + $operatorCost + $travelCost + $rates['base_fee'];
    $costBreakdown['total_cost'] = $totalCost;
    
    return $costBreakdown;
}

$customerName = $_SESSION['name'] ?? 'Customer';
$equipmentId  = $_GET['equipment_id'] ?? '';
$equipment    = null;
$error        = '';

if ($equipmentId === '') {
    $error = 'No equipment selected. Please choose equipment from the catalogue first.';
} else {
    try {
        $conn = getDBConnection();
        $sql = "SELECT equipment_id, equipment_name, category, location, daily_rate, hourly_rate, status, description
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
                        fes: { red: '#D32F2F', dark: '#1a1a1a', mid: '#2e2e2e' }
                    },
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
                    }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }

        :root {
            --red: #D32F2F;
            --red-deep: #b71c1c;
            --dark: #1a1a1a;
            --mid: #2e2e2e;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: var(--red); border-radius: 99px; }
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
<body class="bg-gray-50 font-body text-gray-900 antialiased">
<?php include '../includes/header.php'; ?>

<main class="max-w-7xl mx-auto px-6 py-16">
    <!-- Header -->
    <div class="mb-10">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-fes-red font-display font-700 text-sm uppercase tracking-[0.2em]">Customer Booking</span>
            <span class="block h-px w-12 bg-fes-red opacity-60"></span>
        </div>
        <h1 class="font-display font-900 text-4xl lg:text-5xl text-gray-900 leading-none mb-6" style="letter-spacing:-0.01em;">
            Book Your<br>
            <span class="text-fes-red">Equipment</span>
        </h1>
        <p class="text-gray-600 text-base leading-relaxed max-w-lg">
            Welcome back, <span class="font-semibold text-fes-red"><?php echo htmlspecialchars($customerName); ?></span>. Complete your booking details below.
        </p>
    </div>

        <?php if (!empty($error)): ?>
            <div class="mb-8 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-start gap-3">
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
            <!-- Map Section -->
            <section class="bg-white rounded-sm overflow-hidden border border-gray-100 mb-10">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="accent-line"></span>
                        <h2 class="font-display font-800 text-2xl lg:text-3xl text-gray-900" style="letter-spacing:-0.01em;">
                            Select Service Location
                        </h2>
                    </div>
                    <p class="text-gray-600 text-sm leading-relaxed mb-6">
                        Click on the map to mark where the equipment will be deployed for your service.
                    </p>
                    <div class="fes-map-wrapper">
                        <div id="fes-booking-map" class="rounded-sm"></div>
                        <div class="fes-map-overlay">
                            <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wider">Selected Location</label>
                            <input type="text" id="fes-location-display"
                                   class="w-full border border-gray-300 rounded-sm px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red bg-white"
                                   placeholder="Click on the map to choose location" readonly>
                            <p class="mt-1 text-xs text-gray-500" id="fes-location-coords"></p>
                            <button type="button"
                                    id="fes-clear-area"
                                    class="mt-2 inline-flex items-center gap-1 rounded-sm border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                <i class="fas fa-undo-alt text-xs"></i>
                                Clear Selection
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-7">
                <!-- Equipment Summary -->
                <div class="lg:col-span-1">
                    <section class="equip-card rounded-sm overflow-hidden border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="accent-line"></span>
                                <h2 class="font-display font-800 text-xl text-gray-900" style="letter-spacing:-0.01em;">
                                    Equipment Details
                                </h2>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Equipment ID</p>
                                    <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($equipment['equipment_id']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Name</p>
                                    <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars($equipment['equipment_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Category</p>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-fes-red text-white">
                                        <?php echo htmlspecialchars(ucfirst($equipment['category'] ?? '')); ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Location</p>
                                    <p class="text-sm text-gray-600 flex items-center gap-2">
                                        <i class="fas fa-map-marker-alt text-fes-red text-xs"></i>
                                        <?php echo htmlspecialchars($equipment['location'] ?? '-'); ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Daily Rate</p>
                                    <p class="text-2xl font-bold text-fes-red" style="letter-spacing:-0.01em;">
                                        MK <?php echo number_format((float)($equipment['daily_rate'] ?? 0)); ?>
                                        <span class="text-sm font-normal text-gray-500">/ day</span>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</p>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-green-50 text-green-700 border border-green-200">
                                        <span class="status-dot bg-green-500"></span>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $equipment['status'] ?? 'unknown'))); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($equipment['description'])): ?>
                                <div class="mt-6 pt-6 border-t border-gray-100">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Description</p>
                                    <p class="text-sm text-gray-600 leading-relaxed">
                                        <?php echo htmlspecialchars($equipment['description']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- Booking Form -->
                <div class="lg:col-span-2">
                    <section class="equip-card rounded-sm overflow-hidden border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="accent-line"></span>
                                <h2 class="font-display font-800 text-2xl lg:text-3xl text-gray-900" style="letter-spacing:-0.01em;">
                                    Booking Information
                                </h2>
                            </div>
                            <p class="text-gray-600 text-sm leading-relaxed mb-6">
                                Provide the details for your equipment booking request.
                            </p>
                            <form method="post" action="booking-confirmation.php" class="space-y-5">
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
                                    <label class="block text-sm font-semibold text-gray-900 mb-2 uppercase tracking-wider">Booking Date</label>
                                    <input type="date" name="booking_date" required
                                           class="w-full border border-gray-200 rounded-sm px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red bg-white">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-2 uppercase tracking-wider">Service Type</label>
                                    <select name="service_type" required
                                            class="w-full border border-gray-200 rounded-sm px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red bg-white">
                                        <option value="">Select a service type</option>
                                        <option value="land_prep">Land Preparation</option>
                                        <option value="planting">Planting</option>
                                        <option value="harvesting">Harvesting</option>
                                        <option value="irrigation">Irrigation</option>
                                        <option value="other">Other Service</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-2 uppercase tracking-wider">Service Location</label>
                                    <input type="text" name="service_location" id="service_location" 
                                           placeholder="Location will be set from map selection" 
                                           class="w-full border border-gray-200 rounded-sm px-4 py-2.5 text-sm text-gray-800 bg-gray-50" readonly>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Selected from the map above
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-2 uppercase tracking-wider">Contact Phone</label>
                                    <input type="tel" name="contact_phone" placeholder="Phone number for operator contact" required
                                           class="w-full border border-gray-200 rounded-sm px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red bg-white">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-2 uppercase tracking-wider">Special Instructions</label>
                                    <textarea name="notes" rows="4" 
                                              placeholder="Any access notes, crop type, field conditions, or timing preferences..."
                                              class="w-full border border-gray-200 rounded-sm px-4 py-2 text-sm text-gray-800 focus:outline-none focus:ring-1 focus:ring-fes-red focus:border-fes-red bg-white resize-none"></textarea>
                                </div>

                                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                                    <a href="equipment.php" 
                                       class="inline-flex items-center gap-2 px-6 py-3 border border-gray-200 text-gray-600 font-display font-700 uppercase tracking-wider text-sm rounded-sm hover:bg-gray-50">
                                        <i class="fas fa-arrow-left text-xs"></i>
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="inline-flex items-center gap-3 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider px-8 py-4 rounded-sm shadow-lg transition-all duration-300 hover:shadow-fes-red/40 hover:shadow-xl" style="font-size:1rem; letter-spacing:0.1em;">
                                        <i class="fas fa-calendar-check text-sm"></i>
                                        Submit Booking Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
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
    var serviceLocationInput = document.getElementById('service_location');

    // For area marking
    var polygonCoords = [];
    var polygonSourceId = 'fes-field-polygon';
    var polygonLayerId = 'fes-field-polygon-layer';

    function updateAddress(lngLat) {
        tt.services.reverseGeocode({
            key: 'UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0',
            position: lngLat
        }).go().then(function (response) {
            if (response.addresses && response.addresses[0]) {
                var a = response.addresses[0].address;
                var text = a.freeformAddress || '';
                displayInput.value = text;
                addrInput.value = text;
                if (serviceLocationInput) {
                    serviceLocationInput.value = text;
                }
            }
        }).catch(function () {
            displayInput.value = 'Location selected (address lookup failed)';
            if (serviceLocationInput) {
                serviceLocationInput.value = 'Location selected (address lookup failed)';
            }
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

        // Calculate area in areas using Turf.js (if available)
        if (typeof turf !== 'undefined' && hectaresInput) {
            try {
                var areaSqm = turf.area(data.features[0]); // square meters
                var areas = areaSqm / 10000; // 10,000 m² per area (same as hectare conversion)
                hectaresInput.value = areas.toFixed(2);
                coordsLabel.textContent = polygonCoords.length + ' points · approx ' + areas.toFixed(2) + ' areas';
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
        if (serviceLocationInput) {
            serviceLocationInput.value = '';
        }
    }

    // Calculate and update cost breakdown with area names
    function updateCostCalculation() {
        var fieldLat = latInput.value;
        var fieldLng = lngInput.value;
        var fieldAreas = hectaresInput ? parseFloat(hectaresInput.value) || 0 : 0;
        
        if (!fieldLat || !fieldLng) {
            return;
        }

        // FES Depot coordinates
        var depotLat = -15.791381197859343;
        var depotLng = 35.00946109783795;
        
        // Calculate distance using Haversine formula
        function calculateDistance(lat1, lng1, lat2, lng2) {
            var earthRadius = 6371; // Earth's radius in kilometers
            var latDiff = (lat2 - lat1) * Math.PI / 180;
            var lngDiff = (lng2 - lng1) * Math.PI / 180;
            
            var a = Math.sin(latDiff / 2) * Math.sin(latDiff / 2) +
                     Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                     Math.sin(lngDiff / 2) * Math.sin(lngDiff / 2);
            
            var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return earthRadius * c;
        }

        var distance = calculateDistance(depotLat, depotLng, parseFloat(fieldLat), parseFloat(fieldLng));
        
        // Equipment rates (using database rates with fallback to hardcoded)
        var equipmentRates = {
            'tractor': { hourly: 25000, areas: 15000 },
            'plow': { hourly: 15000, areas: 8000 },
            'harvester': { hourly: 35000, areas: 20000 },
            'irrigation': { hourly: 20000, areas: 12000 },
            'default': { hourly: 18000, areas: 10000 }
        };
        
        // Get actual database hourly rate
        var dbHourlyRate = <?php echo floatval($equipment['hourly_rate'] ?? 0); ?>;
        
        // Get equipment category
        var equipmentCategory = '<?php echo strtolower($equipment['category'] ?? ''); ?>';
        var rates = equipmentRates.default;
        
        // Use database rate if available, otherwise use hardcoded rates
        if (dbHourlyRate > 0) {
            rates = {
                hourly: dbHourlyRate,
                areas: dbHourlyRate * 0.5, // Per-area is 50% of hourly rate
                daily: dbHourlyRate * 8 * 0.9  // Daily rate with 10% discount
            };
        } else {
            for (var key in equipmentRates) {
                if (key !== 'default' && equipmentCategory.indexOf(key) !== -1) {
                    rates = equipmentRates[key];
                    break;
                }
            }
        }
        
        // Calculate costs
        var transportCost = distance * 5000; // MK 5000 per km
        var operatorCost = 8 * 2000; // 8 hours * MK 2,000 per hour
        var baseFee = 15000; // MK base fee
        var equipmentCost = 0;
        var pricingModel = '';
        
        if (fieldAreas > 0) {
            equipmentCost = fieldAreas * rates.areas;
            pricingModel = fieldAreas + ' areas × MK ' + rates.areas + '/area';
            
            // Apply minimum equipment cost (e.g., minimum 1 area or MK 25,000)
            var minimumEquipmentCost = Math.max(25000, rates.areas * 1); // Minimum MK 25,000 or cost of 1 area
            if (equipmentCost < minimumEquipmentCost) {
                equipmentCost = minimumEquipmentCost;
                pricingModel = 'Minimum charge: MK ' + minimumEquipmentCost.toLocaleString();
            }
        } else {
            equipmentCost = 8 * rates.hourly; // 8 hours standard
            pricingModel = '8 hrs × MK ' + rates.hourly + '/hr';
        }
        
        var totalCost = equipmentCost + operatorCost + transportCost + baseFee;
        
        // Use the actual location address from the map instead of coordinates
        var locationName = displayInput.value || 'Selected location';
        
        // Update display with area names
        console.log('Cost calculated for location: ' + locationName + ' (' + distance.toFixed(2) + ' km from depot)');
        
        // Store calculation info for potential use
        if (typeof window.fesCostData === 'undefined') {
            window.fesCostData = {};
        }
        window.fesCostData = {
            equipmentCost: equipmentCost,
            operatorCost: operatorCost,
            travelCost: transportCost,
            baseFee: baseFee,
            totalCost: totalCost,
            distance: distance,
            locationName: locationName,
            pricingModel: pricingModel
        };
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
        updateCostCalculation(); // Update cost calculation when location is selected
    });
});
</script>
</body>
</html>

