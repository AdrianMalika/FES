<?php
session_start();

// Only customers can access this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: auth/signin.php?redirect=equipment.php');
    exit();
}

// Booking confirmation flow:
// 1) POST from booking.php -> store in session and show review
// 2) POST with confirm_booking -> show success and clear session
$pendingKey = 'pending_booking';
$isConfirmPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isConfirmPost && !empty($_POST)) {
    // Normalize location field for display/persistence
    if (empty($_POST['service_location'])) {
        if (!empty($_POST['field_address'])) {
            $_POST['service_location'] = $_POST['field_address'];
        } elseif (!empty($_POST['field_lat']) && !empty($_POST['field_lng'])) {
            $_POST['service_location'] = 'Lat ' . $_POST['field_lat'] . ', Lng ' . $_POST['field_lng'];
        }
    }
    $_SESSION[$pendingKey] = $_POST;
}

if (empty($_SESSION[$pendingKey]) && ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST))) {
    header('Location: equipment.php');
    exit();
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// FES Depot Configuration
define('FES_DEPOT_LAT', -15.791381197859343);
define('FES_DEPOT_LNG', 35.00946109783795);
define('FES_DEPOT_ADDRESS', 'Kaoshiung, Blantyre, Malawi');

// Rates Configuration
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

// Calculate distance using Haversine formula
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

// Get equipment rates based on category and database values
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

// Calculate comprehensive cost
function calculateBookingCost($equipment, $fieldLat, $fieldLng, $fieldAreas, $serviceDays, $rates) {
    $costBreakdown = [];
    $serviceDays = max(1, intval($serviceDays));
    $serviceHoursPerDay = 8;
    $serviceHours = $serviceDays * $serviceHoursPerDay;
    
    // Calculate travel distance and cost
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
    
    // Get equipment rates
    $equipmentRates = getEquipmentRates(
        $equipment['category'] ?? '',
        $equipment['hourly_rate'] ?? 0,
        $equipment['per_hectare_rate'] ?? 0,
        $equipment['daily_rate'] ?? 0,
        $rates
    );
    
    // Calculate equipment cost
    $equipmentCost = 0;
    $serviceDuration = $serviceHours;
    $landAreas = floatval($fieldAreas ?? 0);
    $costBreakdown['service_days'] = $serviceDays;
    $costBreakdown['service_hours'] = $serviceHours;
    
    if ($landAreas > 0) {
        $equipmentCost = $landAreas * $equipmentRates['areas'] * $serviceDays;
        $costBreakdown['pricing_model'] = 'per_area';
        $costBreakdown['land_area_areas'] = $landAreas;
        $costBreakdown['rate_per_area'] = $equipmentRates['areas'];
        $costBreakdown['service_days'] = $serviceDays;
        
        $minimumEquipmentCost = max(25000, $equipmentRates['areas'] * 1) * $serviceDays;
        if ($equipmentCost < $minimumEquipmentCost) {
            $equipmentCost = $minimumEquipmentCost;
            $costBreakdown['pricing_model'] = 'minimum_charge';
            $costBreakdown['minimum_cost'] = $minimumEquipmentCost;
        }
    } else {
        $equipmentCost = $serviceDuration * $equipmentRates['hourly'];
        $costBreakdown['pricing_model'] = 'per_hour';
        $costBreakdown['service_hours'] = $serviceDuration;
        $costBreakdown['rate_per_hour'] = $equipmentRates['hourly'];
    }
    
    $costBreakdown['equipment_cost'] = $equipmentCost;
    $costBreakdown['operator_cost'] = $serviceDuration * $rates['operator_per_hour'];
    $costBreakdown['base_fee'] = $rates['base_fee'];
    $costBreakdown['total_cost'] = $equipmentCost + $costBreakdown['operator_cost'] + $travelCost + $rates['base_fee'];
    
    return $costBreakdown;
}

// Get booking data
$bookingData = $_SESSION[$pendingKey] ?? $_POST;
$equipmentId = $bookingData['equipment_id'] ?? '';
$serviceDays = max(1, intval($bookingData['service_days'] ?? 1));
$equipment = null;

if ($equipmentId) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT equipment_id, equipment_name, category, location, daily_rate, hourly_rate, per_hectare_rate, status, description
                FROM equipment
                WHERE equipment_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $equipmentId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $equipment = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Booking confirmation error: ' . $e->getMessage());
    }
}

// Calculate cost breakdown
$costBreakdown = [];
if ($equipment && $bookingData) {
    $costBreakdown = calculateBookingCost(
        $equipment, 
        $bookingData['field_lat'] ?? '', 
        $bookingData['field_lng'] ?? '', 
        $bookingData['field_hectares'] ?? '',
        $serviceDays,
        $RATES
    );
}

$customerName = $_SESSION['name'] ?? 'Customer';
$isConfirmed = $isConfirmPost && !empty($bookingData);
$createdBookingId = null;
$bookingStatus = 'pending';
$displayLocation = trim($bookingData['service_location'] ?? '');
if ($displayLocation === '' && !empty($bookingData['field_address'])) {
    $displayLocation = $bookingData['field_address'];
} elseif ($displayLocation === '' && !empty($bookingData['field_lat']) && !empty($bookingData['field_lng'])) {
    $displayLocation = 'Lat ' . $bookingData['field_lat'] . ', Lng ' . $bookingData['field_lng'];
}

// Persist booking on confirm (idempotent by session hash)
if ($isConfirmed && $equipment && $bookingData) {
    $bookingHash = hash('sha256', json_encode($bookingData));
    $existingHash = $_SESSION['last_booking_hash'] ?? '';
    $existingId = $_SESSION['last_booking_id'] ?? null;

    if ($existingHash === $bookingHash && !empty($existingId)) {
        $createdBookingId = $existingId;
    } else {
        try {
            $conn = getDBConnection();
            $sql = "INSERT INTO bookings (
                        customer_id, equipment_id, booking_date, service_days, service_type,
                        service_location, contact_phone, field_lat, field_lng, field_address,
                        field_polygon, field_hectares, notes, estimated_total_cost, status, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, NOW(), NOW()
                    )";
            if ($stmt = $conn->prepare($sql)) {
                $customerId = intval($_SESSION['user_id']);
                $equipmentId = $bookingData['equipment_id'] ?? '';
                $bookingDate = $bookingData['booking_date'] ?? null;
                $serviceType = $bookingData['service_type'] ?? '';
                $serviceLocation = $bookingData['service_location'] ?? '';
                if ($serviceLocation === '' && !empty($bookingData['field_address'])) {
                    $serviceLocation = $bookingData['field_address'];
                }
                $contactPhone = $bookingData['contact_phone'] ?? '';
                $fieldLat = !empty($bookingData['field_lat']) ? floatval($bookingData['field_lat']) : null;
                $fieldLng = !empty($bookingData['field_lng']) ? floatval($bookingData['field_lng']) : null;
                $fieldAddress = $bookingData['field_address'] ?? '';
                $fieldPolygon = $bookingData['field_polygon'] ?? '';
                $fieldAcres = !empty($bookingData['field_hectares']) ? floatval($bookingData['field_hectares']) : null;
                $notes = $bookingData['notes'] ?? '';
                $estimatedTotal = $costBreakdown['total_cost'] ?? 0;

                $stmt->bind_param(
                    'ississsddssdsds',
                    $customerId,
                    $equipmentId,
                    $bookingDate,
                    $serviceDays,
                    $serviceType,
                    $serviceLocation,
                    $contactPhone,
                    $fieldLat,
                    $fieldLng,
                    $fieldAddress,
                    $fieldPolygon,
                    $fieldAcres,
                    $notes,
                    $estimatedTotal,
                    $bookingStatus
                );

                if ($stmt->execute()) {
                    $createdBookingId = $stmt->insert_id;
                    $_SESSION['last_booking_id'] = $createdBookingId;
                    $_SESSION['last_booking_hash'] = $bookingHash;

                    // Email admin(s) about new booking (send once per booking id)
                    $alreadySentFor = (int)($_SESSION['last_booking_admin_email_sent_id'] ?? 0);
                    if (!empty($createdBookingId) && $alreadySentFor !== (int)$createdBookingId) {
                        try {
                            $config = include __DIR__ . '/../includes/email_config.php';
                            if (is_array($config) && !empty($config['host']) && !empty($config['username']) && !empty($config['from_email'])) {
                                $admins = [];
                                if ($adminStmt = $conn->prepare("SELECT name, email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email <> ''")) {
                                    $adminStmt->execute();
                                    $adminRes = $adminStmt->get_result();
                                    while ($r = $adminRes->fetch_assoc()) {
                                        $admins[] = $r;
                                    }
                                    $adminStmt->close();
                                }

                                // Always include this recipient (requested)
                                $forcedAdminEmail = 'adrianmalika01@gmail.com';

                                if (!empty($admins) || !empty($forcedAdminEmail)) {
                                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                    $adminLink = $scheme . '://' . $host . '/FES/Pages/admin/booking-details.php?id=' . urlencode((string)$createdBookingId);

                                    $mail = new PHPMailer(true);
                                    $mail->SMTPDebug = 0;
                                    $mail->Debugoutput = function ($str, $level) {
                                        error_log("PHPMailer Debug: $str");
                                    };
                                    $mail->isSMTP();
                                    $mail->Host       = $config['host'];
                                    $mail->SMTPAuth   = true;
                                    $mail->Username   = $config['username'];
                                    $mail->Password   = $config['password'];
                                    $mail->SMTPSecure = $config['encryption'];
                                    $mail->Port       = $config['port'];
                                    $mail->SMTPOptions = [
                                        'ssl' => [
                                            'verify_peer' => false,
                                            'verify_peer_name' => false,
                                            'allow_self_signed' => true
                                        ]
                                    ];
                                    $mail->Timeout = 30;

                                    $mail->setFrom($config['from_email'], $config['from_name'] ?? 'FES');
                                    $added = [];
                                    if (!empty($forcedAdminEmail)) {
                                        $mail->addAddress($forcedAdminEmail, 'Admin');
                                        $added[strtolower($forcedAdminEmail)] = true;
                                    }
                                    foreach ($admins as $a) {
                                        $email = trim((string)($a['email'] ?? ''));
                                        if ($email === '') continue;
                                        $key = strtolower($email);
                                        if (isset($added[$key])) continue;
                                        $mail->addAddress($email, $a['name'] ?? 'Admin');
                                        $added[$key] = true;
                                    }
                                    $mail->isHTML(true);

                                    $safeEquip = htmlspecialchars((string)($equipment['equipment_name'] ?? $equipmentId), ENT_QUOTES, 'UTF-8');
                                    $safeDate = htmlspecialchars((string)($bookingDate ?? ''), ENT_QUOTES, 'UTF-8');
                                    $safeDays = htmlspecialchars((string)$serviceDays, ENT_QUOTES, 'UTF-8');
                                    $safeType = htmlspecialchars((string)ucfirst(str_replace('_', ' ', $serviceType)), ENT_QUOTES, 'UTF-8');
                                    $safeLoc  = htmlspecialchars((string)($serviceLocation ?: $displayLocation), ENT_QUOTES, 'UTF-8');
                                    $safeCost = number_format((float)($estimatedTotal ?? 0));

                                    $mail->Subject = 'New booking received: BK-' . (string)$createdBookingId;
                                    $mail->Body = "
                                        <div style='font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; padding: 16px; background: #f8f9fa;'>
                                          <div style='background: #ffffff; padding: 22px; border-radius: 10px; border: 1px solid #eee;'>
                                            <h2 style='margin: 0 0 8px 0; color: #D32F2F;'>New booking received</h2>
                                            <p style='margin: 0 0 14px 0; color: #444;'>A customer has submitted a new booking request.</p>
                                            <div style='background: #f8f9fa; padding: 14px; border-radius: 8px;'>
                                              <p style='margin: 0;'><b>Booking ID:</b> BK-{$createdBookingId}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Equipment:</b> {$safeEquip}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Date:</b> {$safeDate}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Days:</b> {$safeDays}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Service type:</b> {$safeType}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Location:</b> {$safeLoc}</p>
                                              <p style='margin: 6px 0 0 0;'><b>Estimated total:</b> MK {$safeCost}</p>
                                            </div>
                                            <div style='margin-top: 16px; text-align: center;'>
                                              <a href='{$adminLink}' style='display: inline-block; padding: 12px 18px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                                                View booking
                                              </a>
                                            </div>
                                          </div>
                                        </div>
                                    ";
                                    $mail->AltBody =
                                        "New booking received\n\n" .
                                        "Booking ID: BK-{$createdBookingId}\n" .
                                        "Equipment: " . ($equipment['equipment_name'] ?? $equipmentId) . "\n" .
                                        "Date: " . ($bookingDate ?? '') . "\n" .
                                        "Days: {$serviceDays}\n" .
                                        "Service type: " . ucfirst(str_replace('_', ' ', $serviceType)) . "\n" .
                                        "Location: " . ($serviceLocation ?: $displayLocation) . "\n" .
                                        "Estimated total: MK {$safeCost}\n\n" .
                                        "View booking: {$adminLink}\n";

                                    $mail->send();
                                    $_SESSION['last_booking_admin_email_sent_id'] = (int)$createdBookingId;
                                } else {
                                    error_log('Admin booking email skipped: no admin emails found.');
                                }
                            } else {
                                error_log('Admin booking email skipped: invalid email_config.php.');
                            }
                        } catch (MailException $e) {
                            error_log('Admin booking email failed: ' . $e->getMessage());
                        } catch (Exception $e) {
                            error_log('Admin booking email error: ' . $e->getMessage());
                        }
                    }
                }
                $stmt->close();
            }
            $conn->close();
        } catch (Exception $e) {
            error_log('Booking insert error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - FES</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f5f5f5; }
        ::-webkit-scrollbar-thumb { background: var(--red); border-radius: 99px; }

        .accent-line {
            display: inline-block;
            width: 3rem;
            height: 3px;
            background: var(--red);
            margin-right: 12px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .text-fes-red { color: #D32F2F; }
        .bg-fes-red { background-color: #D32F2F; }
        .border-fes-red { border-color: #D32F2F; }
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
            <?php if ($isConfirmed): ?>
                Booking <span class="text-fes-red">Sent</span>
            <?php else: ?>
                Review &amp; <span class="text-fes-red">Confirm</span>
            <?php endif; ?>
        </h1>
        <p class="text-gray-600 text-base leading-relaxed max-w-2xl">
            <?php if ($isConfirmed): ?>
                Thank you, <span class="font-semibold text-fes-red"><?php echo htmlspecialchars($customerName); ?></span>. Your booking request has been successfully submitted.
            <?php else: ?>
                Please review your booking details below, then confirm to submit your request.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($isConfirmed): ?>
        <div class="mb-8 rounded-sm border border-green-200 bg-green-50 px-6 py-5 flex items-start gap-4">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600">
                <i class="fas fa-check"></i>
            </div>
            <div>
                <h2 class="font-display font-800 text-lg text-gray-900">We have received your request</h2>
                <p class="text-sm text-gray-600">Our team will review availability and confirm within 24 hours.</p>
                <?php if (!empty($createdBookingId)): ?>
                    <p class="text-xs text-gray-600 mt-2">
                        Booking ID: <span class="font-semibold text-gray-900">#BK-<?php echo htmlspecialchars((string)$createdBookingId); ?></span>
                        · Status: <span class="font-semibold text-amber-700">Pending</span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-8 rounded-sm border border-gray-200 bg-white px-6 py-5 flex items-start gap-4">
            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div>
                <h2 class="font-display font-800 text-lg text-gray-900">Confirm before sending</h2>
                <p class="text-sm text-gray-600">Double-check your details. You can still go back and edit.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-7">
        <!-- Booking Summary -->
        <section class="lg:col-span-2 bg-white rounded-sm overflow-hidden border border-gray-100">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="accent-line"></span>
                    <h2 class="font-display font-800 text-2xl lg:text-3xl text-gray-900" style="letter-spacing:-0.01em;">
                        Booking Summary
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Equipment</p>
                            <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($equipment['equipment_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Booking Date</p>
                            <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars($bookingData['booking_date'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Service Type</p>
                            <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $bookingData['service_type'] ?? 'N/A'))); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Service Days</p>
                            <p class="text-base font-medium text-gray-800">
                                <?php echo htmlspecialchars((string)$serviceDays); ?>
                                <?php echo $serviceDays === 1 ? ' day' : ' days'; ?>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Service Location</p>
                            <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars($displayLocation ?: 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Contact Phone</p>
                            <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars($bookingData['contact_phone'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Field Area</p>
                            <p class="text-base font-medium text-gray-800"><?php echo htmlspecialchars($bookingData['field_hectares'] ?? 'Not specified'); ?> acres</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($bookingData['notes'])): ?>
                    <div class="p-4 bg-gray-50 rounded-sm border border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Special Instructions</p>
                        <p class="text-gray-700 text-sm leading-relaxed"><?php echo htmlspecialchars($bookingData['notes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Cost Breakdown -->
        <section class="bg-white rounded-sm overflow-hidden border border-gray-100">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-6">
                    <span class="accent-line"></span>
                    <h2 class="font-display font-800 text-2xl text-gray-900" style="letter-spacing:-0.01em;">
                        Cost Breakdown
                    </h2>
                </div>

                <div class="space-y-4 text-sm">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-700 font-medium">Equipment Cost:</span>
                        <span class="font-bold text-gray-900">
                            MK <?php echo number_format($costBreakdown['equipment_cost'] ?? 0); ?>
                            <?php if (!empty($costBreakdown['pricing_model']) && $costBreakdown['pricing_model'] === 'per_area'): ?>
                                <span class="text-xs font-normal text-gray-600">
                                    (<?php echo htmlspecialchars($costBreakdown['land_area_areas']); ?> acres x MK <?php echo number_format($costBreakdown['rate_per_area']); ?>/acre<?php if (($costBreakdown['service_days'] ?? 1) > 1) echo ' x ' . htmlspecialchars((string)$costBreakdown['service_days']) . ' days'; ?>)
                                </span>
                            <?php elseif (!empty($costBreakdown['pricing_model']) && $costBreakdown['pricing_model'] === 'minimum_charge'): ?>
                                <span class="text-xs font-normal text-gray-600">(Minimum charge: MK <?php echo number_format($costBreakdown['minimum_cost']); ?>)</span>
                            <?php else: ?>
                                <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars($costBreakdown['service_hours']); ?> hrs x MK <?php echo number_format($costBreakdown['rate_per_hour']); ?>/hr)</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-700 font-medium">Operator Cost:</span>
                        <span class="font-bold text-gray-900">
                            MK <?php echo number_format($costBreakdown['operator_cost'] ?? 0); ?>
                            <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars($costBreakdown['service_hours'] ?? 8); ?> hrs x MK <?php echo number_format($RATES['operator_per_hour']); ?>/hr)</span>
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-700 font-medium">Travel Cost:</span>
                        <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['travel_cost'] ?? 0); ?> <span class="text-xs font-normal text-gray-600">(<?php echo htmlspecialchars($costBreakdown['distance_km']); ?> km x MK 5,000/km)</span></span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                        <span class="text-gray-700 font-medium">Base Fee:</span>
                        <span class="font-bold text-gray-900">MK <?php echo number_format($costBreakdown['base_fee'] ?? 0); ?></span>
                    </div>

                    <div class="flex justify-between items-center pt-4">
                        <span class="text-base font-bold text-gray-900">Total Estimated Cost:</span>
                        <span class="text-2xl font-bold text-fes-red">MK <?php echo number_format($costBreakdown['total_cost'] ?? 0); ?></span>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-gray-50 rounded-sm border border-gray-100">
                    <p class="text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-2"></i>
                        Cost calculated from <?php echo htmlspecialchars($costBreakdown['distance_km']); ?> km distance from FES depot (Kaoshiung, Blantyre) to <?php echo htmlspecialchars($displayLocation ?: 'your location'); ?>.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <!-- Next Steps -->
    <section class="bg-white rounded-sm overflow-hidden border border-gray-100 mt-10">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <span class="accent-line"></span>
                <h2 class="font-display font-800 text-2xl lg:text-3xl text-gray-900" style="letter-spacing:-0.01em;">
                    Next Steps
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-green-600 font-bold text-sm">1</span>
                    </div>
                    <div>
                        <h3 class="font-display font-700 text-lg text-gray-900 mb-2">Booking Review</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Our team will review your booking request and equipment availability.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-green-600 font-bold text-sm">2</span>
                    </div>
                    <div>
                        <h3 class="font-display font-700 text-lg text-gray-900 mb-2">Contact Within 24 Hours</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">We'll call you at <?php echo htmlspecialchars($bookingData['contact_phone']); ?> to confirm details and discuss payment.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-green-600 font-bold text-sm">3</span>
                    </div>
                    <div>
                        <h3 class="font-display font-700 text-lg text-gray-900 mb-2">Service Deployment</h3>
                        <p class="text-gray-600 text-sm leading-relaxed">Equipment will be deployed to your location on the scheduled date.</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 p-4 bg-green-50 rounded-sm border border-green-200">
                <p class="text-sm text-green-800">
                    <i class="fas fa-phone mr-2"></i>
                    For urgent inquiries, call our hotline: <strong>+265 999 123 456</strong>
                </p>
            </div>
        </div>
    </section>

    <!-- Action Buttons -->
    <div class="flex flex-col sm:flex-row gap-4 justify-center mt-10">
        <?php if ($isConfirmed): ?>
            <a href="equipment.php" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider text-sm rounded-sm shadow-lg transition-all duration-300 hover:shadow-fes-red/40">
                <i class="fas fa-plus text-sm"></i>
                Book Another Equipment
            </a>
            <a href="customer/dashboard.php" class="inline-flex items-center justify-center gap-2 px-8 py-4 border border-gray-200 text-gray-600 font-display font-700 uppercase tracking-wider text-sm rounded-sm hover:bg-gray-50 transition-all duration-300">
                <i class="fas fa-tachometer-alt text-sm"></i>
                My Dashboard
            </a>
        <?php else: ?>
            <a href="booking.php?equipment_id=<?php echo urlencode($equipmentId); ?>" class="inline-flex items-center justify-center gap-2 px-8 py-4 border border-gray-200 text-gray-600 font-display font-700 uppercase tracking-wider text-sm rounded-sm hover:bg-gray-50 transition-all duration-300">
                <i class="fas fa-arrow-left text-sm"></i>
                Edit Booking
            </a>
            <form method="post" class="inline-flex">
                <input type="hidden" name="confirm_booking" value="1">
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-fes-red hover:bg-red-700 text-white font-display font-700 uppercase tracking-wider text-sm rounded-sm shadow-lg transition-all duration-300 hover:shadow-fes-red/40">
                    <i class="fas fa-check text-sm"></i>
                    Confirm Booking
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
</body>
</html>
<?php
if ($isConfirmed) {
    unset($_SESSION[$pendingKey]);
}
?>

