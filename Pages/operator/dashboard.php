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

$operatorId   = (int)($_SESSION['user_id'] ?? 0);
$operatorName = $_SESSION['name'] ?? 'Operator';

$equipment    = [];
$skills       = [];
$availability = [];
$stats = [
    'assigned'     => 0,
    'active'       => 0,
    'slots'        => 0,
    'skills'       => 0,
    'jobs_total'   => 0,
    'jobs_in_progress' => 0,
    'jobs_completed_today' => 0,
    'unread_notifications' => 0,
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

    // Load weekly availability
    $avSql = '
        SELECT id, day_of_week, start_time, end_time, is_available
        FROM operator_availability
        WHERE operator_id = ?
        ORDER BY day_of_week ASC, start_time ASC
    ';
    if ($stmt = $conn->prepare($avSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $availability[] = $row;
        }
        $stmt->close();
    }
    $stats['slots'] = count($availability);

    // Job stats based on bookings assigned to this operator
    $jobSql = "SELECT
                    COUNT(*) AS total,
                    SUM(status = 'in_progress') AS in_progress,
                    SUM(status = 'completed' AND DATE(COALESCE(operator_end_time, updated_at)) = CURDATE()) AS completed_today
               FROM bookings
               WHERE operator_id = ?";
    if ($stmt = $conn->prepare($jobSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['jobs_total'] = (int)($row['total'] ?? 0);
            $stats['jobs_in_progress'] = (int)($row['in_progress'] ?? 0);
            $stats['jobs_completed_today'] = (int)($row['completed_today'] ?? 0);
        }
        $stmt->close();
    }

    // Notifications placeholder (add table later)
    $stats['unread_notifications'] = 0;

    $conn->close();
} catch (Exception $e) {
    error_log('Operator dashboard error: ' . $e->getMessage());
    $loadError = 'Could not load your workload and schedule. Error: ' . $e->getMessage() . '. Please try again later or contact admin.';
}

$dayNames = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];
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
                        <button class="relative h-10 w-10 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red"></span>
                        </button>
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

                <!-- Job & notification overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-5">
                    <a href="jobs.php" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow">
                        <div>
                            <div class="text-sm text-gray-500">Total Assigned Jobs</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['jobs_total']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </a>
                    <a href="jobs.php?status=in_progress" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow">
                        <div>
                            <div class="text-sm text-gray-500">Jobs In Progress</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['jobs_in_progress']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </a>
                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Completed Today</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['jobs_completed_today']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <a href="notifications.php" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow relative">
                        <div>
                            <div class="text-sm text-gray-500">Unread Notifications</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['unread_notifications']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-red-50 text-fes-red flex items-center justify-center">
                            <i class="fas fa-bell"></i>
                        </div>
                        <?php if ($stats['unread_notifications'] > 0): ?>
                            <span class="absolute top-3 right-3 h-2 w-2 rounded-full bg-fes-red"></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Equipment & availability stats -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Assigned Equipment</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['assigned']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-slate-50 text-slate-600 flex items-center justify-center">
                            <i class="fas fa-tractor"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Availability Slots</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['slots']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Skills</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['skills']; ?></div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-tools"></i>
                        </div>
                    </div>
                    <a href="jobs.php" class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between hover:shadow-md transition-shadow items-center">
                        <span class="text-sm font-medium text-fes-red">View all jobs</span>
                        <i class="fas fa-arrow-right text-fes-red"></i>
                    </a>
                </div>

                <!-- Assigned equipment -->
                <section class="bg-white rounded-xl shadow-card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-900">Your Assigned Equipment</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 border-b">
                                    <th class="py-3 pr-4">Equipment</th>
                                    <th class="py-3 pr-4">Category</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4">Location</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-900">
                                <?php if (empty($equipment)): ?>
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-gray-500">
                                            <i class="fas fa-truck-moving text-2xl mb-2 block text-gray-300"></i>
                                            No equipment is currently assigned to you.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($equipment as $row): ?>
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 pr-4">
                                                <div class="font-medium"><?php echo htmlspecialchars($row['equipment_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['equipment_id']); ?></div>
                                            </td>
                                            <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst($row['category'] ?? '')); ?></td>
                                            <td class="py-3 pr-4">
                                                <?php
                                                $status = $row['status'] ?? '';
                                                $cls = 'bg-gray-100 text-gray-700';
                                                if ($status === 'available') $cls = 'bg-emerald-50 text-emerald-700';
                                                if ($status === 'in_use') $cls = 'bg-amber-50 text-amber-700';
                                                if ($status === 'maintenance') $cls = 'bg-red-50 text-red-700';
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $cls; ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4 text-gray-600">
                                                <?php echo htmlspecialchars($row['location'] ?? '-'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <!-- Weekly availability -->
                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Weekly Availability</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b">
                                        <th class="py-3 pr-4">Day</th>
                                        <th class="py-3 pr-4">Time</th>
                                        <th class="py-3 pr-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <?php if (empty($availability)): ?>
                                        <tr>
                                            <td colspan="3" class="py-8 text-center text-gray-500">
                                                Your administrator has not configured your availability yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($availability as $slot): ?>
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 pr-4">
                                                    <?php
                                                    $d = (int)($slot['day_of_week'] ?? 0);
                                                    echo htmlspecialchars($dayNames[$d] ?? ('Day ' . $d));
                                                    ?>
                                                </td>
                                                <td class="py-3 pr-4 text-gray-700">
                                                    <?php echo htmlspecialchars(substr($slot['start_time'], 0, 5)); ?>
                                                    –
                                                    <?php echo htmlspecialchars(substr($slot['end_time'], 0, 5)); ?>
                                                </td>
                                                <td class="py-3 pr-4">
                                                    <?php if ((int)($slot['is_available'] ?? 1) === 1): ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                                            Available
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                            Unavailable
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Skills -->
                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Skills</h2>
                        </div>
                        <?php if (empty($skills)): ?>
                            <p class="text-sm text-gray-500">
                                No skills have been recorded for your profile yet. Contact your administrator if this is incorrect.
                            </p>
                        <?php else: ?>
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($skills as $skill): ?>
                                    <li class="py-3 flex items-center justify-between">
                                        <div>
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($skill['skill_name']); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo (($added = fes_format_date_safe($skill['created_at'] ?? null, 'M d, Y', '')) !== '') ? 'Added ' . htmlspecialchars($added) : ''; ?>
                                            </div>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                                            <?php echo htmlspecialchars(ucfirst($skill['skill_level'] ?? '')); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </div>
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




