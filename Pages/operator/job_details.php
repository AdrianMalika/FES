<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'operator') {
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'customer':
            header('Location: ../customer/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

require_once __DIR__ . '/../../includes/database.php';

$operatorId = (int)($_SESSION['user_id'] ?? 0);
$operatorName = $_SESSION['name'] ?? 'Operator';
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.*, 
                       e.equipment_name, e.category, e.location AS equipment_location,
                       u.name AS customer_name, u.email AS customer_email
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
                LEFT JOIN users u ON u.user_id = b.customer_id
                WHERE b.booking_id = ? AND b.operator_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ii', $bookingId, $operatorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $booking = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Operator job details error: ' . $e->getMessage());
    }
}

$status = $booking['status'] ?? 'pending';
$badgeClasses = [
    'pending' => 'bg-amber-50 text-amber-700',
    'confirmed' => 'bg-blue-50 text-blue-700',
    'in_progress' => 'bg-purple-50 text-purple-700',
    'completed' => 'bg-emerald-50 text-emerald-700',
    'cancelled' => 'bg-gray-100 text-gray-700'
];
$badgeClass = $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700';
$serviceLocation = $booking ? trim($booking['service_location'] ?? '') : '';
if ($serviceLocation === '' && !empty($booking['field_address'])) {
    $serviceLocation = $booking['field_address'];
}

// Attempt to parse latitude / longitude from the service location string (e.g. "Lat -15.81..., Lng 35.00...").
$mapLat = null;
$mapLng = null;
if (!empty($serviceLocation)) {
    // Match two floating point numbers (latitude and longitude) anywhere in the string.
    if (preg_match('/(-?\d+\.\d+)[^\d\-\.]+(-?\d+\.\d+)/', $serviceLocation, $coords)) {
        $mapLat = $coords[1];
        $mapLng = $coords[2];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - FES Operator</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- TomTom Maps CSS -->
    <link rel="stylesheet" type="text/css" href="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps.css">
    <script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps-web.min.js"></script>
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
        @media (max-width: 767px) {
            #main-content { margin-left: 0 !important; width: 100% !important; }
        }
        @media (min-width: 768px) {
            #main-content { margin-left: 256px !important; width: calc(100% - 256px) !important; }
        }
    </style>
</head>
<body class="bg-gray-100">
<div class="min-h-screen w-full">
    <?php include __DIR__ . '/include/sidebar.php'; ?>

    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <div class="min-h-screen" id="main-content">
        <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator — Job Details</div>
                    <h1 class="text-xl font-semibold text-gray-900">Booking #BK-<?php echo htmlspecialchars((string)$bookingId); ?></h1>
                    <p class="text-xs text-gray-500 mt-1">Assigned operator: <?php echo htmlspecialchars($operatorName); ?></p>
                </div>
            </div>

            <a href="jobs.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Back to My Jobs
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if (!$booking): ?>
                <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                    Job not found or you don�t have access.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <section class="bg-white rounded-xl shadow-card p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-base font-semibold text-gray-900">Job Information</h2>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Customer</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['customer_email'] ?? ''); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($booking['category'] ?? '')); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Date</div>
                                    <div class="text-gray-900 font-medium"><?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Type</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Location</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($serviceLocation ?: 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Field Size</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['field_hectares'] ?? 'Not specified'); ?> acres</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contact Phone</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['contact_phone'] ?? 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Start Time</div>
                                    <div class="text-gray-900 font-medium"><?php echo !empty($booking['operator_start_time']) ? htmlspecialchars(date('M d, Y H:i', strtotime($booking['operator_start_time']))) : 'Not started'; ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">End Time</div>
                                    <div class="text-gray-900 font-medium"><?php echo !empty($booking['operator_end_time']) ? htmlspecialchars(date('M d, Y H:i', strtotime($booking['operator_end_time']))) : 'Not completed'; ?></div>
                                </div>
                            </div>

                            <?php if (!empty($booking['notes'])): ?>
                                <div class="mt-5 pt-5 border-t border-gray-100">
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Notes</div>
                                    <div class="text-gray-700"><?php echo htmlspecialchars($booking['notes']); ?></div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <?php if (!empty($booking['field_polygon'])): ?>
                            <section class="bg-white rounded-xl shadow-card p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-base font-semibold text-gray-900">Field Location Map</h2>
                                    <?php if (!empty($booking['field_lat']) && !empty($booking['field_lng'])): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars((float)$booking['field_lat']); ?>,<?php echo htmlspecialchars((float)$booking['field_lng']); ?>" 
                                           target="_blank" 
                                           class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                                            <i class="fas fa-external-link-alt"></i>
                                            View on Google Maps
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div id="field-map" class="w-full h-64 rounded-lg border border-gray-200 bg-gray-100"></div>
                            </section>
                        <?php endif; ?>
                    </div>

                    <section class="bg-white rounded-xl shadow-card p-6">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Job Actions</h2>
                        <div class="space-y-3">
                            <a href="job_status.php?id=<?php echo urlencode((string)$bookingId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                                <i class="fas fa-tasks text-fes-red"></i>
                                Update Job Status
                            </a>
                            <a href="job_hours.php?id=<?php echo urlencode((string)$bookingId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                                <i class="fas fa-clock text-fes-red"></i>
                                Record Work Hours
                            </a>
                            <a href="job_damage.php?id=<?php echo urlencode((string)$bookingId); ?>" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 font-medium">
                                <i class="fas fa-tools text-fes-red"></i>
                                Report Equipment Damage
                            </a>
                        </div>
                        <div class="mt-5 text-xs text-gray-500">
                            Last updated: <?php echo !empty($booking['updated_at']) ? htmlspecialchars(date('M d, Y — H:i', strtotime($booking['updated_at']))) : 'N/A'; ?>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    (function () {
        var btn = document.getElementById('fes-dashboard-menu-btn');
        var sidebar = document.getElementById('fes-dashboard-sidebar');
        var overlay = document.getElementById('fes-dashboard-overlay');
        if (!btn || !sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
            btn.setAttribute('aria-expanded', 'true');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            overlay.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function () {
            var isOpen = sidebar.classList.contains('translate-x-0');
            if (isOpen) closeSidebar();
            else openSidebar();
        });

        overlay.addEventListener('click', closeSidebar);
    })();

    // Initialize field map if polygon data exists
    <?php if (!empty($booking['field_polygon'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        try {
            var polygonData = <?php echo json_encode($booking['field_polygon']); ?>;
            var mapContainer = document.getElementById('field-map');

            if (mapContainer && polygonData) {
                if (!window.tt || typeof tt.map !== 'function') {
                    throw new Error('TomTom SDK not available');
                }

                // Parse polygon coordinates (stored as JSON array of [lng, lat])
                var coordinates = Array.isArray(polygonData) ? polygonData : JSON.parse(polygonData);
                if (!Array.isArray(coordinates) || coordinates.length < 3) {
                    throw new Error('Invalid polygon data');
                }

                // Ensure polygon ring is closed for GeoJSON
                var first = coordinates[0];
                var last = coordinates[coordinates.length - 1];
                if (!last || first[0] !== last[0] || first[1] !== last[1]) {
                    coordinates = coordinates.concat([first]);
                }

                var latLngs = coordinates.map(function(coord) {
                    return [coord[1], coord[0]]; // TomTom uses [lat, lng] format
                });

                if (latLngs.length > 0) {
                    // Calculate center of the polygon
                    var bounds = new tt.LngLatBounds();
                    latLngs.forEach(function(latLng) {
                        bounds.extend(new tt.LngLat(latLng[1], latLng[0]));
                    });
                    var center = bounds.getCenter();

                    // Initialize TomTom map
                    var map = tt.map({
                        key: 'UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0',
                        container: 'field-map',
                        center: [center.lng, center.lat],
                        zoom: 16,
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

                    map.on('load', function () {
                        var polygonFeature = {
                            type: 'Feature',
                            geometry: {
                                type: 'Polygon',
                                coordinates: [coordinates]
                            },
                            properties: {
                                name: 'Field Area'
                            }
                        };

                        map.addSource('fes-field-polygon', {
                            type: 'geojson',
                            data: polygonFeature
                        });

                        map.addLayer({
                            id: 'fes-field-polygon-layer',
                            type: 'fill',
                            source: 'fes-field-polygon',
                            paint: {
                                'fill-color': '#D32F2F',
                                'fill-opacity': 0.3
                            }
                        });

                        map.addLayer({
                            id: 'fes-field-polygon-outline',
                            type: 'line',
                            source: 'fes-field-polygon',
                            paint: {
                                'line-color': '#D32F2F',
                                'line-width': 3,
                                'line-opacity': 0.8
                            }
                        });

                        var marker = new tt.Marker()
                            .setLngLat([center.lng, center.lat])
                            .addTo(map);

                        var popup = new tt.Popup({ offset: 30 })
                            .setHTML('<b>Field Location</b><br>Size: <?php echo htmlspecialchars($booking['field_hectares'] ?? '0'); ?> Acres')
                            .addTo(map);

                        marker.setPopup(popup);

                        map.fitBounds(bounds, { padding: 20 });
                    });
                }
            }
        } catch (error) {
            console.error('Error initializing field map:', error);
            var mapContainer = document.getElementById('field-map');
            if (mapContainer) {
                mapContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><div class="text-center"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><div class="text-sm">Map data unavailable</div></div></div>';
            }
        }
    });
    <?php endif; ?>
</script>
</body>
</html>
