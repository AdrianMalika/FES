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

$operatorName = $_SESSION['name'] ?? 'Operator';
require_once __DIR__ . '/../../includes/database.php';

$operatorId = (int)($_SESSION['user_id'] ?? 0);
$notifications = [];

try {
    $conn = getDBConnection();
    $sql = "SELECT 
                b.booking_id,
                b.status,
                b.service_type,
                b.service_location,
                b.booking_date,
                b.updated_at,
                b.created_at,
                e.equipment_name,
                u.name AS customer_name
            FROM bookings b
            LEFT JOIN equipment e 
                ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
            LEFT JOIN users u ON u.user_id = b.customer_id
            WHERE b.operator_id = ?
            ORDER BY b.updated_at DESC
            LIMIT 30";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    }
    $conn->close();
} catch (Throwable $e) {
    error_log('Operator notifications load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - FES Operator</title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { fes: { red: '#D32F2F', dark: '#424242' } } } } };</script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4 { font-family: 'Barlow Condensed', sans-serif; }
        @media (max-width: 767px) { #main-content { margin-left: 0 !important; width: 100% !important; } }
        @media (min-width: 768px) { #main-content { margin-left: 256px !important; width: calc(100% - 256px) !important; } }
    </style>
</head>
<body class="bg-gray-100">
<div class="min-h-screen w-full">
    <?php include __DIR__ . '/include/sidebar.php'; ?>
    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <div class="min-h-screen" id="main-content">
        <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator</div>
                    <h1 class="text-xl font-semibold text-gray-900">Notifications</h1>
                    <p class="text-xs text-gray-500 mt-1">Live updates from your assigned bookings appear here.</p>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <section class="bg-white rounded-xl shadow-card p-5 max-w-3xl">
                <h2 class="text-base font-semibold text-gray-900 mb-4">All notifications</h2>
                <p class="text-sm text-gray-500 mb-4">New job assignments, booking updates, and admin messages.</p>
                <ul class="divide-y divide-gray-200">
                    <?php if (empty($notifications)): ?>
                        <li class="py-4">
                            <p class="text-sm text-gray-500">No notifications yet. New job assignments will appear here.</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <?php
                            $status = (string)($n['status'] ?? '');
                            $isUnread = in_array($status, ['pending', 'confirmed'], true);
                            $dotClass = $isUnread ? 'bg-fes-red' : 'bg-gray-300';
                            $rowClass = $isUnread ? ' bg-red-50/50' : '';
                            $textClass = $isUnread ? 'text-gray-900 font-medium' : 'text-gray-700';
                            $badgeClass = $isUnread ? 'text-fes-red font-medium' : 'text-gray-500';
                            $serviceType = ucfirst(str_replace('_', ' ', (string)($n['service_type'] ?? 'service')));
                            $serviceLocation = (string)($n['service_location'] ?? 'N/A');
                            $equipmentName = (string)($n['equipment_name'] ?? 'Unknown equipment');
                            $customerName = (string)($n['customer_name'] ?? 'Unknown customer');
                            $updatedAtRaw = (string)($n['updated_at'] ?? '');
                            $timestamp = $updatedAtRaw !== '' ? date('M d, Y · H:i', strtotime($updatedAtRaw)) : '—';
                            $statusLabel = str_replace('_', ' ', $status);
                            ?>
                            <li class="py-4 flex items-start gap-3<?php echo $rowClass; ?>">
                                <span class="mt-1.5 h-2.5 w-2.5 rounded-full <?php echo $dotClass; ?> flex-shrink-0" title="<?php echo $isUnread ? 'Unread' : 'Read'; ?>"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm <?php echo $textClass; ?>">
                                        You have been assigned to booking <span class="font-semibold">#<?php echo htmlspecialchars((string)$n['booking_id']); ?></span>
                                        for <?php echo htmlspecialchars($serviceType); ?> at <?php echo htmlspecialchars($serviceLocation); ?>
                                    </p>
                                    <p class="text-sm text-gray-700 mt-1">
                                        <?php echo htmlspecialchars($equipmentName); ?> · <?php echo htmlspecialchars($customerName); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($timestamp); ?></p>
                                </div>
                                <span class="text-xs <?php echo $badgeClass; ?> flex-shrink-0"><?php echo htmlspecialchars($statusLabel); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
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
    function openSidebar() { sidebar.classList.remove('-translate-x-full'); sidebar.classList.add('translate-x-0'); overlay.classList.remove('hidden'); }
    function closeSidebar() { sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0'); overlay.classList.add('hidden'); }
    btn.addEventListener('click', function () { sidebar.classList.contains('translate-x-0') ? closeSidebar() : openSidebar(); });
    overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>


