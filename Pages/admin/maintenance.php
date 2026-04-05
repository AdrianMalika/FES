<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    switch ($_SESSION['role'] ?? '') {
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
require_once __DIR__ . '/../../includes/fes_date.php';
require_once __DIR__ . '/../../includes/fes_maintenance_mail.php';

/**
 * Ensures equipment_maintenance exists (matches database/add_maintenance.sql).
 */
function fes_try_create_maintenance_table(mysqli $conn): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `maintenance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` VARCHAR(100) NOT NULL,
  `maintenance_type` ENUM('routine','repair','overhaul','inspection') NOT NULL,
  `status` ENUM('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `scheduled_date` DATE NOT NULL,
  `completed_date` DATE NULL DEFAULT NULL,
  `cost` DECIMAL(12,2) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `admin_notes` TEXT NULL,
  `created_by` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_em_equipment` (`equipment_id`),
  KEY `idx_em_status` (`status`),
  KEY `idx_em_scheduled` (`scheduled_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    if (!$conn->query($sql)) {
        throw new RuntimeException($conn->error ?: 'CREATE TABLE equipment_maintenance failed');
    }
}

$adminId = (int)$_SESSION['user_id'];
$todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');

$flashSuccess = null;
$flashError = null;
$equipmentList = [];
$maintenanceRows = [];
$overdueEquipment = [];
$statScheduled = 0;
$statInProgress = 0;
$statCompleted = 0;
$statOverdueJobs = 0;

$conn = null;
try {
    $conn = getDBConnection();
    fes_try_create_maintenance_table($conn);

    if (isset($_POST['fes_add_maintenance'])) {
        $equipmentId = trim((string)($_POST['equipment_id'] ?? ''));
        $maintenanceType = trim((string)($_POST['maintenance_type'] ?? ''));
        $scheduledDate = trim((string)($_POST['scheduled_date'] ?? ''));
        $costRaw = trim((string)($_POST['cost'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        $allowedTypes = ['routine', 'repair', 'overhaul', 'inspection'];
        if ($equipmentId === '' || $scheduledDate === '' || !in_array($maintenanceType, $allowedTypes, true)) {
            $_SESSION['error'] = 'Equipment, maintenance type, and scheduled date are required.';
            header('Location: maintenance.php');
            exit();
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $scheduledDate);
        if ($dt === false || $dt->format('Y-m-d') !== $scheduledDate) {
            $_SESSION['error'] = 'Invalid scheduled date.';
            header('Location: maintenance.php');
            exit();
        }

        $chk = $conn->prepare('SELECT 1 FROM equipment WHERE equipment_id = ? LIMIT 1');
        if (!$chk) {
            throw new RuntimeException($conn->error);
        }
        $chk->bind_param('s', $equipmentId);
        $chk->execute();
        $exists = $chk->get_result()->fetch_row();
        $chk->close();
        if (!$exists) {
            $_SESSION['error'] = 'Selected equipment was not found.';
            header('Location: maintenance.php');
            exit();
        }

        $costVal = null;
        if ($costRaw !== '') {
            if (!is_numeric($costRaw)) {
                $_SESSION['error'] = 'Cost must be a valid number.';
                header('Location: maintenance.php');
                exit();
            }
            $costVal = round((float)$costRaw, 2);
        }

        $descSql = $description === '' ? null : $description;

        $ins = $conn->prepare(
            'INSERT INTO equipment_maintenance (equipment_id, maintenance_type, scheduled_date, cost, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            throw new RuntimeException($conn->error);
        }
        $costBind = $costVal === null ? null : number_format($costVal, 2, '.', '');
        $ins->bind_param(
            'sssssi',
            $equipmentId,
            $maintenanceType,
            $scheduledDate,
            $costBind,
            $descSql,
            $adminId
        );
        if (!$ins->execute()) {
            $ins->close();
            throw new RuntimeException($conn->error);
        }
        $ins->close();

        if ($scheduledDate <= $todayYmd) {
            $upEq = $conn->prepare('UPDATE equipment SET last_maintenance = ? WHERE equipment_id = ?');
            if ($upEq) {
                $upEq->bind_param('ss', $scheduledDate, $equipmentId);
                $upEq->execute();
                $upEq->close();
            }
        }

        $_SESSION['success'] = 'Maintenance scheduled successfully.';
        header('Location: maintenance.php');
        exit();
    }

    if (isset($_POST['fes_update_maintenance'])) {
        $maintenanceId = (int)($_POST['maintenance_id'] ?? 0);
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $completedDateRaw = trim((string)($_POST['completed_date'] ?? ''));
        $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
        $costRaw = trim((string)($_POST['cost'] ?? ''));

        $allowedStatus = ['scheduled', 'in_progress', 'completed', 'cancelled'];
        if ($maintenanceId < 1 || !in_array($newStatus, $allowedStatus, true)) {
            $_SESSION['error'] = 'Invalid maintenance record or status.';
            header('Location: maintenance.php');
            exit();
        }

        $completedDate = null;
        if ($completedDateRaw !== '') {
            $cdt = DateTimeImmutable::createFromFormat('Y-m-d', $completedDateRaw);
            if ($cdt === false || $cdt->format('Y-m-d') !== $completedDateRaw) {
                $_SESSION['error'] = 'Invalid completion date.';
                header('Location: maintenance.php');
                exit();
            }
            $completedDate = $completedDateRaw;
        }

        if ($newStatus === 'completed' && ($completedDate === null || $completedDate === '')) {
            $_SESSION['error'] = 'Completion date is required when status is completed.';
            header('Location: maintenance.php');
            exit();
        }

        $costVal = null;
        if ($costRaw !== '') {
            if (!is_numeric($costRaw)) {
                $_SESSION['error'] = 'Cost must be a valid number.';
                header('Location: maintenance.php');
                exit();
            }
            $costVal = round((float)$costRaw, 2);
        }

        $sel = $conn->prepare('SELECT equipment_id FROM equipment_maintenance WHERE maintenance_id = ? LIMIT 1');
        if (!$sel) {
            throw new RuntimeException($conn->error);
        }
        $sel->bind_param('i', $maintenanceId);
        $sel->execute();
        $rowEq = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$rowEq) {
            $_SESSION['error'] = 'Maintenance record not found.';
            header('Location: maintenance.php');
            exit();
        }
        $equipForRow = (string)$rowEq['equipment_id'];

        $notesSql = $adminNotes === '' ? null : $adminNotes;

        $upd = $conn->prepare(
            'UPDATE equipment_maintenance SET status = ?, completed_date = ?, admin_notes = ?, cost = ? WHERE maintenance_id = ?'
        );
        if (!$upd) {
            throw new RuntimeException($conn->error);
        }
        $costBind = $costVal === null ? null : number_format($costVal, 2, '.', '');
        $upd->bind_param('ssssi', $newStatus, $completedDate, $notesSql, $costBind, $maintenanceId);
        if (!$upd->execute()) {
            $upd->close();
            throw new RuntimeException($conn->error);
        }
        $upd->close();

        if ($newStatus === 'completed' && $completedDate !== null) {
            $upEq = $conn->prepare("UPDATE equipment SET last_maintenance = ?, status = 'available' WHERE equipment_id = ?");
            if ($upEq) {
                $upEq->bind_param('ss', $completedDate, $equipForRow);
                $upEq->execute();
                $upEq->close();
            }
        }

        $_SESSION['success'] = 'Maintenance record updated.';
        header('Location: maintenance.php');
        exit();
    }

    $flashSuccess = $_SESSION['success'] ?? null;
    $flashError = $_SESSION['error'] ?? null;
    unset($_SESSION['success'], $_SESSION['error']);

    $equipmentList = [];
    $eqRes = $conn->query(
        "SELECT equipment_id, equipment_name, category, status, last_maintenance, total_usage_hours
         FROM equipment
         ORDER BY equipment_name ASC"
    );
    if ($eqRes) {
        while ($r = $eqRes->fetch_assoc()) {
            $equipmentList[] = $r;
        }
        $eqRes->close();
    } else {
        throw new RuntimeException($conn->error ?: 'equipment query failed');
    }

    $maintenanceRows = [];
    // Explicit COLLATE avoids "Illegal mix of collations" when equipment uses utf8mb4_0900_ai_ci (MySQL 8) and this table uses utf8mb4_unicode_ci.
    $mSql = "SELECT m.*, e.equipment_name
             FROM equipment_maintenance m
             INNER JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = m.equipment_id COLLATE utf8mb4_unicode_ci
             ORDER BY FIELD(m.status, 'in_progress', 'scheduled', 'completed', 'cancelled'), m.scheduled_date ASC, m.maintenance_id DESC";
    $mRes = $conn->query($mSql);
    if ($mRes) {
        while ($r = $mRes->fetch_assoc()) {
            $maintenanceRows[] = $r;
        }
        $mRes->close();
    } else {
        throw new RuntimeException($conn->error ?: 'maintenance query failed');
    }

    $statScheduled = 0;
    $statInProgress = 0;
    $statCompleted = 0;
    $statOverdueJobs = 0;
    $stRes = $conn->query('SELECT status, COUNT(*) AS c FROM equipment_maintenance GROUP BY status');
    if (!$stRes) {
        throw new RuntimeException($conn->error ?: 'maintenance stats query failed');
    }
    while ($row = $stRes->fetch_assoc()) {
        $s = (string)($row['status'] ?? '');
        $c = (int)($row['c'] ?? 0);
        if ($s === 'scheduled') {
            $statScheduled = $c;
        }
        if ($s === 'in_progress') {
            $statInProgress = $c;
        }
        if ($s === 'completed') {
            $statCompleted = $c;
        }
    }
    $stRes->close();

    $ovRes = $conn->query(
        "SELECT COUNT(*) AS c FROM equipment_maintenance WHERE status = 'scheduled' AND scheduled_date < CURDATE()"
    );
    if (!$ovRes) {
        throw new RuntimeException($conn->error ?: 'overdue count query failed');
    }
    $or = $ovRes->fetch_assoc();
    $statOverdueJobs = (int)($or['c'] ?? 0);
    $ovRes->close();

    $overdueEquipment = [];
    $oeSql = "SELECT equipment_id, equipment_name, category, status, last_maintenance, total_usage_hours
              FROM equipment
              WHERE status <> 'retired'
                AND (
                  last_maintenance IS NULL
                  OR DATEDIFF(CURDATE(), last_maintenance) > 90
                )
              ORDER BY equipment_name ASC";
    $oeRes = $conn->query($oeSql);
    if (!$oeRes) {
        throw new RuntimeException($conn->error ?: 'overdue equipment query failed');
    }
    while ($r = $oeRes->fetch_assoc()) {
        $overdueEquipment[] = $r;
    }
    $oeRes->close();

    fes_try_create_maintenance_notify_table($conn);
    try {
        fes_maintenance_send_daily_digest_if_needed($conn);
    } catch (Throwable $mailEx) {
        error_log('Admin maintenance digest email: ' . $mailEx->getMessage());
    }
} catch (Throwable $e) {
    error_log('Admin maintenance: ' . $e->getMessage());
    if ($conn instanceof mysqli && $conn->error !== '') {
        error_log('Admin maintenance mysqli: ' . $conn->error);
    }
    if (isset($_POST['fes_add_maintenance']) || isset($_POST['fes_update_maintenance'])) {
        $_SESSION['error'] = 'Something went wrong. Please try again.';
        header('Location: maintenance.php');
        exit();
    }
    if ($flashError === null || $flashError === '') {
        $flashError = 'Could not load maintenance data. Please try again.';
    }
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

/**
 * @param array<string,mixed> $row
 */
function fes_maint_type_badge(string $type): string
{
    $map = [
        'routine' => 'bg-sky-100 text-sky-800',
        'repair' => 'bg-red-100 text-red-800',
        'overhaul' => 'bg-violet-100 text-violet-800',
        'inspection' => 'bg-amber-100 text-amber-900',
    ];
    $cls = $map[$type] ?? 'bg-gray-100 text-gray-700';
    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $cls . '">' . htmlspecialchars(ucfirst($type), ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * @param array<string,mixed> $row
 */
function fes_maint_status_badge(string $status): string
{
    $map = [
        'scheduled' => 'bg-blue-100 text-blue-800',
        'in_progress' => 'bg-purple-100 text-purple-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-gray-200 text-gray-700',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-700';
    $label = str_replace('_', ' ', $status);
    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ' . $cls . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Service timing: badge + explanation + progress bar (bar = % of 90-day window used since last service).
 *
 * @return array{badge: string, badgeClass: string, line: string, barClass: string, barPct: int}
 */
function fes_equipment_service_timing(?string $lastMaintenance, string $equipStatus): array
{
    if ($equipStatus === 'retired') {
        return [
            'badge' => 'Retired',
            'badgeClass' => 'bg-gray-100 text-gray-700 ring-1 ring-gray-200',
            'line' => 'Not counted toward active service reminders.',
            'barClass' => 'bg-gray-400',
            'barPct' => 0,
        ];
    }
    if ($lastMaintenance === null || trim($lastMaintenance) === '') {
        return [
            'badge' => 'No date on file',
            'badgeClass' => 'bg-red-50 text-red-800 ring-1 ring-red-200',
            'line' => 'Add a service date (schedule & complete maintenance) so we can remind you before 90 days.',
            'barClass' => 'bg-red-500',
            'barPct' => 100,
        ];
    }
    $lm = DateTimeImmutable::createFromFormat('Y-m-d', substr((string)$lastMaintenance, 0, 10));
    if ($lm === false) {
        return [
            'badge' => 'Check date',
            'badgeClass' => 'bg-red-50 text-red-800 ring-1 ring-red-200',
            'line' => 'The stored last-service date is invalid — correct it on the equipment or maintenance record.',
            'barClass' => 'bg-red-500',
            'barPct' => 100,
        ];
    }
    $today = new DateTimeImmutable('today');
    if ($lm > $today) {
        return [
            'badge' => 'Future date',
            'badgeClass' => 'bg-sky-50 text-sky-900 ring-1 ring-sky-200',
            'line' => 'Last serviced is in the future; fix the date if that was a mistake.',
            'barClass' => 'bg-emerald-500',
            'barPct' => 8,
        ];
    }
    $days = $lm->diff($today)->days;
    $barPct = min(100, (int)round($days / 90 * 100));

    if ($days <= 60) {
        return [
            'badge' => 'On track',
            'badgeClass' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
            'line' => 'Last service ' . $days . ' day' . ($days === 1 ? '' : 's') . ' ago — within 60 days (good). Next review before 90 days.',
            'barClass' => 'bg-emerald-500',
            'barPct' => max(4, $barPct),
        ];
    }
    if ($days <= 90) {
        $until = 90 - $days;

        return [
            'badge' => 'Due soon',
            'badgeClass' => 'bg-amber-50 text-amber-900 ring-1 ring-amber-200',
            'line' => 'Last service ' . $days . ' days ago — you are in the 61–90 day window. About ' . $until . ' day' . ($until === 1 ? '' : 's') . ' until the 90-day guideline; schedule service.',
            'barClass' => 'bg-amber-500',
            'barPct' => $barPct,
        ];
    }

    return [
        'badge' => 'Past guideline',
        'badgeClass' => 'bg-red-50 text-red-800 ring-1 ring-red-200',
        'line' => 'Last service ' . $days . ' days ago — over the 90-day guideline. Schedule maintenance now.',
        'barClass' => 'bg-red-500',
        'barPct' => 100,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Admin | FES</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { fes: { red: '#D32F2F', dark: '#1a1a1a', mid: '#2e2e2e' } },
                    boxShadow: { card: '0 4px 15px rgba(0,0,0,0.05)' }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-900">
<div class="min-h-screen w-full">
    <?php include __DIR__ . '/include/sidebar.php'; ?>
    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <div class="min-h-screen flex flex-col md:ml-72">
        <header class="bg-white px-4 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-3 shadow-sm border-b border-gray-100">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600 shrink-0" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="min-w-0">
                    <div class="text-sm text-gray-500">Admin</div>
                    <h1 class="text-xl font-semibold text-gray-900 tracking-tight">Maintenance tracking</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Schedule and log equipment servicing</p>
                    <p class="text-[11px] text-gray-400 mt-1 max-w-xl">Email digest to <?php echo htmlspecialchars(fes_maintenance_notify_email(), ENT_QUOTES, 'UTF-8'); ?>: due-soon and overdue items (at most once per day when you open this page).</p>
                </div>
            </div>
            <button type="button" id="btn-open-add-modal" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-semibold px-4 py-2.5 rounded-lg text-sm shadow-sm shrink-0">
                <i class="fas fa-plus"></i> Schedule maintenance
            </button>
        </header>

        <main class="flex-1 p-4 sm:p-6 overflow-x-hidden">
            <?php if ($flashSuccess !== null && $flashSuccess !== ''): ?>
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 flex items-start gap-2">
                    <i class="fas fa-check-circle mt-0.5 text-emerald-600"></i>
                    <span><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flashError !== null && $flashError !== ''): ?>
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 flex items-start gap-2">
                    <i class="fas fa-exclamation-circle mt-0.5 text-fes-red"></i>
                    <span><?php echo htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                        <i class="fas fa-calendar-day text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Scheduled</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo (int)$statScheduled; ?></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                        <i class="fas fa-spinner text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">In progress</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo (int)$statInProgress; ?></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                        <i class="fas fa-circle-check text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Completed</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo (int)$statCompleted; ?></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-center gap-4 ring-1 ring-red-100">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-fes-red">
                        <i class="fas fa-exclamation-triangle text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Overdue jobs</div>
                        <div class="text-2xl font-bold text-fes-red"><?php echo (int)$statOverdueJobs; ?></div>
                        <div class="text-[11px] text-gray-500 mt-0.5">Scheduled before today</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($overdueEquipment)): ?>
                <div class="mb-6 rounded-xl border-2 border-fes-red/30 bg-red-50/90 px-4 py-4 sm:px-5 sm:py-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                        <div class="flex items-start gap-2">
                            <i class="fas fa-exclamation-triangle text-fes-red mt-0.5"></i>
                            <div>
                                <h2 class="text-base font-bold text-gray-900 display">Equipment needing service</h2>
                                <p class="text-sm text-gray-700 mt-0.5">No service in 90+ days or never recorded (excludes retired).</p>
                            </div>
                        </div>
                    </div>
                    <ul class="space-y-2">
                        <?php foreach ($overdueEquipment as $oe): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/80 border border-red-100 px-3 py-2.5">
                                <div>
                                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars((string)$oe['equipment_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="text-gray-500 text-sm"> — <?php echo htmlspecialchars((string)$oe['equipment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="text-xs text-gray-500 block mt-0.5">
                                        Last: <?php echo htmlspecialchars(fes_format_date_safe($oe['last_maintenance'] ?? null, 'M j, Y', 'Never')); ?>
                                    </span>
                                </div>
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-fes-red hover:bg-[#b71c1c] text-white text-xs font-semibold px-3 py-2"
                                        onclick="prefillMaintenance(<?php echo json_encode((string)$oe['equipment_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                    <i class="fas fa-wrench"></i> Schedule now
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Maintenance log</h2>
                    <span class="text-xs text-gray-500"><?php echo count($maintenanceRows); ?> record(s)</span>
                </div>
                <div class="overflow-x-auto -mx-1">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 border-b border-gray-200 uppercase tracking-wider">
                                <th class="py-3 pr-4">Equipment</th>
                                <th class="py-3 pr-4">Type</th>
                                <th class="py-3 pr-4">Scheduled</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Cost</th>
                                <th class="py-3 pr-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            <?php if (empty($maintenanceRows)): ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-gray-500">No maintenance records yet. Schedule one to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($maintenanceRows as $m): ?>
                                    <?php
                                    $st = (string)($m['status'] ?? '');
                                    $sd = (string)($m['scheduled_date'] ?? '');
                                    $isRowOverdue = ($st === 'scheduled' && $sd !== '' && $sd < $todayYmd);
                                    $trClass = $isRowOverdue ? 'bg-red-50/80 border-l-4 border-fes-red' : 'border-b border-gray-100';
                                    $costDisp = $m['cost'] !== null && $m['cost'] !== '' ? 'MWK ' . number_format((float)$m['cost'], 2) : '—';
                                    $updatePayload = [
                                        'maintenance_id' => (int)$m['maintenance_id'],
                                        'equipment_name' => (string)($m['equipment_name'] ?? ''),
                                        'equipment_id' => (string)($m['equipment_id'] ?? ''),
                                        'status' => $st,
                                        'completed_date' => $m['completed_date'] ? substr((string)$m['completed_date'], 0, 10) : '',
                                        'cost' => $m['cost'] !== null && $m['cost'] !== '' ? (string)$m['cost'] : '',
                                        'admin_notes' => (string)($m['admin_notes'] ?? ''),
                                    ];
                                    ?>
                                    <tr class="<?php echo $trClass; ?> align-middle">
                                        <td class="py-3 pr-4">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars((string)($m['equipment_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars((string)($m['equipment_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td class="py-3 pr-4"><?php echo fes_maint_type_badge((string)($m['maintenance_type'] ?? '')); ?></td>
                                        <td class="py-3 pr-4 whitespace-nowrap"><?php echo htmlspecialchars(fes_format_date_safe($m['scheduled_date'] ?? null, 'M j, Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="py-3 pr-4"><?php echo fes_maint_status_badge($st); ?></td>
                                        <td class="py-3 pr-4 whitespace-nowrap"><?php echo htmlspecialchars($costDisp, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="py-3 pr-4 text-right">
                                            <?php if (!in_array($st, ['completed', 'cancelled'], true)): ?>
                                                <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                                        onclick="openUpdateModal(<?php echo json_encode($updatePayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                                    <i class="fas fa-pen-to-square text-fes-red"></i> Update
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5">
                <div class="flex flex-col gap-1 mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Equipment overview</h2>
                    <p class="text-xs text-gray-500 max-w-3xl">
                        <strong>Service timing</strong> uses <strong>Last serviced</strong> and a <strong>90-day</strong> rule:
                        <span class="text-emerald-700 font-medium">On track</span> (0–60 days),
                        <span class="text-amber-800 font-medium">Due soon</span> (61–90),
                        <span class="text-red-700 font-medium">Past guideline</span> (90+ or missing date).
                        <span class="text-gray-500">The bar shows how far you are through that 90-day window after last service (more fill = closer to or past due).</span>
                    </p>
                </div>
                <div class="overflow-x-auto -mx-1">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-500 border-b border-gray-200 uppercase tracking-wider">
                                <th class="py-3 pr-4">Equipment</th>
                                <th class="py-3 pr-4">Status</th>
                                <th class="py-3 pr-4">Last serviced</th>
                                <th class="py-3 pr-4">Usage hours</th>
                                <th class="py-3 pr-4 min-w-[200px] max-w-xs">Service timing</th>
                                <th class="py-3 pr-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipmentList as $eq): ?>
                                <?php
                                $lm = isset($eq['last_maintenance']) && $eq['last_maintenance'] !== null ? (string)$eq['last_maintenance'] : null;
                                $es = (string)($eq['status'] ?? '');
                                $timing = fes_equipment_service_timing($lm, $es);
                                ?>
                                <tr class="border-b border-gray-100 align-middle">
                                    <td class="py-3 pr-4">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars((string)($eq['equipment_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars((string)($eq['equipment_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="py-3 pr-4 capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $es), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="py-3 pr-4 whitespace-nowrap"><?php echo htmlspecialchars(fes_format_date_safe($eq['last_maintenance'] ?? null, 'M j, Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars((string)(int)($eq['total_usage_hours'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold <?php echo htmlspecialchars($timing['badgeClass'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($timing['badge'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <div class="mt-2 max-w-[200px]">
                                            <div class="flex justify-between items-center text-[10px] text-gray-400 mb-0.5">
                                                <span>Since last service</span>
                                                <span>90-day window</span>
                                            </div>
                                            <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                                                <div class="h-full rounded-full <?php echo htmlspecialchars($timing['barClass'], ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo (int)$timing['barPct']; ?>%;"></div>
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-gray-600 mt-1.5 leading-snug"><?php echo htmlspecialchars($timing['line'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </td>
                                    <td class="py-3 pr-4 text-right">
                                        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-fes-red hover:bg-red-50"
                                                onclick="prefillMaintenance(<?php echo json_encode((string)$eq['equipment_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                            <i class="fas fa-calendar-plus"></i> Schedule
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>

<!-- Add modal -->
<div id="modal-add" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-close-add></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto pointer-events-auto border border-gray-100">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 display">Schedule maintenance</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 p-1" data-close-add aria-label="Close"><i class="fas fa-times text-lg"></i></button>
            </div>
            <form method="post" class="px-5 py-4 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="add_equipment_id">Equipment</label>
                    <select name="equipment_id" id="add_equipment_id" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red">
                        <option value="">Select equipment…</option>
                        <?php foreach ($equipmentList as $eq): ?>
                            <option value="<?php echo htmlspecialchars((string)$eq['equipment_id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$eq['equipment_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string)$eq['equipment_id'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="add_type">Maintenance type</label>
                    <select name="maintenance_type" id="add_type" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red">
                        <option value="routine">Routine</option>
                        <option value="repair">Repair</option>
                        <option value="overhaul">Overhaul</option>
                        <option value="inspection">Inspection</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="add_scheduled">Scheduled date</label>
                    <input type="date" name="scheduled_date" id="add_scheduled" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="add_cost">Cost (MWK)</label>
                    <input type="text" name="cost" id="add_cost" inputmode="decimal" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red" placeholder="Optional">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="add_desc">Description</label>
                    <textarea name="description" id="add_desc" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red" placeholder="Optional notes"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50" data-close-add>Cancel</button>
                    <button type="submit" name="fes_add_maintenance" value="1" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                        <i class="fas fa-check"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update modal -->
<div id="modal-update" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" data-close-upd></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto pointer-events-auto border border-gray-100">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 display">Update maintenance</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600 p-1" data-close-upd aria-label="Close"><i class="fas fa-times text-lg"></i></button>
            </div>
            <form method="post" class="px-5 py-4 space-y-4">
                <input type="hidden" name="maintenance_id" id="upd_maint_id" value="">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Equipment</label>
                    <div id="upd_equip_name" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 text-sm text-gray-800 font-medium"></div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="upd_status">Status</label>
                    <select name="new_status" id="upd_status" required class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red">
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="upd_completed">Completion date</label>
                    <input type="date" name="completed_date" id="upd_completed" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="upd_cost">Actual cost (MWK)</label>
                    <input type="text" name="cost" id="upd_cost" inputmode="decimal" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red" placeholder="Optional">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="upd_notes">Admin notes</label>
                    <textarea name="admin_notes" id="upd_notes" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red/30 focus:border-fes-red"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50" data-close-upd>Cancel</button>
                    <button type="submit" name="fes_update_maintenance" value="1" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                        <i class="fas fa-save"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('fes-dashboard-menu-btn');
    var sidebar = document.getElementById('fes-dashboard-sidebar');
    var overlay = document.getElementById('fes-dashboard-overlay');
    if (btn && sidebar && overlay) {
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
            if (sidebar.classList.contains('translate-x-0')) closeSidebar();
            else openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);
    }

    var modalAdd = document.getElementById('modal-add');
    var modalUpd = document.getElementById('modal-update');
    var btnAdd = document.getElementById('btn-open-add-modal');

    function openAddModal() {
        if (!modalAdd) return;
        modalAdd.classList.remove('hidden');
        modalAdd.setAttribute('aria-hidden', 'false');
    }
    function closeAddModal() {
        if (!modalAdd) return;
        modalAdd.classList.add('hidden');
        modalAdd.setAttribute('aria-hidden', 'true');
    }
    function closeUpdModal() {
        if (!modalUpd) return;
        modalUpd.classList.add('hidden');
        modalUpd.setAttribute('aria-hidden', 'true');
    }

    if (btnAdd) btnAdd.addEventListener('click', openAddModal);
    if (modalAdd) {
        modalAdd.querySelectorAll('[data-close-add]').forEach(function (el) {
            el.addEventListener('click', closeAddModal);
        });
    }
    if (modalUpd) {
        modalUpd.querySelectorAll('[data-close-upd]').forEach(function (el) {
            el.addEventListener('click', closeUpdModal);
        });
    }

    window.prefillMaintenance = function (equipId) {
        var sel = document.getElementById('add_equipment_id');
        if (sel && equipId) {
            sel.value = equipId;
        }
        openAddModal();
    };

    window.openUpdateModal = function (rec) {
        if (!modalUpd || !rec) return;
        document.getElementById('upd_maint_id').value = String(rec.maintenance_id || '');
        document.getElementById('upd_equip_name').textContent = rec.equipment_name || '';
        document.getElementById('upd_status').value = rec.status || 'scheduled';
        document.getElementById('upd_completed').value = rec.completed_date || '';
        document.getElementById('upd_cost').value = rec.cost || '';
        document.getElementById('upd_notes').value = rec.admin_notes || '';
        modalUpd.classList.remove('hidden');
        modalUpd.setAttribute('aria-hidden', 'false');
    };
})();
</script>
</body>
</html>
