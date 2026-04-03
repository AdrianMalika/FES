<?php
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

$adminName = $_SESSION['name'] ?? 'Admin';

$stats = [
    'equipment'       => 0,
    'active_bookings' => 0,
    'pending'         => 0,
    'customers'       => 0,
    'month_paid_mwk'  => 0.0,
    'open_damage'     => 0,
];
$recentBookings = [];
$fleetByStatus = [];
$loadError = '';

$badgeClasses = [
    'pending'     => 'bg-amber-50 text-amber-700',
    'confirmed'   => 'bg-blue-50 text-blue-700',
    'in_progress' => 'bg-purple-50 text-purple-700',
    'completed'   => 'bg-emerald-50 text-emerald-700',
    'cancelled'   => 'bg-gray-100 text-gray-700',
];

try {
    $conn = getDBConnection();

    if ($r = $conn->query('SELECT COUNT(*) AS c FROM equipment')) {
        $row = $r->fetch_assoc();
        $stats['equipment'] = (int)($row['c'] ?? 0);
        $r->close();
    }

    if ($r = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status IN ('pending','confirmed','in_progress')")) {
        $row = $r->fetch_assoc();
        $stats['active_bookings'] = (int)($row['c'] ?? 0);
        $r->close();
    }

    if ($r = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE status = 'pending'")) {
        $row = $r->fetch_assoc();
        $stats['pending'] = (int)($row['c'] ?? 0);
        $r->close();
    }

    if ($r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")) {
        $row = $r->fetch_assoc();
        $stats['customers'] = (int)($row['c'] ?? 0);
        $r->close();
    }

    $revSql = "SELECT COALESCE(SUM(estimated_total_cost), 0) AS rev FROM bookings
               WHERE payment_status = 'paid'
               AND DATE_FORMAT(COALESCE(payment_paid_at, updated_at), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    if ($r = $conn->query($revSql)) {
        $row = $r->fetch_assoc();
        $stats['month_paid_mwk'] = (float)($row['rev'] ?? 0);
        $r->close();
    }

    try {
        if ($r = $conn->query("SELECT COUNT(*) AS c FROM damage_reports WHERE status IN ('submitted','acknowledged')")) {
            $row = $r->fetch_assoc();
            $stats['open_damage'] = (int)($row['c'] ?? 0);
            $r->close();
        }
    } catch (Throwable $e) {
        error_log('Admin dashboard damage count: ' . $e->getMessage());
    }

    if ($r = $conn->query('SELECT status, COUNT(*) AS c FROM equipment GROUP BY status')) {
        while ($row = $r->fetch_assoc()) {
            $fleetByStatus[(string)($row['status'] ?? '')] = (int)($row['c'] ?? 0);
        }
        $r->close();
    }

    $rbSql = 'SELECT b.booking_id, b.booking_date, b.status, b.service_type,
                     e.equipment_name, u.name AS customer_name
              FROM bookings b
              INNER JOIN equipment e ON e.equipment_id = b.equipment_id
              INNER JOIN users u ON u.user_id = b.customer_id
              ORDER BY b.created_at DESC
              LIMIT 8';
    if ($r = $conn->query($rbSql)) {
        while ($row = $r->fetch_assoc()) {
            $recentBookings[] = $row;
        }
        $r->close();
    }

    $conn->close();
} catch (Throwable $e) {
    error_log('Admin dashboard: ' . $e->getMessage());
    $loadError = 'Could not load live dashboard data. Please refresh or check the database connection.';
}

$fleetTotal = array_sum($fleetByStatus);
$dashAlerts = (int)$stats['pending'] + (int)$stats['open_damage'];
$fmtMwk = static function (float $n): string {
    return 'MWK ' . number_format($n, 0, '.', ',');
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FES</title>
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
        @media (max-width: 767px) {
            #main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        @media (min-width: 768px) {
            #main-content {
                margin-left: 300px !important;
                width: calc(100% - 300px) !important;
            }
        }
    </style>
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100">
        <?php include __DIR__ . '/include/sidebar.php'; ?>

        <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

        <div class="min-h-screen" style="margin-left: 300px; width: calc(100% - 300px);" id="main-content">
            <header class="bg-white px-6 py-7 flex flex-wrap items-center justify-between gap-4 shadow-sm md:pl-6">
                <div class="flex items-center gap-3">
                    <button id="fes-dashboard-menu-btn" type="button" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <div class="text-sm text-gray-500">Admin</div>
                        <h1 class="text-xl font-semibold text-gray-900">Welcome, <?php echo htmlspecialchars($adminName); ?></h1>
                        <p class="text-xs text-gray-500 mt-1">Overview from your live FES database</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="bookings.php" class="relative h-10 w-10 inline-flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Bookings" title="Bookings">
                        <i class="fas fa-bell"></i>
                        <?php if ($dashAlerts > 0): ?>
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red" title="<?php echo (int)$dashAlerts; ?> items need attention"></span>
                        <?php endif; ?>
                    </a>
                    <a href="add_equipment.php" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow text-sm">
                        <i class="fas fa-plus"></i>
                        Add equipment
                    </a>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
                <?php if ($loadError !== ''): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <?php echo htmlspecialchars($loadError); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                    <a href="equipment.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-start justify-between hover:shadow-md transition-shadow">
                        <div>
                            <div class="text-sm text-gray-500">Equipment in fleet</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['equipment']; ?></div>
                            <div class="mt-1 text-xs text-gray-400">All registered machines</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-red-50 text-fes-red flex items-center justify-center shrink-0">
                            <i class="fas fa-tractor"></i>
                        </div>
                    </a>
                    <a href="bookings.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-start justify-between hover:shadow-md transition-shadow">
                        <div>
                            <div class="text-sm text-gray-500">Active bookings</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$stats['active_bookings']; ?></div>
                            <div class="mt-1 text-xs text-gray-400">Pending, confirmed, or in progress</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </a>
                    <a href="bookings.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-start justify-between hover:shadow-md transition-shadow <?php echo $stats['pending'] > 0 ? 'ring-1 ring-amber-100' : ''; ?>">
                        <div>
                            <div class="text-sm text-gray-500">Pending approval</div>
                            <div class="mt-1 text-2xl font-semibold <?php echo $stats['pending'] > 0 ? 'text-amber-600' : 'text-gray-900'; ?>"><?php echo (int)$stats['pending']; ?></div>
                            <div class="mt-1 text-xs text-gray-400">Bookings awaiting status update</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-clock"></i>
                        </div>
                    </a>
                    <div class="bg-white rounded-xl shadow-card border border-gray-100 p-5 flex items-start justify-between">
                        <div>
                            <div class="text-sm text-gray-500">Paid this month</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars($fmtMwk($stats['month_paid_mwk'])); ?></div>
                            <div class="mt-1 text-xs text-gray-400">Sum of paid booking amounts (<?php echo (int)date('n'); ?>/<?php echo (int)date('Y'); ?>)</div>
                        </div>
                        <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
                    <a href="users.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-4 flex items-center justify-between hover:border-gray-200">
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Customers</div>
                            <div class="text-xl font-semibold text-gray-900"><?php echo (int)$stats['customers']; ?></div>
                        </div>
                        <i class="fas fa-user-friends text-gray-300"></i>
                    </a>
                    <a href="damage_reports.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-4 flex items-center justify-between hover:border-gray-200 <?php echo $stats['open_damage'] > 0 ? 'ring-1 ring-orange-100' : ''; ?>">
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Open damage reports</div>
                            <div class="text-xl font-semibold <?php echo $stats['open_damage'] > 0 ? 'text-orange-600' : 'text-gray-900'; ?>"><?php echo (int)$stats['open_damage']; ?></div>
                        </div>
                        <i class="fas fa-exclamation-triangle text-gray-300"></i>
                    </a>
                    <a href="feedback.php" class="bg-white rounded-xl shadow-card border border-gray-100 p-4 flex items-center justify-between hover:border-gray-200">
                        <div>
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Customer feedback</div>
                            <div class="text-sm font-medium text-fes-red mt-1">View reviews</div>
                        </div>
                        <i class="fas fa-comments text-gray-300"></i>
                    </a>
                </div>

                <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5 mb-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <h2 class="text-base font-semibold text-gray-900">Recent bookings</h2>
                        <a href="bookings.php" class="text-sm font-medium text-fes-red hover:underline">View all bookings</a>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-100">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 bg-gray-50 border-b border-gray-100 uppercase tracking-wider">
                                    <th class="py-3 px-4">ID</th>
                                    <th class="py-3 pr-4">Customer</th>
                                    <th class="py-3 pr-4">Equipment</th>
                                    <th class="py-3 pr-4">Service</th>
                                    <th class="py-3 pr-4">Date</th>
                                    <th class="py-3 pr-4">Status</th>
                                    <th class="py-3 pr-4 w-24">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-900">
                                <?php if (empty($recentBookings)): ?>
                                    <tr>
                                        <td colspan="7" class="py-10 px-4 text-center text-gray-500">No bookings yet. Activity will appear here when customers book.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBookings as $row): ?>
                                        <?php
                                        $status = $row['status'] ?? 'pending';
                                        $badgeClass = $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                        ?>
                                        <tr class="border-b border-gray-50 hover:bg-gray-50/80">
                                            <td class="py-3 px-4 font-medium">#<?php echo (int)$row['booking_id']; ?></td>
                                            <td class="py-3 pr-4"><?php echo htmlspecialchars($row['customer_name'] ?? '—'); ?></td>
                                            <td class="py-3 pr-4"><?php echo htmlspecialchars($row['equipment_name'] ?? '—'); ?></td>
                                            <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($row['service_type'] ?? '')))); ?></td>
                                            <td class="py-3 pr-4 text-gray-600"><?php echo !empty($row['booking_date']) ? htmlspecialchars(date('M j, Y', strtotime($row['booking_date']))) : '—'; ?></td>
                                            <td class="py-3 pr-4">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <a href="booking-details.php?id=<?php echo (int)$row['booking_id']; ?>" class="text-fes-red font-medium hover:underline">Open</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <section class="lg:col-span-2 bg-white rounded-xl shadow-card border border-gray-100 p-5">
                        <h2 class="text-base font-semibold text-gray-900 mb-1">Fleet by status</h2>
                        <p class="text-xs text-gray-500 mb-4">Equipment rows grouped by current status in the database.</p>
                        <?php
                        $statusLabels = [
                            'available'   => 'Available',
                            'in_use'      => 'In use',
                            'maintenance' => 'Maintenance',
                            'retired'     => 'Retired',
                        ];
                        $barColors = [
                            'available'   => 'bg-emerald-500',
                            'in_use'      => 'bg-amber-500',
                            'maintenance' => 'bg-fes-red',
                            'retired'     => 'bg-gray-400',
                        ];
                        ?>
                        <?php if ($fleetTotal < 1): ?>
                            <p class="text-sm text-gray-500 py-6">No equipment records yet. Add machines under <a href="add_equipment.php" class="text-fes-red font-medium hover:underline">Add equipment</a>.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($statusLabels as $key => $label): ?>
                                    <?php
                                    $cnt = (int)($fleetByStatus[$key] ?? 0);
                                    $pct = $fleetTotal > 0 ? round(100 * $cnt / $fleetTotal) : 0;
                                    $bar = $barColors[$key] ?? 'bg-gray-400';
                                    ?>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($label); ?></span>
                                            <span class="text-gray-500"><?php echo (int)$cnt; ?> <span class="text-gray-400">(<?php echo (int)$pct; ?>%)</span></span>
                                        </div>
                                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $bar; ?> transition-all" style="width: <?php echo max(0, min(100, $pct)); ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="equipment.php" class="mt-4 inline-flex text-sm font-medium text-fes-red hover:underline">Manage equipment</a>
                        <?php endif; ?>
                    </section>

                    <section class="bg-white rounded-xl shadow-card border border-gray-100 p-5">
                        <h2 class="text-base font-semibold text-gray-900 mb-4">Quick links</h2>
                        <div class="space-y-2">
                            <a href="add_operator.php" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                <i class="fas fa-user-plus text-fes-red w-5 text-center"></i>
                                Add operator
                            </a>
                            <a href="users.php" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                <i class="fas fa-users text-fes-red w-5 text-center"></i>
                                Manage operators
                            </a>
                            <a href="bookings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                <i class="fas fa-calendar-check text-fes-red w-5 text-center"></i>
                                All bookings
                            </a>
                            <a href="damage_reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                <i class="fas fa-hard-hat text-fes-red w-5 text-center"></i>
                                Damage reports
                            </a>
                            <a href="feedback.php" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                <i class="fas fa-star text-fes-red w-5 text-center"></i>
                                Customer feedback
                            </a>
                        </div>
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
                sidebar.classList.add('show');
                overlay.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.remove('show');
                overlay.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }

            btn.addEventListener('click', function () {
                var isOpen = sidebar.classList.contains('translate-x-0') || sidebar.classList.contains('show');
                if (isOpen) closeSidebar();
                else openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 768px)').matches) {
                    overlay.classList.add('hidden');
                    btn.setAttribute('aria-expanded', 'false');
                    sidebar.classList.remove('show');
                } else {
                    closeSidebar();
                }
            });
        })();
    </script>

</body>

</html>
