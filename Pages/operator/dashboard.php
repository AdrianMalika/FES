<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has operator role
if (($_SESSION['role'] ?? '') !== 'operator') {
    // Redirect based on actual role
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

require_once '../../includes/database.php';
require_once '../../includes/fes_date.php';
require_once '../../includes/fes_skill_types.php';

$operatorId   = (int)($_SESSION['user_id'] ?? 0);
$operatorName = $_SESSION['name'] ?? 'Operator';

$equipment    = [];
$skills       = [];
$completedJobs = [];
$stats = [
    'assigned'     => 0,
    'active'       => 0,
    'skills'       => 0,
    'jobs_total'   => 0,
    'jobs_pending' => 0,
    'jobs_in_progress' => 0,
    'jobs_completed' => 0,
    'jobs_upcoming_week' => 0,
    'feedback_avg' => null,
    'feedback_count' => 0,
    'damage_open' => 0,
    'dashboard_alerts' => 0,
];
$loadError = '';

try {
    $conn = getDBConnection();

    // Load equipment assigned to this operator
    $eqSql = "
        SELECT
            id,
            equipment_id,
            equipment_name,
            category,
            status,
            location
        FROM equipment
        WHERE operator_id = ?
        ORDER BY (status = 'in_use') DESC, equipment_name ASC
    ";
    if ($stmt = $conn->prepare($eqSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $equipment[] = $row;
        }
        $stmt->close();
    }

    $stats['assigned'] = count($equipment);
    foreach ($equipment as $row) {
        if (($row['status'] ?? '') === 'in_use') {
            $stats['active']++;
        }
    }

    // Load skills
    $skSql = 'SELECT id, skill_name, skill_level, created_at FROM operator_skills WHERE operator_id = ? ORDER BY skill_name ASC';
    if ($stmt = $conn->prepare($skSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $skills[] = $row;
        }
        $stmt->close();
    }
    $stats['skills'] = count($skills);

    // Job stats based on bookings assigned to this operator
    $jobSql = "SELECT
                    COUNT(*) AS total,
                    SUM(status = 'in_progress') AS in_progress,
                    SUM(status = 'completed') AS completed_all
               FROM bookings
               WHERE operator_id = ?";
    if ($stmt = $conn->prepare($jobSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['jobs_total'] = (int)($row['total'] ?? 0);
            $stats['jobs_in_progress'] = (int)($row['in_progress'] ?? 0);
            $stats['jobs_completed'] = (int)($row['completed_all'] ?? 0);
        }
        $stmt->close();
    }

    $pendSql = "SELECT COUNT(*) AS c FROM bookings WHERE operator_id = ? AND status IN ('pending','confirmed')";
    if ($stmt = $conn->prepare($pendSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['jobs_pending'] = (int)($row['c'] ?? 0);
        }
        $stmt->close();
    }

    $upSql = "SELECT COUNT(*) AS c FROM bookings WHERE operator_id = ?
              AND status IN ('pending','confirmed','in_progress')
              AND booking_date >= CURDATE() AND booking_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    if ($stmt = $conn->prepare($upSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['jobs_upcoming_week'] = (int)($row['c'] ?? 0);
        }
        $stmt->close();
    }

    try {
        $fbSql = 'SELECT AVG(rating) AS av, COUNT(*) AS c FROM booking_feedback WHERE operator_id = ?';
        if ($stmt = $conn->prepare($fbSql)) {
            $stmt->bind_param('i', $operatorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stats['feedback_count'] = (int)($row['c'] ?? 0);
                if ($stats['feedback_count'] > 0 && $row['av'] !== null) {
                    $stats['feedback_avg'] = round((float)$row['av'], 2);
                }
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('Operator dashboard feedback stats: ' . $e->getMessage());
    }

    try {
        $drSql = "SELECT COUNT(*) AS c FROM damage_reports WHERE operator_id = ? AND status IN ('submitted','acknowledged')";
        if ($stmt = $conn->prepare($drSql)) {
            $stmt->bind_param('i', $operatorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stats['damage_open'] = (int)($row['c'] ?? 0);
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('Operator dashboard damage stats: ' . $e->getMessage());
    }

    $cjSql = 'SELECT b.booking_id, b.booking_date, b.service_type, b.status, b.service_location,
                     e.equipment_name,
                     COALESCE(b.operator_end_time, b.updated_at) AS completed_at
              FROM bookings b
              LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
              WHERE b.operator_id = ?
              AND b.status = \'completed\'
              ORDER BY COALESCE(b.operator_end_time, b.updated_at) DESC, b.booking_id DESC';
    if ($stmt = $conn->prepare($cjSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $completedJobs[] = $row;
        }
        $stmt->close();
    }

    $stats['dashboard_alerts'] = (int)$stats['damage_open'] + (int)$stats['jobs_pending'];

    $conn->close();
} catch (Exception $e) {
    error_log('Operator dashboard error: ' . $e->getMessage());
    $loadError = 'Could not load your workload and schedule. Error: ' . $e->getMessage() . '. Please try again later or contact admin.';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Dashboard - FES</title>
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
                    colors: {
                        fes: {
                            red: '#D32F2F',
                            dark: '#424242'
                        }
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

        /* Ensure main content sits beside sidebar on desktop, full-width on mobile */
        @media (max-width: 767px) {
            #main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        @media (min-width: 768px) {
            #main-content {
                margin-left: 256px !important; /* matches w-64 sidebar */
                width: calc(100% - 256px) !important;
            }
        }
    </style>
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100">
        <!-- Sidebar -->
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

        <!-- Main content -->
        <div class="min-h-screen" id="main-content">
            <!-- Top bar -->
            <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Operator</div>
                            <h1 class="text-xl font-semibold text-gray-900">Welcome, <?php echo htmlspecialchars($operatorName); ?></h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <a href="jobs.php" class="relative h-10 w-10 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Open my jobs" title="Jobs needing attention: pending work or damage follow-up">
                            <i class="fas fa-bell"></i>
                            <?php if ((int)$stats['dashboard_alerts'] > 0): ?>
                                <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red"></span>
                            <?php endif; ?>
                        </a>
                    </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <?php if (!empty($loadError)): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <?php echo htmlspecialchars($loadError); ?>
                    </div>
                <?php endif; ?>

                <!-- Core metrics only -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                    <a href="jobs.php" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow border <?php echo ((int)$stats['jobs_pending'] + (int)$stats['jobs_in_progress']) > 0 ? 'border-blue-100 ring-1 ring-blue-50' : 'border-transparent hover:border-gray-200'; ?>">
                        <div>
                            <div class="text-sm text-gray-500">Active work</div>
                            <div class="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="text-2xl font-semibold text-gray-900"><?php echo (int)$stats['jobs_pending']; ?></span>
                                <span class="text-xs text-gray-500">pending</span>
                                <span class="text-gray-300">·</span>
                                <span class="text-2xl font-semibold text-gray-900"><?php echo (int)$stats['jobs_in_progress']; ?></span>
                                <span class="text-xs text-gray-500">in progress</span>
                            </div>
                            <div class="mt-2 text-xs text-gray-400"><?php echo (int)$stats['jobs_upcoming_week']; ?> in the next 7 days</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </a>
                    <a href="jobs.php?status=completed" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between border border-gray-100 hover:shadow-md transition-shadow hover:border-emerald-100">
                        <div>
                            <div class="text-sm text-gray-500">Completed</div>
                            <div class="mt-1 text-3xl font-semibold text-emerald-600"><?php echo (int)$stats['jobs_completed']; ?></div>
                            <div class="mt-1 text-xs text-gray-500">All finished jobs</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </a>
                    <a href="feedback.php" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow border border-transparent hover:border-amber-100">
                        <div>
                            <div class="text-sm text-gray-500">Your rating</div>
                            <div class="mt-1 flex items-center gap-2">
                                <?php if ($stats['feedback_avg'] !== null): ?>
                                    <span class="text-2xl font-semibold text-amber-600"><?php echo htmlspecialchars((string)$stats['feedback_avg']); ?></span>
                                    <span class="text-sm text-gray-500">/5</span>
                                <?php else: ?>
                                    <span class="text-2xl font-semibold text-gray-400">—</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2 flex gap-0.5 text-amber-400">
                                <?php
                                $stars = $stats['feedback_avg'] !== null ? (int)round((float)$stats['feedback_avg']) : 0;
                                for ($si = 1; $si <= 5; $si++):
                                    ?>
                                    <i class="<?php echo $si <= $stars ? 'fas' : 'far'; ?> fa-star text-sm"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="mt-1 text-xs text-gray-500"><?php echo (int)$stats['feedback_count']; ?> review<?php echo (int)$stats['feedback_count'] === 1 ? '' : 's'; ?> · view feedback</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                            <i class="fas fa-star"></i>
                        </div>
                    </a>
                    <a href="<?php echo (int)$stats['damage_open'] > 0 ? 'job_damage.php' : 'jobs.php'; ?>" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow border <?php echo (int)$stats['damage_open'] > 0 ? 'border-orange-100 ring-1 ring-orange-50' : 'border-transparent hover:border-gray-200'; ?>">
                        <div>
                            <div class="text-sm text-gray-500">Damage queue</div>
                            <div class="mt-1 text-3xl font-semibold <?php echo (int)$stats['damage_open'] > 0 ? 'text-orange-600' : 'text-gray-900'; ?>"><?php echo (int)$stats['damage_open']; ?></div>
                            <div class="mt-1 text-xs text-gray-500"><?php echo (int)$stats['damage_open'] > 0 ? 'Open reports' : 'All clear'; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-hard-hat"></i>
                        </div>
                    </a>
                </div>

                <div class="flex flex-wrap gap-2 mb-6">
                    <a href="jobs.php" class="inline-flex items-center gap-2 rounded-full bg-fes-red px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#b71c1c]">My jobs</a>
                    <a href="feedback.php" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Customer feedback</a>
                    <a href="job_damage.php" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Damage reports</a>
                </div>

                <!-- Completed jobs — matches rest of operator UI (white cards) -->
                <section class="mb-6 rounded-xl bg-white shadow-card border border-gray-100 overflow-hidden">
                    <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-gray-100">
                        <div>
                            <h2 class="display text-xl font-bold text-gray-900 tracking-tight">Completed jobs</h2>
                            <p class="mt-1 text-sm text-gray-500">Every finished job assigned to you — open a row for details.</p>
                        </div>
                        <a href="jobs.php?status=completed" class="inline-flex items-center gap-2 self-start rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100">View on jobs list <i class="fas fa-arrow-right text-xs text-gray-500"></i></a>
                    </div>
                    <div class="p-4 sm:p-6 bg-gray-50/50">
                        <?php if (empty($completedJobs)): ?>
                            <div class="rounded-xl border border-dashed border-gray-200 bg-white px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-3xl text-emerald-600"><i class="fas fa-check-circle"></i></div>
                                <p class="text-lg font-semibold text-gray-900">No completed jobs yet</p>
                                <p class="mt-2 max-w-md mx-auto text-sm text-gray-500">When you finish work and mark jobs complete, they will all appear here.</p>
                                <a href="jobs.php" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-fes-red px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#b71c1c]">Go to my jobs</a>
                            </div>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($completedJobs as $cj): ?>
                                    <?php
                                    $doneRaw = $cj['completed_at'] ?? $cj['booking_date'] ?? '';
                                    $bdDow = fes_format_date_safe($doneRaw, 'D', '');
                                    $bdDay = fes_format_date_safe($doneRaw, 'j', '');
                                    $bdMon = fes_format_date_safe($doneRaw, 'M', '');
                                    ?>
                                    <li>
                                        <a href="job_details.php?id=<?php echo (int)$cj['booking_id']; ?>" class="group flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm hover:border-emerald-200 hover:shadow-md transition">
                                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                                <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-lg bg-emerald-50 text-center leading-tight py-1 text-emerald-800">
                                                    <span class="text-[9px] uppercase tracking-wide text-emerald-600/80"><?php echo htmlspecialchars($bdDow); ?></span>
                                                    <span class="text-xl font-bold leading-tight"><?php echo htmlspecialchars($bdDay); ?></span>
                                                    <span class="text-[9px] text-emerald-600/70"><?php echo htmlspecialchars($bdMon); ?></span>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-gray-900 truncate"><?php echo htmlspecialchars(ucfirst($cj['service_type'] ?? 'Service')); ?></div>
                                                    <div class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($cj['service_location'] ?? ''); ?></div>
                                                    <?php if (!empty($cj['equipment_name'])): ?>
                                                        <div class="text-xs text-gray-400 mt-0.5"><i class="fas fa-tractor mr-1"></i><?php echo htmlspecialchars($cj['equipment_name']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-800">Completed</span>
                                                <i class="fas fa-chevron-right text-gray-300 group-hover:text-emerald-600 text-sm"></i>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Equipment — visual, not a big empty table -->
                <section class="rounded-2xl bg-white shadow-card border border-gray-100 overflow-hidden">
                    <div class="grid grid-cols-1">
                        <div class="p-6 sm:p-8 bg-gradient-to-br from-slate-50 to-white">
                            <div class="flex items-start gap-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-800 text-white text-lg"><i class="fas fa-tractor"></i></span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Equipment</h2>
                                    <p class="mt-1 text-sm text-gray-500">Machines linked to your operator profile.</p>
                                </div>
                            </div>
                            <?php if (empty($equipment)): ?>
                                <div class="mt-6 rounded-xl border border-dashed border-gray-200 bg-white p-6 text-center">
                                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-500 text-2xl"><i class="fas fa-truck-pickup"></i></div>
                                    <p class="text-sm font-medium text-gray-800">No fleet items assigned to you yet</p>
                                    <p class="mt-2 text-sm text-gray-500">Equipment often appears when a job is matched to a machine. Check <strong>My jobs</strong> for what you are running next.</p>
                                    <a href="jobs.php" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-fes-red hover:underline">Open my jobs</a>
                                </div>
                            <?php else: ?>
                                <ul class="mt-6 space-y-3">
                                    <?php foreach ($equipment as $row): ?>
                                        <?php
                                        $status = $row['status'] ?? '';
                                        $cls = 'bg-gray-100 text-gray-700';
                                        if ($status === 'available') {
                                            $cls = 'bg-emerald-50 text-emerald-700';
                                        }
                                        if ($status === 'in_use') {
                                            $cls = 'bg-amber-50 text-amber-700';
                                        }
                                        if ($status === 'maintenance') {
                                            $cls = 'bg-red-50 text-red-700';
                                        }
                                        ?>
                                        <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($row['equipment_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['equipment_id']); ?> · <?php echo htmlspecialchars(ucfirst($row['category'] ?? '')); ?></div>
                                            </div>
                                            <span class="inline-flex shrink-0 items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $cls; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($skills)): ?>
                        <div class="px-6 sm:px-8 py-5 bg-gray-50 border-t border-gray-100">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Skills</div>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($skills as $skill): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-gray-200 px-3 py-1.5 text-sm text-gray-800">
                                        <?php echo htmlspecialchars(fes_operator_skill_type_label($skill['skill_name'] ?? '')); ?>
                                        <span class="text-xs text-gray-400"><?php echo htmlspecialchars(ucfirst($skill['skill_level'] ?? '')); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
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

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    closeSidebar();
                }
            });
        })();
    </script>

</body>

</html>




