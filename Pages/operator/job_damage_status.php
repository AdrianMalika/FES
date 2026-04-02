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
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$booking = null;
$reports = [];

function fes_operator_dr_report_status_badge_class(string $status): string
{
    switch ($status) {
        case 'submitted':
            return 'bg-amber-50 text-amber-800';
        case 'acknowledged':
            return 'bg-blue-50 text-blue-800';
        case 'closed':
            return 'bg-gray-100 text-gray-700';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}

try {
    $conn = getDBConnection();

    if ($bookingId > 0) {
        $bid = (int)$bookingId;
        $oid = (int)$operatorId;
        $sql = "SELECT b.booking_id, b.equipment_id, b.status,
                       e.equipment_name
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
                WHERE b.booking_id = {$bid} AND b.operator_id = {$oid}";
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            $booking = $row;
        }

        if ($booking) {
            $dsql = 'SELECT damage_report_id, description, severity, status, admin_notes, created_at, updated_at, photo_path
                     FROM damage_reports
                     WHERE booking_id = ? AND operator_id = ?
                     ORDER BY created_at DESC';
            if ($dst = $conn->prepare($dsql)) {
                $dst->bind_param('ii', $bid, $oid);
                $dst->execute();
                $drRes = $dst->get_result();
                if ($drRes) {
                    while ($drRow = $drRes->fetch_assoc()) {
                        $reports[] = $drRow;
                    }
                }
                $dst->close();
            }
        }
    }

    $conn->close();
} catch (Throwable $e) {
    error_log('Operator job_damage_status load: ' . $e->getMessage());
}

$bookingLabel = $booking ? ('BK-' . (int)$booking['booking_id']) : '';
$equipmentDisplay = '';
if ($booking) {
    $name = trim((string)($booking['equipment_name'] ?? ''));
    $code = trim((string)($booking['equipment_id'] ?? ''));
    if ($name !== '' && $code !== '') {
        $equipmentDisplay = $name . ' (' . $code . ')';
    } elseif ($name !== '') {
        $equipmentDisplay = $name;
    } elseif ($code !== '') {
        $equipmentDisplay = $code;
    } else {
        $equipmentDisplay = 'N/A';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $booking ? htmlspecialchars('Damage report history — ' . $bookingLabel) : 'Damage report history'; ?> - FES Operator</title>
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
                <button type="button" id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <div class="text-sm text-gray-500">Operator</div>
                    <h1 class="text-xl font-semibold text-gray-900">Damage report history</h1>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php if ($booking): ?>
                            <?php echo htmlspecialchars($bookingLabel); ?> · <?php echo htmlspecialchars($equipmentDisplay); ?>
                        <?php else: ?>
                            Job not found or not assigned to you.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="<?php echo $booking ? 'job_details.php?id=' . urlencode((string)$bookingId) : 'jobs.php'; ?>" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> <?php echo $booking ? 'Back to job' : 'Back to jobs'; ?>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if (!$booking): ?>
                <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                    This booking was not found or is not assigned to you.
                    <div class="mt-4">
                        <a href="jobs.php" class="text-fes-red font-medium text-sm hover:underline">Go to My Jobs</a>
                    </div>
                </div>
            <?php elseif (empty($reports)): ?>
                <div class="bg-white rounded-xl shadow-card p-6 border border-gray-100">
                    <p class="text-sm text-gray-600 mb-4">No damage reports have been submitted for this job yet.</p>
                    <a href="job_damage.php?id=<?php echo (int)$bookingId; ?>" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white text-sm font-medium px-4 py-2 rounded-lg shadow">
                        <i class="fas fa-exclamation-triangle"></i> Report equipment damage
                    </a>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-600 mb-4">Read-only. Administrators update status and office messages in the admin panel.</p>
                <ul class="space-y-4">
                    <?php foreach ($reports as $dr):
                        $drSt = (string)($dr['status'] ?? '');
                        $drBadge = fes_operator_dr_report_status_badge_class($drSt);
                        $submitted = fes_format_date_safe($dr['created_at'] ?? null, 'M j, Y — H:i', '—');
                        $updated = fes_format_date_safe($dr['updated_at'] ?? null, 'M j, Y — H:i', '');
                        $notes = trim((string)($dr['admin_notes'] ?? ''));
                        ?>
                        <li class="bg-white rounded-xl shadow-card border border-slate-200 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900">Report #<?php echo (int)$dr['damage_report_id']; ?></span>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $drBadge; ?>">
                                    <?php echo htmlspecialchars(ucfirst($drSt)); ?>
                                </span>
                                <span class="text-xs text-gray-500">
                                    Submitted <?php echo htmlspecialchars($submitted); ?>
                                </span>
                                <?php if ($updated !== '' && $updated !== $submitted): ?>
                                    <span class="text-xs text-gray-400">· Updated <?php echo htmlspecialchars($updated); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="px-4 py-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Your description</div>
                                <p class="text-sm text-gray-700 whitespace-pre-wrap mb-3"><?php echo htmlspecialchars((string)($dr['description'] ?? '')); ?></p>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Severity</div>
                                <div class="text-sm text-gray-800 mb-3"><?php echo htmlspecialchars(ucfirst((string)($dr['severity'] ?? ''))); ?></div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Message from office</div>
                                <div class="text-sm text-gray-800 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2 whitespace-pre-wrap">
                                    <?php echo $notes !== '' ? htmlspecialchars($notes) : '<span class="text-gray-400 italic">No message yet.</span>'; ?>
                                </div>
                                <?php if (!empty($dr['photo_path'])): ?>
                                    <div class="mt-3">
                                        <a href="../../<?php echo htmlspecialchars((string)$dr['photo_path']); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-fes-red hover:underline">
                                            <i class="fas fa-image"></i> View photo
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-6">
                    <a href="job_damage.php?id=<?php echo (int)$bookingId; ?>" class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg px-4 py-2.5 hover:bg-white bg-gray-50">
                        <i class="fas fa-plus"></i> Add another report
                    </a>
                </div>
            <?php endif; ?>
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
