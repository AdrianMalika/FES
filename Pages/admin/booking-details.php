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
$flashSuccess = $_SESSION['success'] ?? '';
$flashError = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
$booking = null;
$operators = [];
$busyOperators = [];

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
                       e.id AS equipment_row_id, e.equipment_name, e.category, e.location AS equipment_location, e.daily_rate, e.hourly_rate, e.per_hectare_rate, e.status AS equipment_status,
                       u.name AS customer_name, u.email AS customer_email,
                       b.operator_id AS booking_operator_id,
                       op.name AS operator_name
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
                LEFT JOIN users u ON u.user_id = b.customer_id
                LEFT JOIN users op ON op.user_id = b.operator_id
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

if ($booking) {
    try {
        $conn = getDBConnection();

        $opSql = "SELECT user_id, name FROM users WHERE role = 'operator' ORDER BY name ASC";
        $opRes = $conn->query($opSql);
        if ($opRes) {
            while ($row = $opRes->fetch_assoc()) {
                $operators[] = $row;
            }
        }

        $busySql = "SELECT b.operator_id, b.booking_id, b.equipment_id, e.equipment_name
                    FROM bookings b
                    LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
                    WHERE b.operator_id IS NOT NULL";
        $busyRes = $conn->query($busySql);
        if ($busyRes) {
            while ($row = $busyRes->fetch_assoc()) {
                $busyOperators[(int)$row['operator_id']] = [
                    'equipment_id' => $row['equipment_id'],
                    'equipment_name' => $row['equipment_name'],
                    'booking_id' => (int)$row['booking_id']
                ];
            }
        }

        $conn->close();
    } catch (Exception $e) {
        error_log('Admin booking detail operators fetch error: ' . $e->getMessage());
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
$costBreakdown = $booking ? calculateBookingCost($booking, $RATES) : null;
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
                <?php if (!empty($flashSuccess)): ?>
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <?php echo htmlspecialchars($flashSuccess); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <?php echo htmlspecialchars($flashError); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$booking): ?>
                    <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                        Booking not found.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6 mb-6">
                        <section class="xl:col-span-3 bg-white rounded-xl shadow-card p-6">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider">Booking</div>
                                    <div class="mt-2 text-2xl font-semibold text-gray-900">#BK-<?php echo htmlspecialchars((string)$booking['booking_id']); ?></div>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="md:text-right">
                                    <div class="text-xs text-gray-500 uppercase tracking-wider">Customer</div>
                                    <div class="mt-2 text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></div>
                                    <div class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($booking['customer_email'] ?? ''); ?></div>
                                </div>
                            </div>

                            <div class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Date</div>
                                    <div class="text-gray-900 font-medium"><?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Days</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars((string)($booking['service_days'] ?? 1)); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Type</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_name'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(ucfirst($booking['category'] ?? '')); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Assigned Operator</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['operator_name'] ?? 'Unassigned'); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contact Phone</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['contact_phone'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        </section>

                        <section class="bg-white rounded-xl shadow-card p-6 flex flex-col justify-between">
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Estimated Total</div>
                                <div class="mt-2 text-3xl font-semibold text-fes-red">MK <?php echo number_format((float)($booking['estimated_total_cost'] ?? 0)); ?></div>
                                <div class="mt-1 text-sm text-gray-600">Service: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                            </div>
                            <div class="mt-6 text-xs text-gray-500">
                                Status: <span class="font-medium text-gray-900"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></span>
                            </div>
                        </section>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <section class="xl:col-span-2 bg-white rounded-xl shadow-card p-6">
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
                        
                        <section class="bg-white rounded-xl shadow-card p-6">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Booking Information</h2>
                            <div class="grid grid-cols-1 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment Location</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_location'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['equipment_status'] ?? 'N/A'))); ?></div>
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
                    </div>

                    <div class="bg-white rounded-xl shadow-card p-6 mt-6">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Cost Breakdown</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
                            <div class="space-y-3">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Service & Field</div>
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
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['field_hectares'] ?? '0'); ?> acres</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Distance From Depot</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars((string)($costBreakdown['distance_km'] ?? 0)); ?> km</span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Rates</div>
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
                                    <span class="text-sm text-gray-600">Per Acre Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$booking['per_hectare_rate']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Operator Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$RATES['operator_per_hour']); ?>/hr</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Transport Rate</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$RATES['transport_per_km']); ?>/km</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-sm text-gray-600">Base Fee</span>
                                    <span class="text-sm font-medium text-gray-900">MK <?php echo number_format((float)$RATES['base_fee']); ?></span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Costs</div>
                                <div class="flex justify-between items-center pb-3 border-b border-gray-100 text-sm">
                                    <span class="text-gray-700 font-medium">Equipment Cost</span>
                                    <span class="font-bold text-gray-900">
                                        MK <?php echo number_format($costBreakdown['equipment_cost'] ?? 0); ?>
                                        <?php if (($costBreakdown['pricing_model'] ?? '') === 'per_area'): ?>
                                            <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars((string)$costBreakdown['land_area_areas']); ?> acres x MK <?php echo number_format($costBreakdown['rate_per_area']); ?>/acre<?php if (($costBreakdown['service_days'] ?? 1) > 1) echo ' x ' . htmlspecialchars((string)$costBreakdown['service_days']) . ' days'; ?>)</span>
                                        <?php elseif (($costBreakdown['pricing_model'] ?? '') === 'minimum_charge'): ?>
                                            <span class="text-xs font-normal text-gray-600">(Minimum charge applied: MK <?php echo number_format($costBreakdown['minimum_cost'] ?? 0); ?>)</span>
                                        <?php else: ?>
                                            <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars((string)($costBreakdown['service_hours'] ?? 8)); ?> hrs x MK <?php echo number_format($costBreakdown['rate_per_hour']); ?>/hr)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between items-center pb-3 border-b border-gray-100 text-sm">
                                    <span class="text-gray-700 font-medium">Operator Cost</span>
                                    <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['operator_cost'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between items-center pb-3 border-b border-gray-100 text-sm">
                                    <span class="text-gray-700 font-medium">Travel Cost</span>
                                    <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['travel_cost'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between items-center pb-3 border-b border-gray-100 text-sm">
                                    <span class="text-gray-700 font-medium">Base Fee</span>
                                    <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['base_fee'] ?? 0); ?></span>
                                </div>
                                <div class="flex justify-between items-center pt-1">
                                    <span class="text-base font-semibold text-gray-900">Estimated Total Cost</span>
                                    <span class="text-lg font-bold text-fes-red">MK <?php echo number_format((float)($costBreakdown['total_cost'] ?? 0)); ?></span>
                                </div>
                                <?php
                                $storedTotal = floatval($booking['estimated_total_cost'] ?? 0);
                                $calcTotal = floatval($costBreakdown['total_cost'] ?? 0);
                                if ($storedTotal > 0 && abs($storedTotal - $calcTotal) > 1):
                                ?>
                                    <div class="text-xs text-gray-500">
                                        Saved estimate for this booking is MK <?php echo number_format($storedTotal); ?>. Rates may have changed since booking.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Actions</div>
                                <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                                    <form method="post" action="include/process_assign_booking_operator.php" class="space-y-3">
                                        <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars((string)$booking['booking_id']); ?>">
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Assign Operator</div>
                                        <select name="operator_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                            <option value="">Unassigned</option>
                                            <?php
                                                $currentOperatorId = (int)($booking['booking_operator_id'] ?? 0);
                                            ?>
                                            <?php foreach ($operators as $op): ?>
                                                <?php
                                                    $opId = (int)$op['user_id'];
                                                    $isCurrent = ($opId === $currentOperatorId);
                                                    $isBusyElsewhere = isset($busyOperators[$opId]) && ($busyOperators[$opId]['booking_id'] ?? 0) !== (int)($booking['booking_id'] ?? 0);
                                                    $busyLabel = $isBusyElsewhere
                                                        ? ' (Assigned to ' . $busyOperators[$opId]['equipment_id'] . ')'
                                                        : '';
                                                ?>
                                                <option value="<?php echo $opId; ?>"
                                                    <?php echo $isCurrent ? 'selected' : ''; ?>
                                                    <?php echo $isBusyElsewhere ? 'disabled' : ''; ?>>
                                                    <?php echo htmlspecialchars($op['name'] . $busyLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="w-full px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-50">Update Operator</button>
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

                        map.on('load', function () {
                            // Add the polygon to the map
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

                            // Add polygon source
                            map.addSource('fes-field-polygon', {
                                type: 'geojson',
                                data: polygonFeature
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
                                .setHTML('<b>Field Location</b><br>Size: <?php echo htmlspecialchars($booking['field_hectares'] ?? '0'); ?> Acres')
                                .addTo(map);

                            marker.setPopup(popup);

                            // Fit the map to the polygon bounds
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

