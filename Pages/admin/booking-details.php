<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has admin role
if ($_SESSION['role'] !== 'admin') {
    switch($_SESSION['role']) {
        case 'operator':
            header('Location: ../operator/dashboard.php');
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

$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['new_status'])) {
    $updateId = intval($_POST['booking_id']);
    $newStatus = $_POST['new_status'];
    $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    if ($updateId > 0 && in_array($newStatus, $allowed, true)) {
        try {
            $conn = getDBConnection();
            $sql = "UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('si', $newStatus, $updateId);
                $stmt->execute();
                $stmt->close();
                $message = 'Booking status updated.';
            }
            $conn->close();
        } catch (Exception $e) {
            error_log('Admin booking detail update error: ' . $e->getMessage());
        }
    }
}

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.*, 
                       e.equipment_name, e.category, e.location AS equipment_location, e.daily_rate, e.hourly_rate, e.per_hectare_rate, e.status AS equipment_status,
                       u.name AS customer_name, u.email AS customer_email
                FROM bookings b
                JOIN equipment e ON e.equipment_id = b.equipment_id
                JOIN users u ON u.user_id = b.customer_id
                WHERE b.booking_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $bookingId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $booking = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Admin booking detail fetch error: ' . $e->getMessage());
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Admin</title>
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
            #main-content { margin-left: 300px !important; width: calc(100% - 300px) !important; }
        }
    </style>
</head>
<body>
    <div class="min-h-screen w-full bg-gray-100">
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

        <div class="min-h-screen" style="margin-left: 300px; width: calc(100% - 300px);" id="main-content">
            <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm md:pl-6">
                <div class="flex items-center gap-3">
                    <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="text-sm text-gray-500">Admin</div>
                        <h1 class="text-xl font-semibold text-gray-900">Booking Details</h1>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="bookings.php" class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 font-medium px-4 py-2 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-arrow-left"></i>
                        Back to Bookings
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
                <?php if (!empty($message)): ?>
                    <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$booking): ?>
                    <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                        Booking not found.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-card p-5">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Booking ID</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900">#BK-<?php echo htmlspecialchars((string)$booking['booking_id']); ?></div>
                            <div class="mt-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                </span>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Customer</div>
                            <div class="mt-2 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></div>
                            <div class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($booking['customer_email'] ?? ''); ?></div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5">
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Estimated Total</div>
                            <div class="mt-2 text-2xl font-semibold text-fes-red">MK <?php echo number_format((float)($booking['estimated_total_cost'] ?? 0)); ?></div>
                            <div class="mt-1 text-sm text-gray-600">Service: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <section class="bg-white rounded-xl shadow-card p-6">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Booking Information</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(ucfirst($booking['category'] ?? '')); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment Location</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_location'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['equipment_status'] ?? 'N/A'))); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Date</div>
                                    <div class="text-gray-900 font-medium">
                                        <?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Days</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars((string)($booking['service_days'] ?? 1)); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Location</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($serviceLocation ?: 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contact Phone</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['contact_phone'] ?? 'N/A'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Field Size</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['field_hectares'] ?? 'Not specified'); ?> hectares</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Field Address</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['field_address'] ?: 'Not specified'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">GPS Coordinates</div>
                                    <div class="text-gray-900 font-medium">
                                        <?php if (!empty($booking['field_lat']) && !empty($booking['field_lng'])): ?>
                                            Lat: <?php echo htmlspecialchars((float)$booking['field_lat']); ?>, Lng: <?php echo htmlspecialchars((float)$booking['field_lng']); ?>
                                        <?php else: ?>
                                            Not specified
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Notes</div>
                                    <div class="text-gray-700"><?php echo !empty($booking['notes']) ? htmlspecialchars($booking['notes']) : '—'; ?></div>
                                </div>
                            </div>
                        </section>

                        <section class="bg-white rounded-xl shadow-card p-6">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Field Location & Area</h2>
                            
                            <?php if (!empty($booking['field_polygon'])): ?>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Field Polygon Data</div>
                                    <div class="bg-gray-50 rounded-lg p-3 text-xs font-mono text-gray-700 max-h-32 overflow-y-auto">
                                        <?php echo htmlspecialchars($booking['field_polygon']); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Field Location Map</div>
                                    <div id="field-map" class="w-full h-64 bg-gray-100 rounded-lg border border-gray-200"></div>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-8">
                                    <i class="fas fa-map text-3xl mb-2"></i>
                                    <div class="text-sm">No field polygon data available</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($booking['field_lat']) && !empty($booking['field_lng'])): ?>
                                <div class="mt-4">
                                    <a href="https://www.google.com/maps?q=<?php echo htmlspecialchars((float)$booking['field_lat']); ?>,<?php echo htmlspecialchars((float)$booking['field_lng']); ?>" 
                                       target="_blank" 
                                       class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium">
                                        <i class="fas fa-external-link-alt"></i>
                                        View on Google Maps
                                    </a>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <div class="bg-white rounded-xl shadow-card p-6 mt-6">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Cost Breakdown</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Service Type</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Service Days</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars((string)($booking['service_days'] ?? 1)); ?> days</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Field Size</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['field_hectares'] ?? '0'); ?> hectares</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <?php if (!empty($booking['daily_rate'])): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Daily Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$booking['daily_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['hourly_rate'])): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Hourly Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$booking['hourly_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($booking['per_hectare_rate'])): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Per Hectare Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$booking['per_hectare_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-3 mt-2">
                                    <span class="text-base font-semibold text-gray-900">Estimated Total Cost</span>
                                    <span class="text-base font-bold text-fes-red">MK <?php echo number_format((float)($booking['estimated_total_cost'] ?? 0)); ?></span>
                                </div>
                                <div class="mt-4">
                                    <form method="post" class="space-y-3">
                                        <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars((string)$booking['booking_id']); ?>">
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Update Status</div>
                                        <select name="new_status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                            <option value="pending" <?php if ($status === 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="confirmed" <?php if ($status === 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                            <option value="in_progress" <?php if ($status === 'in_progress') echo 'selected'; ?>>In Progress</option>
                                            <option value="completed" <?php if ($status === 'completed') echo 'selected'; ?>>Completed</option>
                                            <option value="cancelled" <?php if ($status === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-fes-red text-white text-sm font-semibold hover:bg-red-700">Update Status</button>
                                    </form>
                                </div>
                            </div>
                        </div>
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
                sidebar.classList.add('show');
                overlay.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.remove('show');
                overlay.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function () {
                var isOpen = sidebar.classList.contains('show');
                if (isOpen) closeSidebar();
                else openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    closeSidebar();
                }
            });
        })();

        // Initialize field map if polygon data exists
        <?php if (!empty($booking['field_polygon'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                var polygonData = <?php echo json_encode($booking['field_polygon']); ?>;
                var mapContainer = document.getElementById('field-map');
                
                if (mapContainer && polygonData) {
                    // Parse polygon coordinates
                    var coordinates = JSON.parse(polygonData);
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

                        // Add the polygon to the map
                        var polygonData = {
                            type: 'Feature',
                            geometry: {
                                type: 'Polygon',
                                coordinates: [coordinates]
                            },
                            properties: {
                                name: 'Field Area'
                            }
                        };

                        // Add polygon source
                        map.addSource('fes-field-polygon', {
                            type: 'geojson',
                            data: polygonData
                        });

                        // Add polygon fill layer
                        map.addLayer({
                            id: 'fes-field-polygon-layer',
                            type: 'fill',
                            source: 'fes-field-polygon',
                            paint: {
                                'fill-color': '#D32F2F',
                                'fill-opacity': 0.3
                            }
                        });

                        // Add polygon outline layer
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

                        // Add a marker at the center
                        var marker = new tt.Marker()
                            .setLngLat([center.lng, center.lat])
                            .addTo(map);

                        var popup = new tt.Popup({ offset: 30 })
                            .setHTML('<b>Field Location</b><br>Size: <?php echo htmlspecialchars($booking['field_hectares'] ?? '0'); ?> hectares')
                            .addTo(map);

                        marker.setPopup(popup);

                        // Fit the map to the polygon bounds
                        map.fitBounds(bounds, { padding: 20 });
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
