<?php
session_start();

// Require operator role
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

$operatorName = $_SESSION['name'] ?? 'Operator';
$operatorId = (int)($_SESSION['user_id'] ?? 0);
$jobs = [];
$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

require_once __DIR__ . '/../../includes/database.php';
try {
    $conn = getDBConnection();
    $sql = "SELECT b.booking_id, b.booking_date, b.service_type, b.service_days, b.status,
                   COALESCE(NULLIF(b.service_location, ''), b.field_address) AS service_location,
                   b.field_hectares,
                   e.equipment_name,
                   u.name AS customer_name
            FROM bookings b
            LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
            LEFT JOIN users u ON u.user_id = b.customer_id
            WHERE b.operator_id = ?";
    if ($statusFilter !== 'all') {
        $sql .= " AND b.status = ?";
    }
    $sql .= " ORDER BY b.booking_date DESC, b.booking_id DESC";

    if ($stmt = $conn->prepare($sql)) {
        if ($statusFilter !== 'all') {
            $stmt->bind_param('is', $operatorId, $statusFilter);
        } else {
            $stmt->bind_param('i', $operatorId);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $jobs[] = $row;
        }
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log('Operator jobs error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs - FES Operator</title>
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
                    <div class="text-sm text-gray-500">Operator</div>
                    <h1 class="text-xl font-semibold text-gray-900">My Jobs</h1>
                    <p class="text-xs text-gray-500 mt-1">Assigned jobs for <?php echo htmlspecialchars($operatorName); ?></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center gap-2 text-xs text-gray-500 border border-gray-200 rounded-lg px-3 py-1.5">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span>Pending</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span>In Progress</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Completed</span>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <section class="bg-white rounded-xl shadow-card p-5 mb-6">
                <form class="grid grid-cols-1 md:grid-cols-4 gap-3" method="get">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                        <input type="text" name="q" placeholder="Search by booking ID, customer, or equipment (coming soon)" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" disabled>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="inline-flex items-center justify-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow text-sm">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="jobs.php" class="inline-flex items-center justify-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-3 py-2.5 rounded-lg text-sm">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-xl shadow-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900">Assigned Jobs</h2>
                    <span class="text-xs text-gray-500"><?php echo count($jobs); ?> job(s)</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                        <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                            <th class="py-3 pr-4">Booking ID</th>
                            <th class="py-3 pr-4">Customer</th>
                            <th class="py-3 pr-4">Service Type</th>
                            <th class="py-3 pr-4">Location</th>
                            <th class="py-3 pr-4">Land Size</th>
                            <th class="py-3 pr-4">Service Date</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3">Action</th>
                        </tr>
                        </thead>
                        <tbody class="text-sm text-gray-900">
                        <?php if (!empty($jobs)): ?>
                            <?php foreach ($jobs as $row): ?>
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
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['service_type'] ?? 'N/A'))); ?></td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars($row['service_location'] ?? 'N/A'); ?></td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars((string)($row['field_hectares'] ?? 'N/A')); ?> acres</td>
                                    <td class="py-3 pr-4"><?php echo !empty($row['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['booking_date']))) : 'N/A'; ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <a href="job_details.php?id=<?php echo urlencode((string)$row['booking_id']); ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-fes-red hover:bg-[#b71c1c] text-white text-sm font-medium">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-6 text-center text-sm text-gray-500">No assigned jobs found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
    })();
</script>
</body>
</html>
