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
require_once __DIR__ . '/../../includes/fes_date.php';

$filterBooking = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$rows = [];
$loadError = '';
$avgAll = null;
$countAll = 0;
$conn = null;

try {
    $conn = getDBConnection();

    $whereSql = '1=1';
    if ($filterBooking > 0) {
        $whereSql .= ' AND bf.booking_id = ' . (int)$filterBooking;
    }

    $sql = "SELECT bf.feedback_id, bf.booking_id, bf.customer_id, bf.operator_id, bf.rating, bf.comment, bf.created_at,
                   c.name AS customer_name, c.email AS customer_email,
                   o.name AS operator_name,
                   b.status AS booking_status
            FROM booking_feedback bf
            LEFT JOIN users c ON c.user_id = bf.customer_id
            LEFT JOIN users o ON o.user_id = bf.operator_id
            LEFT JOIN bookings b ON b.booking_id = bf.booking_id
            WHERE {$whereSql}
            ORDER BY bf.created_at DESC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    } else {
        throw new Exception($conn->error ?: 'query failed');
    }

    $sumRes = $conn->query('SELECT AVG(rating) AS av, COUNT(*) AS c FROM booking_feedback');
    if ($sumRes && ($sumRow = $sumRes->fetch_assoc())) {
        $avgAll = $sumRow['av'] !== null ? round((float)$sumRow['av'], 2) : null;
        $countAll = (int)($sumRow['c'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('Admin feedback list: ' . $e->getMessage());
    $errno = ($conn instanceof mysqli) ? $conn->errno : 0;
    if ($errno === 1146 || str_contains($e->getMessage(), 'booking_feedback')) {
        $loadError = 'The booking_feedback table is missing. In phpMyAdmin, run database/add_booking_feedback.sql.';
    } else {
        $loadError = 'Could not load feedback: ' . htmlspecialchars($e->getMessage());
    }
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer feedback - Admin</title>
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
                    colors: { fes: { red: '#D32F2F', dark: '#424242' } },
                    boxShadow: { card: '0 4px 15px rgba(0,0,0,0.05)' }
                }
            }
        };
    </script>
    <style>
        * { font-family: 'Barlow', sans-serif; }
        h1, h2, h3, h4, .display { font-family: 'Barlow Condensed', sans-serif; }
        @media (max-width: 767px) { #main-content { margin-left: 0 !important; width: 100% !important; } }
        @media (min-width: 768px) { #main-content { margin-left: 300px !important; width: calc(100% - 300px) !important; } }
    </style>
</head>
<body>
<div class="min-h-screen w-full bg-gray-100">
    <?php include __DIR__ . '/include/sidebar.php'; ?>
    <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>
    <div class="min-h-screen" style="margin-left: 300px; width: calc(100% - 300px);" id="main-content">
        <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm md:pl-6">
            <div class="flex items-center gap-3">
                <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Admin</div>
                    <h1 class="text-xl font-semibold text-gray-900">Customer feedback</h1>
                    <p class="text-xs text-gray-500 mt-1">Ratings after completed bookings</p>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
            <?php if ($loadError !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php echo $loadError; ?>
                </div>
            <?php endif; ?>

            <?php if ($countAll > 0 && $avgAll !== null): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-card p-5 border border-gray-100">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Total submissions</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900"><?php echo (int)$countAll; ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-card p-5 border border-gray-100">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">Average rating</div>
                        <div class="mt-1 text-2xl font-semibold text-amber-600"><?php echo htmlspecialchars((string)$avgAll); ?> / 5</div>
                    </div>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-xl shadow-card p-5 mb-6">
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Booking ID</label>
                        <input type="number" name="booking_id" min="1" placeholder="e.g. 12" value="<?php echo $filterBooking > 0 ? (int)$filterBooking : ''; ?>" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow text-sm">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="feedback.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg text-sm">Reset</a>
                </form>
            </section>

            <section class="bg-white rounded-xl shadow-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900">All feedback</h2>
                    <span class="text-xs text-gray-500"><?php echo count($rows); ?> record(s)</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                <th class="py-3 pr-4">Date</th>
                                <th class="py-3 pr-4">Booking</th>
                                <th class="py-3 pr-4">Customer</th>
                                <th class="py-3 pr-4">Operator</th>
                                <th class="py-3 pr-4">Rating</th>
                                <th class="py-3 pr-4">Comment</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-800">
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-gray-500">No feedback yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr class="border-b border-gray-100 align-top">
                                        <td class="py-3 pr-4 whitespace-nowrap text-gray-600">
                                            <?php echo htmlspecialchars(fes_format_date_safe($r['created_at'] ?? null, 'M j, Y H:i', '—')); ?>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <a href="booking-details.php?id=<?php echo (int)$r['booking_id']; ?>" class="font-medium text-fes-red hover:underline">#<?php echo (int)$r['booking_id']; ?></a>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars((string)($r['customer_name'] ?? '—')); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars((string)($r['customer_email'] ?? '')); ?></div>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <?php if (!empty($r['operator_id'])): ?>
                                                <a href="operator_manage.php?id=<?php echo (int)$r['operator_id']; ?>" class="text-fes-red hover:underline"><?php echo htmlspecialchars((string)($r['operator_name'] ?? ('Operator #' . (int)$r['operator_id']))); ?></a>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex items-center gap-1 text-amber-500">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="<?php echo $s <= (int)$r['rating'] ? 'fas fa-star' : 'far fa-star'; ?> text-xs"></i>
                                                <?php endfor; ?>
                                                <span class="ml-1 text-gray-800 font-medium"><?php echo (int)$r['rating']; ?>/5</span>
                                            </span>
                                        </td>
                                        <td class="py-3 pr-4 max-w-md">
                                            <?php if (trim((string)($r['comment'] ?? '')) !== ''): ?>
                                                <div class="text-gray-700 whitespace-pre-wrap break-words"><?php echo htmlspecialchars((string)$r['comment']); ?></div>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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
        if (sidebar.classList.contains('translate-x-0')) closeSidebar();
        else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
