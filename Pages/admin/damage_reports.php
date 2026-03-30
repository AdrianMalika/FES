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

/**
 * Create damage_reports from database/add_damage_reports.sql if the file is readable.
 * Used when the table is missing (MySQL errno 1146) so admins are not blocked after deploy.
 */
function fes_admin_try_create_damage_reports_table(mysqli $conn): bool
{
    $path = realpath(__DIR__ . '/../../database/add_damage_reports.sql');
    if ($path === false || !is_readable($path)) {
        return false;
    }
    $raw = (string)file_get_contents($path);
    $sql = trim(preg_replace('/^\s*--[^\n]*\r?\n/m', '', $raw));
    if ($sql === '') {
        return false;
    }
    try {
        return (bool)$conn->query($sql);
    } catch (mysqli_sql_exception $e) {
        error_log('fes_admin_try_create_damage_reports_table: ' . $e->getMessage());
        return false;
    }
}

$filterStatus = trim((string)($_GET['status'] ?? 'all'));
$allowedFilter = ['all', 'submitted', 'acknowledged', 'closed'];
if (!in_array($filterStatus, $allowedFilter, true)) {
    $filterStatus = 'all';
}
$filterBooking = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$reports = [];
$loadError = '';
$conn = null;

try {
    $conn = getDBConnection();

    // Safe WHERE: status is whitelist-validated; booking_id is cast to int (avoids prepared + get_result(),
    // which requires mysqlnd — often missing on Windows/WAMP builds).
    $whereParts = ['1=1'];
    if ($filterStatus !== 'all') {
        $whereParts[] = "dr.status = '" . $conn->real_escape_string($filterStatus) . "'";
    }
    if ($filterBooking > 0) {
        $whereParts[] = 'dr.booking_id = ' . (int)$filterBooking;
    }

    $whereSql = implode(' AND ', $whereParts);
    // LEFT JOIN users so rows still show if operator_id ever mismatches users.user_id
    $sql = "SELECT dr.damage_report_id, dr.booking_id, dr.operator_id, dr.equipment_id,
                   dr.description, dr.severity, dr.photo_path, dr.status, dr.created_at,
                   COALESCE(u.name, CONCAT('Operator #', dr.operator_id)) AS operator_name,
                   e.equipment_name
            FROM damage_reports dr
            LEFT JOIN users u ON u.user_id = dr.operator_id
            LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = dr.equipment_id COLLATE utf8mb4_unicode_ci
            WHERE {$whereSql}
            ORDER BY dr.created_at DESC";

    $res = null;
    try {
        $res = $conn->query($sql);
    } catch (mysqli_sql_exception $qe) {
        $qerrno = (int)$qe->getCode();
        $qmsg = $qe->getMessage();
        $isMissingDr = ($qerrno === 1146 || stripos($qmsg, 'damage_reports') !== false);
        if ($isMissingDr && fes_admin_try_create_damage_reports_table($conn)) {
            $res = $conn->query($sql);
        } else {
            throw $qe;
        }
    }

    if ($res === false) {
        $err = $conn->error ?? '';
        $errno = (int)$conn->errno;
        error_log('Admin damage_reports query failed: [' . $errno . '] ' . $err);
        if ($errno === 1146 || stripos($err, 'damage_reports') !== false) {
            if (fes_admin_try_create_damage_reports_table($conn)) {
                try {
                    $res = $conn->query($sql);
                } catch (mysqli_sql_exception $e2) {
                    $loadError = 'Could not load damage reports: ' . htmlspecialchars($e2->getMessage()) . ' (MySQL errno ' . (int)$e2->getCode() . ').';
                }
            }
        }
        if ($res === false && $loadError === '') {
            if ($errno === 1146 || stripos($err, 'damage_reports') !== false) {
                $loadError = 'The damage_reports table is missing and could not be created automatically. In phpMyAdmin, select database "' . htmlspecialchars(DB_NAME) . '" and run the SQL in database/add_damage_reports.sql.';
            } else {
                $loadError = 'Could not load damage reports. Database said: ' . htmlspecialchars($err);
            }
        }
    }

    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $reports[] = $row;
        }
        $res->free();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Admin damage_reports mysqli: [' . $e->getCode() . '] ' . $e->getMessage());
    $errno = (int)$e->getCode();
    $msg = $e->getMessage();
    if ($errno === 1146 || stripos($msg, 'damage_reports') !== false) {
        $loadError = 'The damage_reports table is missing. In phpMyAdmin, select database "' . htmlspecialchars(DB_NAME) . '" and run database/add_damage_reports.sql (or import database/fes_db.sql).';
    } else {
        $loadError = 'Could not load damage reports: ' . htmlspecialchars($msg) . ' (MySQL errno ' . $errno . ').';
    }
} catch (Exception $e) {
    error_log('Admin damage_reports list: ' . $e->getMessage());
    $loadError = 'Could not connect or load damage reports: ' . htmlspecialchars($e->getMessage());
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

function fes_dr_status_badge(string $s): string
{
    switch ($s) {
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

function fes_dr_severity_badge(string $s): string
{
    switch ($s) {
        case 'minor':
            return 'bg-slate-100 text-slate-800';
        case 'major':
            return 'bg-orange-50 text-orange-800';
        case 'critical':
            return 'bg-red-50 text-red-800';
        default:
            return 'bg-gray-100 text-gray-700';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damage reports - Admin</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Damage reports</h1>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6" style="width: 100%; overflow-x: hidden;">
            <?php if ($loadError !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php echo htmlspecialchars($loadError); ?>
                </div>
            <?php endif; ?>

            <section class="bg-white rounded-xl shadow-card p-5 mb-6">
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="submitted" <?php echo $filterStatus === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="acknowledged" <?php echo $filterStatus === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                            <option value="closed" <?php echo $filterStatus === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Booking ID</label>
                        <input type="number" name="booking_id" min="1" placeholder="e.g. 9" value="<?php echo $filterBooking > 0 ? (int)$filterBooking : ''; ?>" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow text-sm">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="damage_reports.php" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2.5 rounded-lg text-sm">Reset</a>
                </form>
            </section>

            <section class="bg-white rounded-xl shadow-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-900">All reports</h2>
                    <span class="text-xs text-gray-500"><?php echo count($reports); ?> record(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                        <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                            <th class="py-3 pr-4">ID</th>
                            <th class="py-3 pr-4">Booking</th>
                            <th class="py-3 pr-4">Equipment</th>
                            <th class="py-3 pr-4">Operator</th>
                            <th class="py-3 pr-4">Severity</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3 pr-4">Submitted</th>
                            <th class="py-3">Action</th>
                        </tr>
                        </thead>
                        <tbody class="text-sm text-gray-900">
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-gray-500">No damage reports found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $r): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 pr-4 font-medium">#<?php echo (int)$r['damage_report_id']; ?></td>
                                    <td class="py-3 pr-4">
                                        <a href="booking-details.php?id=<?php echo (int)$r['booking_id']; ?>" class="text-fes-red font-medium hover:underline">#BK-<?php echo (int)$r['booking_id']; ?></a>
                                    </td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars($r['equipment_name'] ?? $r['equipment_id']); ?></td>
                                    <td class="py-3 pr-4"><?php echo htmlspecialchars($r['operator_name'] ?? ''); ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?php echo fes_dr_severity_badge((string)$r['severity']); ?>">
                                            <?php echo htmlspecialchars(ucfirst((string)$r['severity'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium <?php echo fes_dr_status_badge((string)$r['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst((string)$r['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-600"><?php echo !empty($r['created_at']) ? htmlspecialchars(date('M j, Y H:i', strtotime($r['created_at']))) : ''; ?></td>
                                    <td class="py-3">
                                        <a href="damage_report.php?id=<?php echo (int)$r['damage_report_id']; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-fes-red hover:bg-[#b71c1c] text-white text-sm font-medium">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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
