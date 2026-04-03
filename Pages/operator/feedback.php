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
require_once __DIR__ . '/../../includes/fes_date.php';

$operatorId = (int)($_SESSION['user_id'] ?? 0);

$rows = [];
$loadError = '';
$avgRating = null;
$reviewCount = 0;

try {
    $conn = getDBConnection();

    $sumSql = 'SELECT AVG(rating) AS av, COUNT(*) AS c FROM booking_feedback WHERE operator_id = ?';
    if ($stmt = $conn->prepare($sumSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $reviewCount = (int)($row['c'] ?? 0);
            if ($reviewCount > 0 && $row['av'] !== null) {
                $avgRating = round((float)$row['av'], 2);
            }
        }
        $stmt->close();
    }

    $listSql = "SELECT bf.feedback_id, bf.booking_id, bf.rating, bf.comment, bf.created_at,
                       c.name AS customer_name,
                       b.service_type, b.booking_date
                FROM booking_feedback bf
                LEFT JOIN users c ON c.user_id = bf.customer_id
                LEFT JOIN bookings b ON b.booking_id = bf.booking_id
                WHERE bf.operator_id = ?
                ORDER BY bf.created_at DESC";
    if ($stmt = $conn->prepare($listSql)) {
        $stmt->bind_param('i', $operatorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }

    $conn->close();
} catch (Throwable $e) {
    error_log('Operator feedback page: ' . $e->getMessage());
    if (str_contains($e->getMessage(), 'booking_feedback') || ($e instanceof mysqli_sql_exception && $e->getCode() == 1146)) {
        $loadError = 'Feedback is not available yet. Ask an admin to run database/add_booking_feedback.sql.';
    } else {
        $loadError = 'Could not load feedback.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer feedback - FES Operator</title>
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
                <button type="button" id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator</div>
                    <h1 class="text-xl font-semibold text-gray-900">Customer feedback</h1>
                    <p class="text-xs text-gray-500 mt-1">Ratings from completed jobs assigned to you</p>
                </div>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if ($loadError !== ''): ?>
                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <?php echo htmlspecialchars($loadError); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
                <div class="bg-white rounded-xl shadow-card p-6 border border-gray-100">
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Average rating</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <?php if ($avgRating !== null): ?>
                            <span class="text-4xl font-bold text-amber-600"><?php echo htmlspecialchars((string)$avgRating); ?></span>
                            <span class="text-lg text-gray-500">/ 5</span>
                        <?php else: ?>
                            <span class="text-2xl font-semibold text-gray-400">—</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Across all reviews where you were the assigned operator.</p>
                </div>
                <div class="bg-white rounded-xl shadow-card p-6 border border-gray-100">
                    <div class="text-xs text-gray-500 uppercase tracking-wider">Total reviews</div>
                    <div class="mt-2 text-4xl font-bold text-gray-900"><?php echo (int)$reviewCount; ?></div>
                    <p class="mt-2 text-sm text-gray-600">Submitted after customers mark bookings completed.</p>
                </div>
            </div>

            <section class="bg-white rounded-xl shadow-card p-5 border border-gray-100">
                <h2 class="text-base font-semibold text-gray-900 mb-4">All reviews</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                <th class="py-3 pr-4">Date</th>
                                <th class="py-3 pr-4">Booking</th>
                                <th class="py-3 pr-4">Customer</th>
                                <th class="py-3 pr-4">Rating</th>
                                <th class="py-3 pr-4">Comment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-gray-500">
                                        No customer feedback yet. Reviews appear after jobs are completed and customers submit a rating.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $r): ?>
                                    <tr class="border-b border-gray-100 align-top">
                                        <td class="py-3 pr-4 whitespace-nowrap text-gray-600">
                                            <?php echo htmlspecialchars(fes_format_date_safe($r['created_at'] ?? null, 'M j, Y H:i', '—')); ?>
                                        </td>
                                        <td class="py-3 pr-4">
                                            <a href="job_details.php?id=<?php echo (int)$r['booking_id']; ?>" class="font-medium text-fes-red hover:underline">#<?php echo (int)$r['booking_id']; ?></a>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($r['service_type'] ?? '')))); ?> · <?php echo htmlspecialchars(fes_format_date_safe($r['booking_date'] ?? null, 'M j, Y', '')); ?></div>
                                        </td>
                                        <td class="py-3 pr-4"><?php echo htmlspecialchars((string)($r['customer_name'] ?? '—')); ?></td>
                                        <td class="py-3 pr-4">
                                            <span class="inline-flex items-center gap-0.5 text-amber-500">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="<?php echo $s <= (int)$r['rating'] ? 'fas' : 'far'; ?> fa-star text-xs"></i>
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
