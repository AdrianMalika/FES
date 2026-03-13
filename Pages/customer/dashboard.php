<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has customer role
if ($_SESSION['role'] !== 'customer') {
    // Redirect based on actual role
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

$bookingStats = [
    'pending' => 0,
    'confirmed' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$recentBookings = [];

try {
    $conn = getDBConnection();
    $customerId = intval($_SESSION['user_id']);

    // Status counts
    $sql = "SELECT status, COUNT(*) as total FROM bookings WHERE customer_id = ? GROUP BY status";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $status = $row['status'];
            if (isset($bookingStats[$status])) {
                $bookingStats[$status] = intval($row['total']);
            }
        }
        $stmt->close();
    }

    // Recent bookings
    $sql = "SELECT b.booking_id, b.booking_date, b.status, e.equipment_name
            FROM bookings b
            JOIN equipment e ON e.equipment_id = b.equipment_id
            WHERE b.customer_id = ?
            ORDER BY b.created_at DESC
            LIMIT 5";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $recentBookings[] = $row;
        }
        $stmt->close();
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Customer dashboard data error: ' . $e->getMessage());
}

$activeBookings = $bookingStats['confirmed'] + $bookingStats['in_progress'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - FES</title>
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
    </style>
</head>

<body>
    <div class="min-h-screen w-full bg-gray-100">
        <div class="flex min-h-screen">
            <!-- Sidebar -->
            <?php include __DIR__ . '/include/sidebar.php'; ?>

            <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

            <!-- Main -->
            <div class="flex-1 flex flex-col min-w-0 md:ml-64">
                <!-- Top bar -->
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Customer</div>
                            <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <button class="relative h-10 w-10 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-fes-red"></span>
                        </button>
                        <a href="../equipment.php" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow">
                            <i class="fas fa-plus"></i>
                            New Booking
                        </a>
                    </div>
                </header>

                <!-- Content -->
                <main class="flex-1 overflow-y-auto p-6">
                    <!-- Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Active Bookings</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$activeBookings); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Pending Requests</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['pending']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Completed Rentals</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['completed']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Outstanding Balance</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">MK 0</div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-red-50 text-fes-red flex items-center justify-center">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Recent bookings -->
                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Recent Bookings</h2>
                            <a href="#" class="text-sm font-medium text-fes-red hover:underline">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b">
                                        <th class="py-3 pr-4">ID</th>
                                        <th class="py-3 pr-4">Equipment</th>
                                        <th class="py-3 pr-4">Dates</th>
                                        <th class="py-3 pr-4">Status</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <?php if (!empty($recentBookings)): ?>
                                        <?php foreach ($recentBookings as $row): ?>
                                            <?php
                                                $status = $row['status'] ?? 'pending';
                                                $badgeClasses = [
                                                    'pending' => 'bg-amber-50 text-amber-700',
                                                    'confirmed' => 'bg-blue-50 text-blue-700',
                                                    'in_progress' => 'bg-purple-50 text-purple-700',
                                                    'completed' => 'bg-emerald-50 text-emerald-700',
                                                    'cancelled' => 'bg-gray-100 text-gray-700'
                                                ];
                                                $badgeClass = $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700';
                                            ?>
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 pr-4 font-medium">#BK-<?php echo htmlspecialchars((string)$row['booking_id']); ?></td>
                                                <td class="py-3 pr-4"><?php echo htmlspecialchars($row['equipment_name'] ?? 'N/A'); ?></td>
                                                <td class="py-3 pr-4">
                                                    <?php echo !empty($row['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['booking_date']))) : 'N/A'; ?>
                                                </td>
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3">
                                                    <button class="text-gray-500 hover:text-gray-900" aria-label="More"><i class="fas fa-ellipsis-h"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="py-6 text-center text-sm text-gray-500" colspan="5">No bookings yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                        <!-- Recommended -->
                        <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Recommended For You</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 transition">
                                    <div class="text-sm text-gray-500">Tractor</div>
                                    <div class="mt-1 font-semibold text-gray-900">Massey Ferguson 2600</div>
                                    <div class="mt-2 text-xs text-gray-500">Popular for land prep and haulage</div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 hover:bg-gray-50 transition">
                                    <div class="text-sm text-gray-500">Harvester</div>
                                    <div class="mt-1 font-semibold text-gray-900">Harvester X5</div>
                                    <div class="mt-2 text-xs text-gray-500">High throughput harvesting</div>
                                </div>
                            </div>
                        </section>

                        <!-- Quick actions -->
                        <section class="bg-white rounded-xl shadow-card p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
                            <div class="space-y-3">
                                <a href="../equipment.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-search text-fes-red"></i>
                                    Browse Equipment
                                </a>
                                <a href="../equipment.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-calendar-plus text-fes-red"></i>
                                    Request Booking
                                </a>
                                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium">
                                    <i class="fas fa-file-invoice-dollar text-fes-red"></i>
                                    View Payments
                                </button>
                            </div>
                        </section>
                    </div>
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
    </script>

</body>

</html>

