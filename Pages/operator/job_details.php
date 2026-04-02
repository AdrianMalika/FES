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
require_once __DIR__ . '/../../includes/equipment_status_from_bookings.php';
require_once __DIR__ . '/../../includes/fes_date.php';

/** Minimum damage reports (this booking + operator) before status can be set to Completed. */
$fesMinDamageReportsToComplete = 3;

$operatorId = (int)($_SESSION['user_id'] ?? 0);
$operatorName = $_SESSION['name'] ?? 'Operator';
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;

// Prevent browser caching and "resubmit" prompts after POST.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$statusUpdated = (int)($_GET['status_updated'] ?? 0);
// (Optional param used by redirects; not required for rendering.)
// $statusUpdatedNew = trim((string)($_GET['new_status'] ?? ''));

// Handle operator status update from within this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['new_status'])) {
    $postBookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = trim((string)($_POST['new_status'] ?? ''));
    $allowed = ['in_progress', 'completed'];

    if ($postBookingId > 0 && in_array($newStatus, $allowed, true)) {
        $updated = false;
        $conn = null;

        try {
            $conn = getDBConnection();

            $fullOk = false;
            $fullError = '';
            $equipmentId = '';

            if ($newStatus === 'completed') {
                $drc = 0;
                $cntStmt = $conn->prepare('SELECT COUNT(*) AS c FROM damage_reports WHERE booking_id = ? AND operator_id = ?');
                if ($cntStmt) {
                    $cntStmt->bind_param('ii', $postBookingId, $operatorId);
                    $cntStmt->execute();
                    $cr = $cntStmt->get_result();
                    if ($cr && ($crow = $cr->fetch_assoc())) {
                        $drc = (int)($crow['c'] ?? 0);
                    }
                    $cntStmt->close();
                }
                if ($drc < $fesMinDamageReportsToComplete) {
                    $conn->close();
                    header('Location: job_details.php?' . http_build_query([
                        'id' => $postBookingId,
                        'status_updated' => 0,
                        'new_status' => $newStatus,
                        'damage_reports_required' => $fesMinDamageReportsToComplete,
                        'damage_reports_have' => $drc,
                    ]));
                    exit();
                }
            }

            // Fetch equipment_id for equipment status updates.
            $equipStmt = $conn->prepare("SELECT equipment_id FROM bookings WHERE booking_id = ? AND operator_id = ? LIMIT 1");
            if ($equipStmt) {
                $equipStmt->bind_param('ii', $postBookingId, $operatorId);
                $equipStmt->execute();
                $equipRes = $equipStmt->get_result();
                $equipRow = $equipRes ? $equipRes->fetch_assoc() : null;
                $equipmentId = (string)($equipRow['equipment_id'] ?? '');
                $equipStmt->close();
            }

            // Primary update: status + operator_start_time (first In progress) + operator_end_time (Completed).
            // Requires columns operator_start_time, operator_end_time on bookings (see database/fes_db.sql).
            try {
                $sqlFull = "UPDATE bookings 
                            SET status = ?,
                                operator_start_time = IF(?, IFNULL(operator_start_time, NOW()), operator_start_time),
                                operator_end_time = IF(?, IFNULL(operator_end_time, NOW()), operator_end_time),
                                updated_at = NOW()
                            WHERE booking_id = ? AND operator_id = ?";

                $stmt = $conn->prepare($sqlFull);
                if ($stmt) {
                    $isStart = $newStatus === 'in_progress' ? 1 : 0;
                    $isEnd = $newStatus === 'completed' ? 1 : 0;
                    $stmt->bind_param('siiii', $newStatus, $isStart, $isEnd, $postBookingId, $operatorId);
                    $fullOk = $stmt->execute();
                    $fullError = $stmt->error ?? '';
                    $stmt->close();
                } else {
                    $fullError = $conn->error ?? 'prepare failed';
                }
            } catch (Exception $e) {
                $fullOk = false;
                $fullError = $e->getMessage();
            }

            // Fallback: status only, then try timestamp columns separately (if DB was migrated after deploy).
            if (!$fullOk) {
                try {
                    $sqlSimple = 'UPDATE bookings SET status = ?, updated_at = NOW() WHERE booking_id = ? AND operator_id = ?';
                    $stmt2 = $conn->prepare($sqlSimple);
                    if ($stmt2) {
                        $stmt2->bind_param('sii', $newStatus, $postBookingId, $operatorId);
                        $updated = $stmt2->execute();
                        $stmt2->close();
                    } else {
                        $updated = false;
                        error_log('Operator status fallback prepare failed: ' . ($conn->error ?? 'unknown'));
                    }
                } catch (Exception $e) {
                    $updated = false;
                    error_log('Operator status fallback error: ' . $e->getMessage());
                }

                if ($updated) {
                    try {
                        if ($newStatus === 'in_progress') {
                            $ts = $conn->prepare('UPDATE bookings SET operator_start_time = IFNULL(operator_start_time, NOW()) WHERE booking_id = ? AND operator_id = ?');
                            if ($ts) {
                                $ts->bind_param('ii', $postBookingId, $operatorId);
                                $ts->execute();
                                $ts->close();
                            }
                        } elseif ($newStatus === 'completed') {
                            $ts = $conn->prepare('UPDATE bookings SET operator_end_time = IFNULL(operator_end_time, NOW()) WHERE booking_id = ? AND operator_id = ?');
                            if ($ts) {
                                $ts->bind_param('ii', $postBookingId, $operatorId);
                                $ts->execute();
                                $ts->close();
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Operator job time columns update skipped or failed: ' . $e->getMessage());
                    }
                }
            } else {
                $updated = true;
            }

            // Always log full update failure details for debugging.
            if (!$fullOk) {
                error_log(sprintf(
                    'Operator status update full query failed; booking_id=%d operator_id=%d new_status=%s error=%s',
                    $postBookingId,
                    $operatorId,
                    $newStatus,
                    $fullError
                ));
            }

            // Keep equipment.status in sync with all bookings for this machine (same as admin flow).
            if ($updated && $equipmentId !== '') {
                try {
                    recalculate_equipment_status_from_bookings($conn, $equipmentId);
                } catch (Exception $e) {
                    error_log('Equipment lifecycle update error: ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log('Operator job status update error: ' . $e->getMessage());
            $updated = false;
        } finally {
            if ($conn instanceof mysqli) {
                $conn->close();
            }
        }

        $qs = http_build_query([
            'id' => $postBookingId,
            'status_updated' => $updated ? 1 : 0,
            'new_status' => $newStatus,
        ]);
        header('Location: job_details.php?' . $qs);
        exit();
    }
}

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.*, 
                       e.equipment_name, e.category, e.location AS equipment_location, e.status AS equipment_status,
                       u.name AS customer_name, u.email AS customer_email
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
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

/** Damage counts + up to 3 most recent statuses for sidebar (full text & history on job_damage_status.php). */
$damageReportCount = 0;
$recentDamageReports = [];
if ($bookingId > 0 && $booking) {
    try {
        $connDr = getDBConnection();
        $csql = 'SELECT COUNT(*) AS c FROM damage_reports WHERE booking_id = ? AND operator_id = ?';
        if ($cst = $connDr->prepare($csql)) {
            $cst->bind_param('ii', $bookingId, $operatorId);
            $cst->execute();
            $cRes = $cst->get_result();
            if ($cRes && ($crow = $cRes->fetch_assoc())) {
                $damageReportCount = (int)($crow['c'] ?? 0);
            }
            $cst->close();
        }
        if ($damageReportCount > 0) {
            $dsql = 'SELECT damage_report_id, status FROM damage_reports WHERE booking_id = ? AND operator_id = ? ORDER BY created_at DESC LIMIT 3';
            if ($dst = $connDr->prepare($dsql)) {
                $dst->bind_param('ii', $bookingId, $operatorId);
                $dst->execute();
                $drRes = $dst->get_result();
                if ($drRes) {
                    while ($drRow = $drRes->fetch_assoc()) {
                        $recentDamageReports[] = $drRow;
                    }
                }
                $dst->close();
            }
        }
        $connDr->close();
    } catch (Throwable $e) {
        error_log('Operator job_details damage_reports: ' . $e->getMessage());
    }
}

function fes_operator_dr_status_badge(string $s): string
{
    switch ($s) {
        case 'submitted':
            return 'bg-amber-50 text-amber-800';
        case 'acknowledged':
            return 'bg-blue-50 text-blue-800';
        case 'closed':
            return 'bg-gray-100 text-gray-700';
        default:
            return 'bg-gray-100 text-gray-700';
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
                                    <?php if (!empty($booking['equipment_status'])): ?>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                                Equipment: <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($booking['equipment_status']))); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Date</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars(fes_format_date_safe($booking['booking_date'] ?? null, 'M d, Y', 'N/A')); ?></div>
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
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars(fes_format_date_safe($booking['operator_start_time'] ?? null, 'M d, Y H:i', 'Not started')); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">End Time</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars(fes_format_date_safe($booking['operator_end_time'] ?? null, 'M d, Y H:i', 'Not completed')); ?></div>
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
                        <div class="space-y-4">
                            <?php if (isset($_GET['damage_reports_required'])): ?>
                                <?php
                                $needDr = (int)($_GET['damage_reports_required'] ?? $fesMinDamageReportsToComplete);
                                $haveDr = (int)($_GET['damage_reports_have'] ?? 0);
                                ?>
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    Complete is blocked: you need at least <?php echo (int)$needDr; ?> damage report<?php echo $needDr === 1 ? '' : 's'; ?> for this job before marking it completed. You currently have <?php echo (int)$haveDr; ?>.
                                    <a href="job_damage.php?id=<?php echo (int)$bookingId; ?>" class="font-semibold text-fes-red hover:underline">Submit damage reports</a>
                                </div>
                            <?php elseif (isset($_GET['status_updated'])): ?>
                                <?php if ($statusUpdated === 1): ?>
                                    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                                        Job status updated.
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                                        Failed to update job status.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php
                            $jobStatus = (string)($booking['status'] ?? '');
                            $canEditJobStatus = in_array($jobStatus, ['pending', 'confirmed', 'in_progress'], true);
                            $selInProgress = in_array($jobStatus, ['pending', 'confirmed', 'in_progress'], true);
                            $selCompleted = ($jobStatus === 'completed');
                            ?>
                            <?php if ($booking && $canEditJobStatus): ?>
                                <form method="post" id="fes-job-status-form" class="space-y-3"
                                      data-min-damage-reports="<?php echo (int)$fesMinDamageReportsToComplete; ?>"
                                      data-damage-report-count="<?php echo (int)$damageReportCount; ?>">
                                    <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars((string)$bookingId); ?>">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Booking status</label>
                                        <select name="new_status" id="fes-job-new-status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                            <option value="in_progress" <?php echo $selInProgress ? 'selected' : ''; ?>>In progress</option>
                                            <option value="completed" <?php echo $selCompleted ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <strong>In progress</strong> records the start time when you first save it.
                                            <strong>Completed</strong> records the end time. Equipment status is updated too.
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow">
                                            <i class="fas fa-save"></i> Save status
                                        </button>
                                    </div>
                                </form>
                            <?php elseif ($booking && $jobStatus === 'completed'): ?>
                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                    This job is completed. Status cannot be changed here.
                                </div>
                            <?php elseif ($booking && $jobStatus === 'cancelled'): ?>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                    This booking was cancelled.
                                </div>
                            <?php endif; ?>

                            <div class="pt-4 border-t border-gray-100">
                                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Equipment damage</div>
                                <?php if ($damageReportCount <= 0 || empty($recentDamageReports)): ?>
                                    <a href="job_damage.php?id=<?php echo (int)$bookingId; ?>" class="text-sm font-medium text-fes-red hover:underline">Report damage</a>
                                <?php else: ?>
                                    <ul class="space-y-1.5 mb-2" aria-label="Most recent damage reports">
                                        <?php foreach ($recentDamageReports as $drRow):
                                            $drSt = (string)($drRow['status'] ?? 'submitted');
                                            ?>
                                            <li class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                                <span class="font-mono text-gray-600">#<?php echo (int)$drRow['damage_report_id']; ?></span>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo fes_operator_dr_status_badge($drSt); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($drSt)); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if ($damageReportCount > count($recentDamageReports)): ?>
                                        <p class="text-xs text-gray-400 mb-2">Older reports are in history.</p>
                                    <?php endif; ?>
                                    <div class="flex flex-col gap-1.5 text-sm">
                                        <a href="job_damage_status.php?id=<?php echo (int)$bookingId; ?>" class="text-fes-red font-medium hover:underline">Office replies &amp; history</a>
                                        <a href="job_damage.php?id=<?php echo (int)$bookingId; ?>" class="text-gray-600 hover:text-gray-900 hover:underline">Add another report</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                        <div class="mt-5 text-xs text-gray-500">
                            Last updated: <?php echo htmlspecialchars(fes_format_date_safe($booking['updated_at'] ?? null, 'M d, Y — H:i', 'N/A')); ?>
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

    // Confirm before marking job completed (updates booking + equipment).
    (function () {
        var form = document.getElementById('fes-job-status-form');
        var sel = document.getElementById('fes-job-new-status');
        if (!form || !sel) return;
        form.addEventListener('submit', function (e) {
            if (sel.value !== 'completed') return;
            var min = parseInt(form.getAttribute('data-min-damage-reports') || '0', 10);
            var cnt = parseInt(form.getAttribute('data-damage-report-count') || '0', 10);
            if (min > 0 && cnt < min) {
                e.preventDefault();
                window.alert('You must submit at least ' + min + ' damage report' + (min === 1 ? '' : 's') + ' for this job before marking it completed. You currently have ' + cnt + '.');
                return;
            }
            var msg = 'Are you sure you want to mark this job as completed?\n\nThis will confirm completion, record the end time, and update equipment availability.';
            if (!window.confirm(msg)) {
                e.preventDefault();
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
