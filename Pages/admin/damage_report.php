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

$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['damage_report_id'], $_POST['status'])) {
    $postId = (int)$_POST['damage_report_id'];
    $newStatus = trim((string)$_POST['status']);
    $adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
    $allowed = ['submitted', 'acknowledged', 'closed'];
    if ($postId <= 0 || !in_array($newStatus, $allowed, true)) {
        $error = 'Invalid update.';
    } elseif (mb_strlen($adminNotes) > 8000) {
        $error = 'Admin notes are too long (max 8000 characters).';
    } else {
        try {
            $conn = getDBConnection();
            $sql = 'UPDATE damage_reports SET status = ?, admin_notes = ?, updated_at = NOW() WHERE damage_report_id = ?';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('ssi', $newStatus, $adminNotes, $postId);
                if ($stmt->execute()) {
                    $message = 'Report updated.';
                    $reportId = $postId;
                } else {
                    $error = 'Could not update the report.';
                }
                $stmt->close();
            }
            $conn->close();
        } catch (Exception $e) {
            error_log('Admin damage_report update: ' . $e->getMessage());
            $error = 'Database error while updating.';
        }
    }
}

if ($reportId > 0) {
    try {
        $conn = getDBConnection();
        $rid = (int)$reportId;
        $sql = "SELECT dr.*,
                       COALESCE(u.name, CONCAT('Operator #', dr.operator_id)) AS operator_name,
                       u.email AS operator_email,
                       e.equipment_name
                FROM damage_reports dr
                LEFT JOIN users u ON u.user_id = dr.operator_id
                LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = dr.equipment_id COLLATE utf8mb4_unicode_ci
                WHERE dr.damage_report_id = {$rid}";
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            $report = $row;
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Admin damage_report fetch: ' . $e->getMessage());
        $error = $error === '' ? 'Could not load the report.' : $error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damage report #<?php echo $reportId > 0 ? (int)$reportId : ''; ?> - Admin</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Damage report</h1>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 justify-end">
                <?php if ($report): ?>
                    <a href="damage_reports.php?booking_id=<?php echo (int)$report['booking_id']; ?>" class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 font-medium px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">
                        <i class="fas fa-filter"></i> Reports for #BK-<?php echo (int)$report['booking_id']; ?>
                    </a>
                <?php endif; ?>
                <a href="damage_reports.php" class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 font-medium px-4 py-2 rounded-lg hover:bg-gray-50 text-sm">
                    <i class="fas fa-arrow-left"></i> All reports
                </a>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
            <?php if ($message !== ''): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$report): ?>
                <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">Report not found.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 space-y-6">
                        <section class="bg-white rounded-xl shadow-card p-6">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Report #<?php echo (int)$report['damage_report_id']; ?></h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-6">
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Booking</div>
                                    <a href="booking-details.php?id=<?php echo (int)$report['booking_id']; ?>" class="text-fes-red font-semibold hover:underline">#BK-<?php echo (int)$report['booking_id']; ?></a>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($report['equipment_name'] ?? $report['equipment_id']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars((string)$report['equipment_id']); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Operator</div>
                                    <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($report['operator_name'] ?? ''); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($report['operator_email'] ?? ''); ?></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Submitted</div>
                                    <div class="text-gray-900 font-medium"><?php echo !empty($report['created_at']) ? htmlspecialchars(date('M j, Y — H:i', strtotime($report['created_at']))) : '—'; ?></div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Severity</div>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-800"><?php echo htmlspecialchars(ucfirst((string)$report['severity'])); ?></span>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Description</div>
                                <div class="text-sm text-gray-800 whitespace-pre-wrap border border-gray-100 rounded-lg bg-gray-50 p-4"><?php echo htmlspecialchars((string)$report['description']); ?></div>
                            </div>
                            <?php if (!empty($report['photo_path'])): ?>
                                <div class="mt-6">
                                    <div class="text-xs text-gray-500 uppercase tracking-wider mb-2">Photo</div>
                                    <a href="../../<?php echo htmlspecialchars($report['photo_path']); ?>" target="_blank" rel="noopener">
                                        <img src="../../<?php echo htmlspecialchars($report['photo_path']); ?>" alt="Damage photo" class="max-w-full max-h-96 rounded-lg border border-gray-200 object-contain bg-gray-50">
                                    </a>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                    <div>
                        <section class="bg-white rounded-xl shadow-card p-6">
                            <h2 class="text-base font-semibold text-gray-900 mb-4">Admin follow-up</h2>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="damage_report_id" value="<?php echo (int)$report['damage_report_id']; ?>">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                        <option value="submitted" <?php echo ($report['status'] ?? '') === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                        <option value="acknowledged" <?php echo ($report['status'] ?? '') === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                                        <option value="closed" <?php echo ($report['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Admin notes</label>
                                    <textarea name="admin_notes" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="Internal notes, actions taken, contact with customer…"><?php echo htmlspecialchars((string)($report['admin_notes'] ?? '')); ?></textarea>
                                </div>
                                <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow text-sm">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </form>
                        </section>
                    </div>
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
        sidebar.classList.add('show');
        overlay.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function () {
        var isOpen = sidebar.classList.contains('show');
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
