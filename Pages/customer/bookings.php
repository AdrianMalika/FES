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
require_once __DIR__ . '/../../includes/fes_date.php';

$bookingStats = [
    'pending' => 0,
    'confirmed' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$bookings = [];

try {
    $conn = getDBConnection();
    $customerId = intval($_SESSION['user_id']);

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

    $sql = "SELECT b.booking_id, b.booking_date, b.service_days, b.service_type,
                   COALESCE(NULLIF(b.service_location, ''), b.field_address) AS service_location,
                   b.status, b.estimated_total_cost, e.equipment_name
            FROM bookings b
            JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
            WHERE b.customer_id = ?
            ORDER BY b.created_at DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $bookings[] = $row;
        }
        $stmt->close();
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Customer bookings data error: ' . $e->getMessage());
}

$customerName = $_SESSION['name'] ?? 'Customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - FES</title>
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
                            <h1 class="text-xl font-semibold text-gray-900">Bookings</h1>
                            <p class="text-xs text-gray-500 mt-1">Welcome back, <?php echo htmlspecialchars($customerName); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="../equipment.php" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2 rounded-lg shadow">
                            <i class="fas fa-plus"></i>
                            New Booking
                        </a>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Pending</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['pending']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Confirmed</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['confirmed']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">In Progress</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['in_progress']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <i class="fas fa-tractor"></i>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-card p-5 flex items-start justify-between">
                            <div>
                                <div class="text-sm text-gray-500">Completed</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo htmlspecialchars((string)$bookingStats['completed']); ?></div>
                            </div>
                            <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>

                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">All Bookings</h2>
                            <span class="text-xs text-gray-500"><?php echo count($bookings); ?> total</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                        <th class="py-3 pr-4">Booking ID</th>
                                        <th class="py-3 pr-4">Equipment</th>
                                        <th class="py-3 pr-4">Date</th>
                                        <th class="py-3 pr-4">Service</th>
                                        <th class="py-3 pr-4">Location</th>
                                        <th class="py-3 pr-4">Days</th>
                                        <th class="py-3 pr-4">Total</th>
                                        <th class="py-3 pr-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <?php if (!empty($bookings)): ?>
                                        <?php foreach ($bookings as $row): ?>
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
                                            <tr class="border-b hover:bg-gray-50 cursor-pointer" onclick="window.location.href='booking-details.php?id=<?php echo urlencode((string)$row['booking_id']); ?>'">
                                                <td class="py-3 pr-4 font-medium text-fes-red">#BK-<?php echo htmlspecialchars((string)$row['booking_id']); ?></td>
                                                <td class="py-3 pr-4 text-gray-900"><?php echo htmlspecialchars($row['equipment_name'] ?? 'N/A'); ?></td>
                                                <td class="py-3 pr-4">
                                                    <?php echo htmlspecialchars(fes_format_date_safe($row['booking_date'] ?? null, 'M d, Y', 'N/A')); ?>
                                                </td>
                                                <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['service_type'] ?? 'N/A'))); ?></td>
                                                <td class="py-3 pr-4"><?php echo htmlspecialchars($row['service_location'] ?? 'N/A'); ?></td>
                                                <td class="py-3 pr-4"><?php echo htmlspecialchars((string)($row['service_days'] ?? 1)); ?></td>
                                                <td class="py-3 pr-4">MK <?php echo number_format((float)($row['estimated_total_cost'] ?? 0)); ?></td>
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="py-6 text-center text-sm text-gray-500" colspan="8">No bookings yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
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

