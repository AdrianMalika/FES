<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has customer role
if ($_SESSION['role'] !== 'customer') {
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'operator':
            header('Location: ../operator/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

require_once __DIR__ . '/../../includes/database.php';

$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;
// FES Depot Configuration
define('FES_DEPOT_LAT', -15.791381197859343);
define('FES_DEPOT_LNG', 35.00946109783795);
define('FES_DEPOT_ADDRESS', 'Kaoshiung, Blantyre, Malawi');

// Pricing Configuration (shared with booking confirmation)
$RATES = [
    'transport_per_km' => 5000,
    'operator_per_hour' => 6000,
    'base_fee' => 15000,
    'equipment' => [
        'tractor' => ['hourly' => 25000, 'areas' => 15000, 'daily' => 180000],
        'plow' => ['hourly' => 15000, 'areas' => 8000, 'daily' => 100000],
        'harvester' => ['hourly' => 35000, 'areas' => 20000, 'daily' => 250000],
        'irrigation' => ['hourly' => 20000, 'areas' => 12000, 'daily' => 140000],
        'default' => ['hourly' => 18000, 'areas' => 10000, 'daily' => 120000]
    ]
];

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371;
    $latDiff = deg2rad($lat2 - $lat1);
    $lngDiff = deg2rad($lng2 - $lng1);
    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lngDiff / 2) * sin($lngDiff / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function getEquipmentRates($category, $hourlyRate, $perAcreRate, $dailyRate, $rates) {
    $category = strtolower($category);
    $dbHourlyRate = floatval($hourlyRate ?? 0);
    $dbPerHectareRate = floatval($perAcreRate ?? 0);
    $dbDailyRate = floatval($dailyRate ?? 0);

    foreach ($rates['equipment'] as $key => $rate) {
        if ($key !== 'default' && strpos($category, $key) !== false) {
            $fallback = $rate;
            break;
        }
    }
    if (!isset($fallback)) {
        $fallback = $rates['equipment']['default'];
    }

    $hourly = $dbHourlyRate > 0 ? $dbHourlyRate : $fallback['hourly'];
    $areas = $dbPerHectareRate > 0 ? $dbPerHectareRate : ($dbHourlyRate > 0 ? $dbHourlyRate * 0.5 : $fallback['areas']);
    $daily = $dbDailyRate > 0 ? $dbDailyRate : ($dbHourlyRate > 0 ? $dbHourlyRate * 8 * 0.9 : $fallback['daily']);

    return [
        'hourly' => $hourly,
        'areas' => $areas,
        'daily' => $daily
    ];
}

function calculateBookingCost($booking, $rates) {
    $serviceDays = max(1, intval($booking['service_days'] ?? 1));
    $serviceHoursPerDay = 8;
    $serviceHours = $serviceDays * $serviceHoursPerDay;

    $distance = 0;
    $travelCost = 0;
    if (!empty($booking['field_lat']) && !empty($booking['field_lng'])) {
        $distance = calculateDistance(
            FES_DEPOT_LAT,
            FES_DEPOT_LNG,
            floatval($booking['field_lat']),
            floatval($booking['field_lng'])
        );
        $travelCost = $distance * $rates['transport_per_km'];
    }

    $equipmentRates = getEquipmentRates(
        $booking['category'] ?? '',
        $booking['hourly_rate'] ?? 0,
        $booking['per_hectare_rate'] ?? 0,
        $booking['daily_rate'] ?? 0,
        $rates
    );

    $landAreas = floatval($booking['field_hectares'] ?? 0);
    $equipmentCost = 0;
    $pricingModel = 'per_hour';

    if ($landAreas > 0) {
        $equipmentCost = $landAreas * $equipmentRates['areas'] * $serviceDays;
        $pricingModel = 'per_area';
        $minimumEquipmentCost = max(25000, $equipmentRates['areas'] * 1) * $serviceDays;
        if ($equipmentCost < $minimumEquipmentCost) {
            $equipmentCost = $minimumEquipmentCost;
            $pricingModel = 'minimum_charge';
        }
    } else {
        $equipmentCost = $serviceHours * $equipmentRates['hourly'];
    }

    $operatorCost = $serviceHours * $rates['operator_per_hour'];
    $baseFee = $rates['base_fee'];
    $totalCost = $equipmentCost + $operatorCost + $travelCost + $baseFee;

    return [
        'distance_km' => round($distance, 2),
        'travel_cost' => $travelCost,
        'equipment_cost' => $equipmentCost,
        'operator_cost' => $operatorCost,
        'base_fee' => $baseFee,
        'total_cost' => $totalCost,
        'service_days' => $serviceDays,
        'service_hours' => $serviceHours,
        'land_area_areas' => $landAreas,
        'rate_per_area' => $equipmentRates['areas'],
        'rate_per_hour' => $equipmentRates['hourly'],
        'pricing_model' => $pricingModel,
        'minimum_cost' => isset($minimumEquipmentCost) ? $minimumEquipmentCost : null
    ];
}

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.*, e.equipment_name, e.category, e.location AS equipment_location, e.daily_rate, e.hourly_rate, e.per_hectare_rate, e.status AS equipment_status
                FROM bookings b
                JOIN equipment e ON e.equipment_id = b.equipment_id
                WHERE b.booking_id = ? AND b.customer_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $customerId = intval($_SESSION['user_id']);
            $stmt->bind_param('ii', $bookingId, $customerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $booking = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Booking details error: ' . $e->getMessage());
    }
}

$customerName = $_SESSION['name'] ?? 'Customer';
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
if (empty($serviceLocation) && !empty($booking['field_address'])) {
    $serviceLocation = $booking['field_address'];
}
$costBreakdown = $booking ? calculateBookingCost($booking, $RATES) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
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
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
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
        #fes-booking-details-map {
            height: 360px;
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="min-h-screen w-full bg-gray-100">
        <div class="flex min-h-screen">
            <?php include __DIR__ . '/include/sidebar.php'; ?>

            <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

            <div class="flex-1 flex flex-col min-w-0 md:ml-64">
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Customer</div>
                            <h1 class="text-xl font-semibold text-gray-900">Booking Details</h1>
                            <p class="text-xs text-gray-500 mt-1">Welcome back, <?php echo htmlspecialchars($customerName); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="bookings.php" class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 font-medium px-4 py-2 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-arrow-left"></i>
                            Back to Bookings
                        </a>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6">
                    <?php if (!$booking): ?>
                        <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                            Booking not found or you don’t have access.
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
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Service Date</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900">
                                    <?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">Days: <?php echo htmlspecialchars((string)($booking['service_days'] ?? 1)); ?></div>
                            </div>
                            <div class="bg-white rounded-xl shadow-card p-5">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Estimated Total</div>
                                <div class="mt-2 text-2xl font-semibold text-fes-red">
                                    MK <?php echo number_format((float)($booking['estimated_total_cost'] ?? 0)); ?>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">Service: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                                <button type="button" id="toggle-cost-breakdown" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-receipt"></i>
                                    View cost breakdown
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-6">
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
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Location</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($serviceLocation ?: 'N/A'); ?></div>
                                        <?php if (!empty($booking['field_address']) && $serviceLocation !== $booking['field_address']): ?>
                                            <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($booking['field_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contact Phone</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['contact_phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Field Size</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['field_hectares'] ?? 'Not specified'); ?> acres</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Notes</div>
                                        <div class="text-gray-700"><?php echo !empty($booking['notes']) ? htmlspecialchars($booking['notes']) : '—'; ?></div>
                                    </div>
                                </div>
                            </section>

                            <section class="bg-white rounded-xl shadow-card p-6">
                                <h2 class="text-base font-semibold text-gray-900 mb-4">Timeline</h2>
                                <div class="space-y-4 text-sm text-gray-600">
                                    <div class="flex items-center gap-3">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <div>Submitted: <?php echo !empty($booking['created_at']) ? htmlspecialchars(date('M d, Y · H:i', strtotime($booking['created_at']))) : 'N/A'; ?></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="h-2 w-2 rounded-full bg-gray-300"></span>
                                        <div>Last Update: <?php echo !empty($booking['updated_at']) ? htmlspecialchars(date('M d, Y · H:i', strtotime($booking['updated_at']))) : 'N/A'; ?></div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <section class="bg-white rounded-xl shadow-card p-6 mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-base font-semibold text-gray-900">Service Location Map</h2>
                                <div class="text-xs text-gray-500">
                                    <?php if (!empty($booking['field_lat']) && !empty($booking['field_lng'])): ?>
                                        Lat <?php echo htmlspecialchars((string)$booking['field_lat']); ?>, Lng <?php echo htmlspecialchars((string)$booking['field_lng']); ?>
                                    <?php else: ?>
                                        No coordinates saved
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (empty($booking['field_lat']) || empty($booking['field_lng'])): ?>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600">
                                    This booking does not have a saved map location.
                                </div>
                            <?php else: ?>
                                <div id="fes-booking-details-map"></div>
                                <?php if (!empty($booking['field_address'])): ?>
                                    <div class="mt-3 text-sm text-gray-600">
                                        <span class="font-medium text-gray-800">Address:</span>
                                        <?php echo htmlspecialchars($booking['field_address']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>

                        <section id="cost-breakdown-panel" class="hidden bg-white rounded-xl shadow-card p-6 mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-base font-semibold text-gray-900">Cost Breakdown</h2>
                                <button type="button" id="close-cost-breakdown" class="text-xs font-semibold text-gray-500 hover:text-gray-700">Hide</button>
                            </div>
                            <?php if (!$costBreakdown): ?>
                                <div class="text-sm text-gray-600">Cost breakdown is unavailable for this booking.</div>
                            <?php else: ?>
                                <div class="space-y-4 text-sm">
                                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                                        <span class="text-gray-700 font-medium">Equipment Cost:</span>
                                        <span class="font-bold text-gray-900">
                                            MK <?php echo number_format($costBreakdown['equipment_cost'] ?? 0); ?>
                                            <?php if ($costBreakdown['pricing_model'] === 'per_area'): ?>
                                                <span class="text-xs font-normal text-gray-600">
                                                    (<?php echo htmlspecialchars((string)$costBreakdown['land_area_areas']); ?> acres x MK <?php echo number_format($costBreakdown['rate_per_area']); ?>/acre<?php if (($costBreakdown['service_days'] ?? 1) > 1) echo ' x ' . htmlspecialchars((string)$costBreakdown['service_days']) . ' days'; ?>)
                                                </span>
                                            <?php elseif ($costBreakdown['pricing_model'] === 'minimum_charge'): ?>
                                                <span class="text-xs font-normal text-gray-600">(Minimum charge applied: MK <?php echo number_format($costBreakdown['minimum_cost'] ?? 0); ?>)</span>
                                            <?php else: ?>
                                                <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars((string)$costBreakdown['service_hours']); ?> hrs x MK <?php echo number_format($costBreakdown['rate_per_hour']); ?>/hr)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                                        <span class="text-gray-700 font-medium">Operator Cost:</span>
                                        <span class="font-bold text-gray-900">
                                            MK <?php echo number_format($costBreakdown['operator_cost'] ?? 0); ?>
                                            <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars((string)($costBreakdown['service_hours'] ?? 8)); ?> hrs x MK <?php echo number_format($RATES['operator_per_hour']); ?>/hr)</span>
                                        </span>
                                    </div>

                                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                                        <span class="text-gray-700 font-medium">Travel Cost:</span>
                                        <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['travel_cost'] ?? 0); ?> <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars((string)($costBreakdown['distance_km'] ?? 0)); ?> km x MK 5,000/km)</span></span>
                                    </div>

                                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                                        <span class="text-gray-700 font-medium">Base Fee:</span>
                                        <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['base_fee'] ?? 0); ?></span>
                                    </div>

                                    <div class="flex justify-between items-center pt-4">
                                        <span class="text-base font-bold text-gray-900">Estimated Total:</span>
                                        <span class="text-2xl font-bold text-fes-red">MK <?php echo number_format($costBreakdown['total_cost'] ?? 0); ?></span>
                                    </div>
                                </div>

                                <?php
                                $storedTotal = floatval($booking['estimated_total_cost'] ?? 0);
                                $calcTotal = floatval($costBreakdown['total_cost'] ?? 0);
                                if ($storedTotal > 0 && abs($storedTotal - $calcTotal) > 1):
                                ?>
                                    <div class="mt-4 text-xs text-gray-500">
                                        Note: The saved estimate for this booking is MK <?php echo number_format($storedTotal); ?>. Rates may have changed since you booked.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </main>
            </div>
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

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    closeSidebar();
                }
            });
        })();

        (function () {
            var toggleBtn = document.getElementById('toggle-cost-breakdown');
            var closeBtn = document.getElementById('close-cost-breakdown');
            var panel = document.getElementById('cost-breakdown-panel');
            if (!toggleBtn || !panel) return;

            toggleBtn.addEventListener('click', function () {
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    panel.classList.add('hidden');
                });
            }
        })();
    </script>

    <!-- TomTom Maps JS (read-only display) -->
    <script src="https://api.tomtom.com/maps-sdk-for-web/cdn/6.x/6.25.0/maps/maps-web.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var mapEl = document.getElementById('fes-booking-details-map');
        if (!mapEl) return;
        if (typeof tt === 'undefined') {
            mapEl.innerHTML = '<div class="h-full w-full flex items-center justify-center text-sm text-gray-500">Map failed to load.</div>';
            return;
        }

        var lat = <?php echo json_encode(!empty($booking['field_lat']) ? (float)$booking['field_lat'] : null); ?>;
        var lng = <?php echo json_encode(!empty($booking['field_lng']) ? (float)$booking['field_lng'] : null); ?>;
        if (lat === null || lng === null) return;

        var polygonCoords = <?php echo json_encode(!empty($booking['field_polygon']) ? $booking['field_polygon'] : ''); ?>;
        var parsedPoly = [];
        try {
            if (polygonCoords) parsedPoly = JSON.parse(polygonCoords);
        } catch (e) {
            parsedPoly = [];
        }

        // Use satellite imagery tiles (same style as booking.php)
        var map = tt.map({
            key: 'UeDQhUcZNKjtuImgABKQ1oqKPZglpVJ0',
            container: 'fes-booking-details-map',
            center: [lng, lat],
            zoom: 14,
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

        // Marker for the saved point
        new tt.Marker({ color: '#D32F2F' }).setLngLat([lng, lat]).addTo(map);

        // Draw polygon if available (array of [lng,lat])
        if (Array.isArray(parsedPoly) && parsedPoly.length >= 3) {
            var closed = parsedPoly.concat([parsedPoly[0]]);
            var geojson = {
                type: 'FeatureCollection',
                features: [{
                    type: 'Feature',
                    geometry: { type: 'Polygon', coordinates: [closed] },
                    properties: {}
                }]
            };

            map.on('load', function () {
                map.addSource('fes-booking-field', { type: 'geojson', data: geojson });
                map.addLayer({
                    id: 'fes-booking-field-fill',
                    type: 'fill',
                    source: 'fes-booking-field',
                    paint: {
                        'fill-color': 'rgba(211,47,47,0.22)',
                        'fill-outline-color': '#D32F2F'
                    }
                });

                // Fit bounds to polygon
                try {
                    var bounds = new tt.LngLatBounds();
                    closed.forEach(function (pt) { bounds.extend(pt); });
                    map.fitBounds(bounds, { padding: 40, maxZoom: 18 });
                } catch (e) {}
            });
        } else {
            map.on('load', function () {
                map.setZoom(16);
            });
        }

        // Make map feel read-only (no scroll zoom surprises in dashboard)
        map.scrollZoom.disable();
    });
    </script>
</body>
</html>

