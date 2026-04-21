<?php
session_start();
if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/signin.php');
    exit();
}
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/fes_date.php';

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function fmtMwK(float $v): string { return 'MWK ' . number_format($v, 2, '.', ','); }

$from  = (string)($_GET['from'] ?? '');
$to    = (string)($_GET['to']   ?? '');
$depot = (string)($_GET['depot'] ?? '');
$dpat = '/^\d{4}-\d{2}-\d{2}$/';
$from = preg_match($dpat, $from) ? $from : '';
$to   = preg_match($dpat, $to)   ? $to   : '';

$wheres = []; $wheresB = []; $wheresBP = []; $wheresBF = []; $wheresEM = [];
if ($from !== '') { $wheres[] = "b.created_at >= '{$from} 00:00:00'"; $wheresB[] = "created_at >= '{$from} 00:00:00'"; $wheresBP[] = "bp.created_at >= '{$from} 00:00:00'"; $wheresBF[] = "bf.created_at >= '{$from} 00:00:00'"; $wheresEM[] = "scheduled_date >= '{$from}'"; }
if ($to   !== '') { $wheres[] = "b.created_at <= '{$to} 23:59:59'";   $wheresB[] = "created_at <= '{$to} 23:59:59'";   $wheresBP[] = "bp.created_at <= '{$to} 23:59:59'";   $wheresBF[] = "bf.created_at <= '{$to} 23:59:59'";   $wheresEM[] = "scheduled_date <= '{$to}'"; }
if ($depot !== '') { $d = e($depot); $wheres[] = "b.equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')"; $wheresB[] = "equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')"; $wheresBP[] = "bp.booking_id IN (SELECT booking_id FROM bookings WHERE equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}'))"; $wheresBF[] = "bf.booking_id IN (SELECT booking_id FROM bookings WHERE equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}'))"; $wheresEM[] = "equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')"; }

$whereBookings = $wheres !== [] ? implode(' AND ', $wheres) : '1=1';
$whereB = $wheresB !== [] ? implode(' AND ', $wheresB) : '1=1';
$whereBP = $wheresBP !== [] ? implode(' AND ', $wheresBP) : '1=1';
$whereBF = $wheresBF !== [] ? implode(' AND ', $wheresBF) : '1=1';
$whereEM = $wheresEM !== [] ? implode(' AND ', $wheresEM) : '1=1';
$eqWhere = $depot !== '' ? "location = '{$depot}'" : '1=1';
$year = (int)date('Y');

$conn = getDBConnection();

// Overview KPIs
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings}")->fetch_assoc(); $ov_total = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='paid'")->fetch_assoc(); $ov_rev = (float)($row['s'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere}")->fetch_assoc(); $ov_eq = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(DISTINCT operator_id) AS c FROM bookings b WHERE {$whereBookings} AND b.status='in_progress'")->fetch_assoc(); $ov_ops = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc(); $ov_rate = $row['a'] !== null ? round((float)$row['a'], 2) : 0;
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings} AND b.status='pending'")->fetch_assoc(); $ov_pend = (int)($row['c'] ?? 0);

$mb = array_fill(1, 12, 0); $res = $conn->query("SELECT MONTH(created_at) AS m, COUNT(*) AS c FROM bookings b WHERE {$whereB} AND YEAR(created_at)={$year} GROUP BY m"); while ($r = $res->fetch_assoc()) { $mb[(int)$r['m']] = (int)$r['c']; }
$mr = array_fill(1, 12, 0); $res = $conn->query("SELECT MONTH(created_at) AS m, COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereB} AND payment_status='paid' AND YEAR(created_at)={$year} GROUP BY m"); while ($r = $res->fetch_assoc()) { $mr[(int)$r['m']] = (float)$r['s']; }
$stb = ['pending' => 0, 'confirmed' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0]; $res = $conn->query("SELECT status, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY status"); while ($r = $res->fetch_assoc()) { $stb[$r['status']] = (int)$r['c']; }
$svc = []; $res = $conn->query("SELECT service_type, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY service_type ORDER BY c DESC"); while ($r = $res->fetch_assoc()) { $svc[] = ['type' => $r['service_type'], 'count' => (int)$r['c']]; }

// Bookings
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='confirmed'")->fetch_assoc(); $bk_conf = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='completed'")->fetch_assoc(); $bk_comp = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='cancelled'")->fetch_assoc(); $bk_can = (int)($row['c'] ?? 0);
$dbl = $conn->query("SELECT equipment_id, booking_date, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY equipment_id, booking_date HAVING c > 1"); $bk_dbl = (int)$dbl->num_rows;
$bkRows = []; $res = $conn->query("SELECT b.booking_id, u.name AS customer_name, b.service_type, e.equipment_name, o.name AS operator_name, b.booking_date, b.status, b.estimated_total_cost FROM bookings b LEFT JOIN users u ON u.user_id=b.customer_id LEFT JOIN equipment e ON e.equipment_id=b.equipment_id LEFT JOIN users o ON o.user_id=b.operator_id WHERE {$whereBookings} ORDER BY b.created_at DESC LIMIT 500"); while ($r = $res->fetch_assoc()) { $bkRows[] = $r; }

// Equipment
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere}")->fetch_assoc(); $eq_total = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere} AND status='in_use'")->fetch_assoc(); $eq_use = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere} AND status='maintenance'")->fetch_assoc(); $eq_maint = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COALESCE(SUM(cost),0) AS s FROM equipment_maintenance WHERE {$whereEM}")->fetch_assoc(); $eq_cost = (float)($row['s'] ?? 0);
$eqUtil = []; $res = $conn->query("SELECT category, COUNT(*) AS total, SUM(CASE WHEN status='in_use' THEN 1 ELSE 0 END) AS in_use FROM equipment WHERE {$eqWhere} GROUP BY category"); while ($r = $res->fetch_assoc()) { $eqUtil[] = $r; }
$maintChart = []; $res = $conn->query("SELECT maintenance_type, SUM(CASE WHEN status='scheduled' THEN 1 ELSE 0 END) AS scheduled, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed FROM equipment_maintenance WHERE {$whereEM} GROUP BY maintenance_type"); while ($r = $res->fetch_assoc()) { $maintChart[] = $r; }
$eqRows = []; $res = $conn->query("SELECT equipment_name, category, total_usage_hours, last_maintenance, status FROM equipment WHERE {$eqWhere} ORDER BY equipment_name"); while ($r = $res->fetch_assoc()) { $r['next_service_due'] = !empty($r['last_maintenance']) ? date('Y-m-d', strtotime($r['last_maintenance'].' +90 days')) : null; $eqRows[] = $r; }

// Operators
$row = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='operator'")->fetch_assoc(); $op_total = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='operator' AND user_id NOT IN (SELECT DISTINCT operator_id FROM bookings WHERE status='in_progress' AND operator_id IS NOT NULL)")->fetch_assoc(); $op_avail = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='completed'")->fetch_assoc(); $op_avg_jobs = $op_total > 0 ? round((int)($row['c'] ?? 0) / $op_total, 1) : 0;
$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc(); $op_avg_rate = $row['a'] !== null ? round((float)$row['a'], 2) : 0;
$opJobs = []; $res = $conn->query("SELECT u.user_id, u.name, COUNT(b.booking_id) AS jobs FROM users u LEFT JOIN bookings b ON b.operator_id=u.user_id AND {$whereBookings} WHERE u.role='operator' GROUP BY u.user_id ORDER BY jobs DESC"); while ($r = $res->fetch_assoc()) { $opJobs[] = $r; }
$opRatings = []; $res = $conn->query("SELECT u.user_id, u.name, ROUND(AVG(bf.rating),2) AS avg_rating FROM users u LEFT JOIN booking_feedback bf ON bf.operator_id=u.user_id AND {$whereBF} WHERE u.role='operator' GROUP BY u.user_id"); while ($r = $res->fetch_assoc()) { $opRatings[] = $r; }
$opRows = []; $res = $conn->query("SELECT u.name, COUNT(b.booking_id) AS jobs_completed, ROUND(AVG(bf.rating),2) AS avg_rating, ROUND(SUM(CASE WHEN b.status='completed' THEN COALESCE(TIMESTAMPDIFF(MINUTE,b.operator_start_time,b.operator_end_time),0) ELSE 0 END)/60,1) AS hours_logged, COUNT(DISTINCT b.equipment_id) AS equipment_used, CASE WHEN u.user_id IN (SELECT DISTINCT operator_id FROM bookings WHERE status='in_progress' AND operator_id IS NOT NULL) THEN 'busy' ELSE 'available' END AS availability FROM users u LEFT JOIN bookings b ON b.operator_id=u.user_id AND b.status='completed' AND {$whereBookings} LEFT JOIN booking_feedback bf ON bf.operator_id=u.user_id WHERE u.role='operator' GROUP BY u.user_id ORDER BY jobs_completed DESC"); while ($r = $res->fetch_assoc()) { $opRows[] = $r; }

// Revenue
$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='paid'")->fetch_assoc(); $rev_total = (float)($row['s'] ?? 0);
$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='unpaid'")->fetch_assoc(); $rev_unpaid = (float)($row['s'] ?? 0);
$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.status='cancelled'")->fetch_assoc(); $rev_cancelled = (float)($row['s'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings} AND b.payment_status='paid'")->fetch_assoc(); $rev_paid_count = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings}")->fetch_assoc(); $rev_total_count = (int)($row['c'] ?? 0);
$rev_avg = $rev_paid_count > 0 ? round($rev_total / $rev_paid_count, 2) : 0;
$rev_rate = $rev_total_count > 0 ? round(($rev_paid_count / $rev_total_count) * 100, 1) : 0;
$revSvc = []; $res = $conn->query("SELECT service_type, COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereB} AND payment_status='paid' GROUP BY service_type"); while ($r = $res->fetch_assoc()) { $revSvc[] = ['type' => $r['service_type'], 'amount' => (float)$r['s']]; }

$txRows = []; $res = $conn->query("SELECT bp.id AS transaction_id, bp.booking_id, u.name AS customer_name, bp.amount, bp.provider, bp.status, bp.created_at FROM booking_payments bp LEFT JOIN bookings b ON b.booking_id=bp.booking_id LEFT JOIN users u ON u.user_id=bp.user_id WHERE {$whereBP} ORDER BY bp.created_at DESC LIMIT 500"); while ($r = $res->fetch_assoc()) { $txRows[] = $r; }

// Feedback
$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc(); $fb_total = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} AND rating=5")->fetch_assoc(); $fb_5 = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} AND rating IN (1,2)")->fetch_assoc(); $fb_low = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc(); $fb_avg = $row['a'] !== null ? round((float)$row['a'], 2) : 0;
$dist = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0]; $res = $conn->query("SELECT rating, COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} GROUP BY rating"); while ($r = $res->fetch_assoc()) { $dist[(string)$r['rating']] = (int)$r['c']; }
$rt = []; $res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF} GROUP BY month ORDER BY month LIMIT 12"); while ($r = $res->fetch_assoc()) { $rt[] = ['month' => $r['month'], 'avg' => $r['a'] !== null ? round((float)$r['a'], 2) : 0]; }
$fbRows = []; $res = $conn->query("SELECT bf.booking_id, b.service_type, bf.rating, bf.comment, bf.created_at, c.name AS customer_name FROM booking_feedback bf LEFT JOIN bookings b ON b.booking_id=bf.booking_id LEFT JOIN users c ON c.user_id=bf.customer_id WHERE {$whereBF} ORDER BY bf.created_at DESC LIMIT 100"); while ($r = $res->fetch_assoc()) { $fbRows[] = $r; }

// Depots for filter
$depots = []; $res = $conn->query("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL AND location <> '' ORDER BY location"); while ($r = $res->fetch_assoc()) { $depots[] = $r['location']; }

$conn->close();

$jsonData = json_encode([
    'overview' => ['monthly_bookings' => array_values($mb), 'monthly_revenue' => array_values($mr), 'status' => $stb, 'services' => $svc],
    'equipment' => ['util' => $eqUtil, 'maint' => $maintChart],
    'operators' => ['jobs' => $opJobs, 'ratings' => $opRatings],
    'revenue' => ['service' => $revSvc],
    'feedback' => ['distribution' => array_values($dist), 'over_time' => $rt],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports &amp; Analytics - FES</title>
<link rel="icon" type="image/png" href="../../assets/images/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
tailwind.config = { theme: { extend: { colors: { fes: { red: '#D32F2F', dark: '#1a1a1a', mid: '#2e2e2e' } } } } };
</script>
<style>
*{font-family:'Barlow',sans-serif;}h1,h2,h3,h4,.display{font-family:'Barlow Condensed',sans-serif;}

.tab-btn.active{border-bottom-color:#D32F2F;color:#D32F2F;font-weight:600;}
@media print{
  #fes-dashboard-sidebar,#fes-dashboard-overlay,.tab-nav,#fes-dashboard-menu-btn,header .no-print{display:none!important;}
  #main-content{margin-left:0!important;width:100%!important;}
  .tab-panel{display:block!important;page-break-before:always;}
  .tab-panel:first-of-type{page-break-before:auto;}
  body{background:#fff;}
}
</style>
</head>
<body class="bg-gray-100 text-gray-900">
<?php include __DIR__ . '/include/sidebar.php'; ?>
<div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>
<div class="min-h-screen w-full md:ml-[300px] md:w-[calc(100%-300px)]" id="main-content">
<header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-gray-200 p-4 md:p-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <button id="fes-dashboard-menu-btn" type="button" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu"><i class="fas fa-bars"></i></button>
      <div><h1 class="text-2xl md:text-3xl font-bold display">Reports &amp; Analytics</h1></div>
    </div>
    <div class="flex flex-wrap items-center gap-2 no-print">
      <button type="button" onclick="downloadPDF()" class="inline-flex items-center gap-2 bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm font-semibold"><i class="fas fa-file-pdf"></i>Download PDF</button>
    </div>
  </div>
  <nav class="tab-nav flex flex-wrap gap-4 mt-4 border-b border-gray-200 no-print">
    <?php $tabs = ['overview'=>'Overview','bookings'=>'Bookings','equipment'=>'Equipment &amp; Maint.','operators'=>'Operators','revenue'=>'Revenue','feedback'=>'Feedback'];
    foreach ($tabs as $k=>$l): ?>
    <button type="button" class="tab-btn px-1 py-2 text-sm text-gray-600 border-b-2 border-transparent whitespace-nowrap transition-colors <?php echo $k==='overview'?'active':''; ?>" data-tab="<?php echo $k; ?>"><?php echo $l; ?></button>
    <?php endforeach; ?>
  </nav>
</header>

<main class="p-4 md:p-6 space-y-6" id="report-root">

<!-- OVERVIEW -->
<section class="tab-panel" id="tab-overview">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Bookings</div><div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($ov_total)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Revenue</div><div class="text-3xl font-bold text-emerald-600"><?php echo e(fmtMwK($ov_rev)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Active Equipment</div><div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($ov_eq)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Operators On Duty</div><div class="text-3xl font-bold text-blue-600"><?php echo e(number_format($ov_ops)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Service Rating</div><div class="text-3xl font-bold text-amber-500"><?php echo e(number_format($ov_rate, 2)); ?> / 5</div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Pending Approvals</div><div class="text-3xl font-bold text-fes-red"><?php echo e(number_format($ov_pend)); ?></div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Monthly Bookings</h3><div class="h-64"><canvas id="chart-monthly-bookings"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Revenue Trend (<?php echo $year; ?>)</h3><div class="h-64"><canvas id="chart-revenue-trend"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Booking Status</h3><div class="h-64 flex justify-center"><canvas id="chart-status"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Services Requested</h3><div class="h-64"><canvas id="chart-services"></canvas></div></div>
  </div>
</section>

<!-- BOOKINGS -->
<section class="tab-panel hidden" id="tab-bookings">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Confirmed</div><div class="text-3xl font-bold text-blue-600"><?php echo e(number_format($bk_conf)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Completed</div><div class="text-3xl font-bold text-emerald-600"><?php echo e(number_format($bk_comp)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Cancelled</div><div class="text-3xl font-bold text-red-600"><?php echo e(number_format($bk_can)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Double-Booking Incidents</div><div class="text-3xl font-bold text-amber-600"><?php echo e(number_format($bk_dbl)); ?></div></div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <h3 class="display text-lg font-semibold mb-3">Bookings</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left text-gray-500"><th class="py-2 pr-3">ID</th><th class="py-2 pr-3">Customer</th><th class="py-2 pr-3">Service</th><th class="py-2 pr-3">Equipment</th><th class="py-2 pr-3">Operator</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Cost</th></tr></thead>
        <tbody>
          <?php foreach ($bkRows as $r):
            $st = (string)$r['status'];
            if ($st === 'completed') $badge = 'bg-emerald-100 text-emerald-700';
            elseif ($st === 'in_progress') $badge = 'bg-blue-100 text-blue-700';
            elseif ($st === 'pending') $badge = 'bg-amber-100 text-amber-700';
            elseif ($st === 'cancelled') $badge = 'bg-red-100 text-red-700';
            else $badge = 'bg-gray-100 text-gray-700';
          ?>
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-3 font-medium">#<?php echo (int)$r['booking_id']; ?></td>
            <td class="py-2 pr-3"><?php echo e((string)($r['customer_name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(ucwords(str_replace('_',' ',(string)($r['service_type'] ?? '')))); ?></td>
            <td class="py-2 pr-3"><?php echo e((string)($r['equipment_name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e((string)($r['operator_name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(fes_format_date_safe($r['booking_date'] ?? null, 'M j, Y', '—')); ?></td>
            <td class="py-2 pr-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize <?php echo $badge; ?>"><?php echo e(str_replace('_',' ',(string)$r['status'])); ?></span></td>
            <td class="py-2 pr-3"><?php echo e(fmtMwK((float)($r['estimated_total_cost'] ?? 0))); ?></td>
          </tr>
          <?php endforeach; if (empty($bkRows)): ?><tr><td colspan="8" class="py-10 text-center text-gray-500">No bookings found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- EQUIPMENT -->
<section class="tab-panel hidden" id="tab-equipment">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Fleet</div><div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($eq_total)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">In Use</div><div class="text-3xl font-bold text-blue-600"><?php echo e(number_format($eq_use)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Under Maintenance</div><div class="text-3xl font-bold text-amber-600"><?php echo e(number_format($eq_maint)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Maintenance Cost</div><div class="text-3xl font-bold text-fes-red"><?php echo e(fmtMwK($eq_cost)); ?></div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Utilisation by Type</h3><div class="h-64"><canvas id="chart-eq-util"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Maintenance Scheduled vs Completed</h3><div class="h-64"><canvas id="chart-maint"></canvas></div></div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <h3 class="display text-lg font-semibold mb-3">Equipment Status</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left text-gray-500"><th class="py-2 pr-3">Name</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Usage Hours</th><th class="py-2 pr-3">Last Maintenance</th><th class="py-2 pr-3">Next Service Due</th><th class="py-2 pr-3">Status</th></tr></thead>
        <tbody>
          <?php foreach ($eqRows as $r):
            $st = (string)$r['status'];
            if ($st === 'available') $badge = 'bg-emerald-100 text-emerald-700';
            elseif ($st === 'in_use') $badge = 'bg-blue-100 text-blue-700';
            elseif ($st === 'maintenance') $badge = 'bg-amber-100 text-amber-700';
            elseif ($st === 'retired') $badge = 'bg-gray-100 text-gray-700';
            else $badge = 'bg-gray-100 text-gray-700';
          ?>
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-3 font-medium"><?php echo e((string)($r['equipment_name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e((string)($r['category'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(number_format((int)($r['total_usage_hours'] ?? 0))); ?></td>
            <td class="py-2 pr-3"><?php echo e(fes_format_date_safe($r['last_maintenance'] ?? null, 'M j, Y', '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(fes_format_date_safe($r['next_service_due'] ?? null, 'M j, Y', '—')); ?></td>
            <td class="py-2 pr-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize <?php echo $badge; ?>"><?php echo e(str_replace('_',' ',(string)$r['status'])); ?></span></td>
          </tr>
          <?php endforeach; if (empty($eqRows)): ?><tr><td colspan="6" class="py-10 text-center text-gray-500">No equipment found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- OPERATORS -->
<section class="tab-panel hidden" id="tab-operators">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Operators</div><div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($op_total)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Available Now</div><div class="text-3xl font-bold text-emerald-600"><?php echo e(number_format($op_avail)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Jobs / Operator</div><div class="text-3xl font-bold text-blue-600"><?php echo e(number_format($op_avg_jobs, 1)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Avg Rating</div><div class="text-3xl font-bold text-amber-500"><?php echo e(number_format($op_avg_rate, 2)); ?> / 5</div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Jobs per Operator</h3><div class="h-64"><canvas id="chart-op-jobs"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Operator Ratings</h3><div class="h-64"><canvas id="chart-op-ratings"></canvas></div></div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <h3 class="display text-lg font-semibold mb-3">Performance</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left text-gray-500"><th class="py-2 pr-3">Operator</th><th class="py-2 pr-3">Jobs Completed</th><th class="py-2 pr-3">Avg Rating</th><th class="py-2 pr-3">Hours Logged</th><th class="py-2 pr-3">Equipment Used</th><th class="py-2 pr-3">Availability</th></tr></thead>
        <tbody>
          <?php foreach ($opRows as $r):
            $availBadge = $r['availability'] === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700';
            $stars = (float)($r['avg_rating'] ?? 0);
          ?>
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-3 font-medium"><?php echo e((string)($r['name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(number_format((int)($r['jobs_completed'] ?? 0))); ?></td>
            <td class="py-2 pr-3">
              <div class="inline-flex items-center gap-1">
                <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-solid fa-star <?php echo $stars >= $i ? 'text-amber-400' : 'text-gray-300'; ?> text-xs"></i><?php endfor; ?>
                <span class="text-xs text-gray-500 ml-1"><?php echo e(number_format($stars, 2)); ?></span>
              </div>
            </td>
            <td class="py-2 pr-3"><?php echo e(number_format((float)($r['hours_logged'] ?? 0), 1)); ?></td>
            <td class="py-2 pr-3"><?php echo e(number_format((int)($r['equipment_used'] ?? 0))); ?></td>
            <td class="py-2 pr-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize <?php echo $availBadge; ?>"><?php echo e((string)$r['availability']); ?></span></td>
          </tr>
          <?php endforeach; if (empty($opRows)): ?><tr><td colspan="6" class="py-10 text-center text-gray-500">No operators found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- REVENUE -->
<section class="tab-panel hidden" id="tab-revenue">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Revenue</div><div class="text-3xl font-bold text-emerald-600"><?php echo e(fmtMwK($rev_total)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Unpaid Bookings</div><div class="text-3xl font-bold text-amber-600"><?php echo e(fmtMwK($rev_unpaid)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Cancelled Bookings</div><div class="text-3xl font-bold text-fes-red"><?php echo e(fmtMwK($rev_cancelled)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Collection Rate</div><div class="text-3xl font-bold text-blue-600"><?php echo e($rev_rate); ?>%</div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="display text-lg font-semibold mb-3">Revenue by Service Type</h3>
      <div class="h-72 flex justify-center"><canvas id="chart-rev-service"></canvas></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <h3 class="display text-lg font-semibold mb-4">Revenue Insights</h3>
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
          <span class="text-sm text-gray-500">Paid Bookings</span>
          <span class="text-lg font-bold text-gray-900"><?php echo e(number_format($rev_paid_count)); ?></span>
        </div>
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
          <span class="text-sm text-gray-500">Avg Booking Value</span>
          <span class="text-lg font-bold text-emerald-600"><?php echo e(fmtMwK($rev_avg)); ?></span>
        </div>
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
          <span class="text-sm text-gray-500">Unpaid Bookings</span>
          <span class="text-lg font-bold text-amber-600"><?php echo e(fmtMwK($rev_unpaid)); ?></span>
        </div>
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
          <span class="text-sm text-gray-500">Cancelled Bookings</span>
          <span class="text-lg font-bold text-fes-red"><?php echo e(fmtMwK($rev_cancelled)); ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">Collection Rate</span>
          <span class="text-lg font-bold text-blue-600"><?php echo e($rev_rate); ?>%</span>
        </div>
      </div>
      <div class="mt-5 pt-4 border-t border-gray-100">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">By Service</h4>
        <div class="space-y-2">
          <?php foreach ($revSvc as $svc): ?>
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-700 capitalize"><?php echo e(str_replace('_',' ',$svc['type'])); ?></span>
            <span class="font-semibold text-gray-900"><?php echo e(fmtMwK($svc['amount'])); ?></span>
          </div>
          <?php endforeach; if (empty($revSvc)): ?><p class="text-sm text-gray-400">No paid services yet.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <h3 class="display text-lg font-semibold mb-3">Transactions</h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="border-b text-left text-gray-500"><th class="py-2 pr-3">TX ID</th><th class="py-2 pr-3">Booking</th><th class="py-2 pr-3">Customer</th><th class="py-2 pr-3">Amount</th><th class="py-2 pr-3">Method</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Status</th></tr></thead>
        <tbody>
          <?php foreach ($txRows as $r):
            $st = (string)$r['status'];
            if ($st === 'paid') $badge = 'bg-emerald-100 text-emerald-700';
            elseif ($st === 'pending') $badge = 'bg-amber-100 text-amber-700';
            elseif ($st === 'failed') $badge = 'bg-red-100 text-red-700';
            elseif ($st === 'cancelled') $badge = 'bg-gray-100 text-gray-700';
            else $badge = 'bg-gray-100 text-gray-700';
          ?>
          <tr class="border-b border-gray-100">
            <td class="py-2 pr-3 font-medium">#<?php echo (int)$r['transaction_id']; ?></td>
            <td class="py-2 pr-3"><a href="booking-details.php?id=<?php echo (int)$r['booking_id']; ?>" class="text-fes-red hover:underline">#<?php echo (int)$r['booking_id']; ?></a></td>
            <td class="py-2 pr-3"><?php echo e((string)($r['customer_name'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(fmtMwK((float)($r['amount'] ?? 0))); ?></td>
            <td class="py-2 pr-3 capitalize"><?php echo e((string)($r['provider'] ?? '—')); ?></td>
            <td class="py-2 pr-3"><?php echo e(fes_format_date_safe($r['created_at'] ?? null, 'M j, Y H:i', '—')); ?></td>
            <td class="py-2 pr-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize <?php echo $badge; ?>"><?php echo e((string)$r['status']); ?></span></td>
          </tr>
          <?php endforeach; if (empty($txRows)): ?><tr><td colspan="7" class="py-10 text-center text-gray-500">No transactions found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- FEEDBACK -->
<section class="tab-panel hidden" id="tab-feedback">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Total Reviews</div><div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($fb_total)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">5-Star Reviews</div><div class="text-3xl font-bold text-emerald-600"><?php echo e(number_format($fb_5)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">1-2 Star Reviews</div><div class="text-3xl font-bold text-red-600"><?php echo e(number_format($fb_low)); ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Overall Average</div><div class="text-3xl font-bold text-amber-500"><?php echo e(number_format($fb_avg, 2)); ?> / 5</div></div>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Rating Distribution</h3><div class="h-64"><canvas id="chart-fb-dist"></canvas></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-5"><h3 class="display text-lg font-semibold mb-3">Ratings Over Time</h3><div class="h-64"><canvas id="chart-fb-time"></canvas></div></div>
  </div>
  <div class="bg-white rounded-xl border border-gray-200 p-5 mt-6">
    <h3 class="display text-lg font-semibold mb-3">Recent Feedback</h3>
    <div class="space-y-3">
      <?php foreach ($fbRows as $r): $stars = (int)($r['rating'] ?? 0); ?>
      <div class="flex items-start gap-3 border-b border-gray-100 pb-3 last:border-0">
        <div class="flex-1">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-medium text-sm"><?php echo e((string)($r['customer_name'] ?? '—')); ?></span>
            <span class="text-gray-400 text-xs">• Booking #<?php echo (int)$r['booking_id']; ?> • <?php echo e(ucwords(str_replace('_',' ',(string)($r['service_type'] ?? '')))); ?></span>
            <span class="text-xs text-gray-500 ml-auto"><?php echo e(fes_format_date_safe($r['created_at'] ?? null, 'M j, Y', '—')); ?></span>
          </div>
          <div class="inline-flex items-center gap-0.5 mt-1">
            <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-solid fa-star <?php echo $stars >= $i ? 'text-amber-400' : 'text-gray-300'; ?> text-xs"></i><?php endfor; ?>
            <span class="text-xs text-gray-600 ml-1 font-medium"><?php echo $stars; ?>/5</span>
          </div>
          <?php if (!empty($r['comment'])): ?><p class="text-sm text-gray-700 mt-1"><?php echo e((string)$r['comment']); ?></p><?php endif; ?>
        </div>
      </div>
      <?php endforeach; if (empty($fbRows)): ?><p class="text-gray-500 text-sm py-6 text-center">No feedback yet.</p><?php endif; ?>
    </div>
  </div>
</section>

</main>
</div>

<script>
const rdata = <?= $jsonData; ?>;
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const statusColors = {pending:'#f59e0b',confirmed:'#3b82f6',in_progress:'#a855f7',completed:'#10b981',cancelled:'#6b7280'};
const statusLabels = {pending:'Pending',confirmed:'Confirmed',in_progress:'In Progress',completed:'Completed',cancelled:'Cancelled'};

Chart.defaults.font.family = "'Barlow', sans-serif";
Chart.defaults.color = '#6b7280';

const doughnutCenter = {
  id: 'doughnutCenter',
  afterDraw(chart) {
    const {ctx, width, height} = chart;
    const cfg = chart.config.options.plugins.doughnutCenter;
    if (!cfg) return;
    ctx.save();
    ctx.font = 'bold 16px Barlow, sans-serif';
    ctx.fillStyle = '#1f2937';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if (cfg.text) ctx.fillText(cfg.text, width/2, height/2 - 10);
    ctx.font = '12px Barlow, sans-serif';
    ctx.fillStyle = '#6b7280';
    if (cfg.subtext) ctx.fillText(cfg.subtext, width/2, height/2 + 10);
    ctx.restore();
  }
};
Chart.register(doughnutCenter);

function fmtTooltipMwK(context) {
  let label = context.label || '';
  if (label) label += ': ';
  label += 'MWK ' + Number(context.raw).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  return label;
}

function initCharts() {
  const statusTotal = Object.values(rdata.overview.status).reduce((a,b)=>a+b,0);

  // Overview
  new Chart(document.getElementById('chart-monthly-bookings'), { type:'bar', data:{ labels:months, datasets:[{label:'Bookings',data:rdata.overview.monthly_bookings,backgroundColor:'#D32F2F',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}});
  new Chart(document.getElementById('chart-revenue-trend'), { type:'line', data:{ labels:months, datasets:[{label:'Revenue (MWK)',data:rdata.overview.monthly_revenue,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,0.1)',fill:true,tension:0.3}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}});
  new Chart(document.getElementById('chart-status'), { type:'doughnut', data:{ labels:Object.keys(rdata.overview.status).map(k=>statusLabels[k]||k), datasets:[{data:Object.values(rdata.overview.status),backgroundColor:Object.keys(rdata.overview.status).map(k=>statusColors[k]||'#9ca3af'),borderWidth:0}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{doughnutCenter:{text:String(statusTotal),subtext:'Total Bookings'},legend:{position:'right'},tooltip:{callbacks:{label:function(c){let l=c.label||'';if(l)l+=': ';l+=c.raw;return l;}}}}}});
  new Chart(document.getElementById('chart-services'), { type:'bar', data:{ labels:rdata.overview.services.map(s=>s.type.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())), datasets:[{label:'Bookings',data:rdata.overview.services.map(s=>s.count),backgroundColor:'#3b82f6',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}});

  // Equipment
  new Chart(document.getElementById('chart-eq-util'), { type:'bar', data:{ labels:rdata.equipment.util.map(e=>e.category), datasets:[{label:'In Use',data:rdata.equipment.util.map(e=>e.in_use),backgroundColor:'#D32F2F',borderRadius:4},{label:'Total',data:rdata.equipment.util.map(e=>e.total),backgroundColor:'#e5e7eb',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',scales:{x:{stacked:true},y:{stacked:true}}}});
  new Chart(document.getElementById('chart-maint'), { type:'bar', data:{ labels:rdata.equipment.maint.map(m=>m.maintenance_type.charAt(0).toUpperCase()+m.maintenance_type.slice(1)), datasets:[{label:'Scheduled',data:rdata.equipment.maint.map(m=>m.scheduled),backgroundColor:'#f59e0b',borderRadius:4},{label:'Completed',data:rdata.equipment.maint.map(m=>m.completed),backgroundColor:'#10b981',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,scales:{x:{stacked:false},y:{beginAtZero:true}}}});

  // Operators
  new Chart(document.getElementById('chart-op-jobs'), { type:'bar', data:{ labels:rdata.operators.jobs.map(o=>o.name), datasets:[{label:'Jobs',data:rdata.operators.jobs.map(o=>o.jobs),backgroundColor:'#3b82f6',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}}}});
  new Chart(document.getElementById('chart-op-ratings'), { type:'bar', data:{ labels:rdata.operators.ratings.map(o=>o.name), datasets:[{label:'Avg Rating',data:rdata.operators.ratings.map(o=>o.avg_rating||0),backgroundColor:'#f59e0b',borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{min:0,max:5}}}});

  // Revenue
  new Chart(document.getElementById('chart-rev-service'), { type:'doughnut', data:{ labels:rdata.revenue.service.map(s=>s.type.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())), datasets:[{data:rdata.revenue.service.map(s=>s.amount),backgroundColor:['#D32F2F','#3b82f6','#10b981','#f59e0b','#a855f7','#6b7280'],borderWidth:0}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'},tooltip:{callbacks:{label:fmtTooltipMwK}}}}});

  // Feedback
  new Chart(document.getElementById('chart-fb-dist'), { type:'bar', data:{ labels:['1 Star','2 Stars','3 Stars','4 Stars','5 Stars'], datasets:[{label:'Reviews',data:rdata.feedback.distribution,backgroundColor:['#ef4444','#f97316','#f59e0b','#84cc16','#10b981'],borderRadius:4}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}});
  new Chart(document.getElementById('chart-fb-time'), { type:'line', data:{ labels:rdata.feedback.over_time.map(x=>x.month), datasets:[{label:'Avg Rating',data:rdata.feedback.over_time.map(x=>x.avg),borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,0.1)',fill:true,tension:0.3}] }, options:{responsive:true,maintainAspectRatio:false,scales:{y:{min:0,max:5}}}});
}

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.add('hidden'));
    btn.classList.add('active');
    document.getElementById('tab-'+btn.dataset.tab).classList.remove('hidden');
  });
});

// Sidebar
document.addEventListener('DOMContentLoaded',()=>{
  const btn=document.getElementById('fes-dashboard-menu-btn'), sidebar=document.getElementById('fes-dashboard-sidebar'), overlay=document.getElementById('fes-dashboard-overlay');
  if(btn&&sidebar&&overlay){
    btn.addEventListener('click',()=>{ sidebar.classList.toggle('-translate-x-full'); sidebar.classList.toggle('translate-x-0'); overlay.classList.toggle('hidden'); });
    overlay.addEventListener('click',()=>{ sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0'); overlay.classList.add('hidden'); });
  }
  initCharts();
});

// PDF Download
async function downloadPDF(){
  try {
    const {jsPDF} = window.jspdf;
    if (!jsPDF) { alert('PDF library not loaded. Please refresh and try again.'); return; }
    const pdf = new jsPDF('p','mm','a4');
    const panels = Array.from(document.querySelectorAll('.tab-panel'));
    const activeBtn = document.querySelector('.tab-btn.active');
    const activeTab = activeBtn ? activeBtn.dataset.tab : 'overview';
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    for (let i = 0; i < panels.length; i++) {
      panels.forEach(p => p.classList.add('hidden'));
      panels[i].classList.remove('hidden');
      await new Promise(r => setTimeout(r, 250));
      const canvas = await html2canvas(panels[i], {scale: 2, useCORS: true, logging: false});
      const img = canvas.toDataURL('image/png');
      if (i > 0) pdf.addPage();
      const pw = 210, ph = 297;
      const ratio = Math.min(pw / canvas.width, ph / canvas.height);
      pdf.addImage(img, 'PNG', 0, 0, canvas.width * ratio, canvas.height * ratio);
    }
    panels.forEach(p => p.classList.add('hidden'));
    document.getElementById('tab-' + activeTab).classList.remove('hidden');
    if (activeBtn) activeBtn.classList.add('active');
    pdf.save('FES_Reports_<?php echo date('Y-m-d'); ?>.pdf');
  } catch (err) {
    console.error('PDF generation failed:', err);
    alert('Failed to generate PDF. Please try again.');
  }
}
</script>
</body>
</html>
