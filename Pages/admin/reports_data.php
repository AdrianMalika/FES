<?php
/**
 * Reports Data API for FES Admin Dashboard
 * Returns JSON payload for all report tabs, filtered by ?from, ?to, ?depot
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || (string)($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

require_once __DIR__ . '/../../includes/database.php';

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$from = (string)($_GET['from'] ?? '');
$to   = (string)($_GET['to']   ?? '');
$depot = (string)($_GET['depot'] ?? '');

$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
$from = preg_match($datePattern, $from) ? $from : '';
$to   = preg_match($datePattern, $to)   ? $to   : '';

$wheres = [];
$wheresB = [];
$wheresBP = [];
$wheresBF = [];
$wheresEM = [];

if ($from !== '') { $wheres[] = "b.created_at >= '{$from} 00:00:00'"; $wheresB[] = "created_at >= '{$from} 00:00:00'"; $wheresBP[] = "bp.created_at >= '{$from} 00:00:00'"; $wheresBF[] = "bf.created_at >= '{$from} 00:00:00'"; $wheresEM[] = "scheduled_date >= '{$from}'"; }
if ($to   !== '') { $wheres[] = "b.created_at <= '{$to} 23:59:59'";   $wheresB[] = "created_at <= '{$to} 23:59:59'";   $wheresBP[] = "bp.created_at <= '{$to} 23:59:59'";   $wheresBF[] = "bf.created_at <= '{$to} 23:59:59'";   $wheresEM[] = "scheduled_date <= '{$to}'"; }
if ($depot !== '') {
    $d = e($depot);
    $wheres[] = "b.equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')";
    $wheresB[] = "equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')";
    $wheresBP[] = "bp.booking_id IN (SELECT booking_id FROM bookings WHERE equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}'))";
    $wheresBF[] = "bf.booking_id IN (SELECT booking_id FROM bookings WHERE equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}'))";
    $wheresEM[] = "equipment_id IN (SELECT equipment_id FROM equipment WHERE location = '{$d}')";
}

$whereBookings = $wheres !== [] ? implode(' AND ', $wheres) : '1=1';
$whereB = $wheresB !== [] ? implode(' AND ', $wheresB) : '1=1';
$whereBP = $wheresBP !== [] ? implode(' AND ', $wheresBP) : '1=1';
$whereBF = $wheresBF !== [] ? implode(' AND ', $wheresBF) : '1=1';
$whereEM = $wheresEM !== [] ? implode(' AND ', $wheresEM) : '1=1';

$year = (int)date('Y');
$out = [
    'filters' => ['from' => $from, 'to' => $to, 'depot' => $depot],
    'overview' => [],
    'bookings' => [],
    'equipment' => [],
    'operators' => [],
    'revenue' => [],
    'feedback' => [],
];

$conn = getDBConnection();

// ── OVERVIEW ──
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings}")->fetch_assoc();
$out['overview']['total_bookings'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='paid'")->fetch_assoc();
$out['overview']['total_revenue'] = (float)($row['s'] ?? 0);

$eqWhere = $depot !== '' ? "location = '{$depot}'" : '1=1';
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere}")->fetch_assoc();
$out['overview']['total_equipment'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere} AND status='in_use'")->fetch_assoc();
$out['overview']['equipment_in_use'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COUNT(DISTINCT operator_id) AS c FROM bookings b WHERE {$whereBookings} AND b.status='in_progress'")->fetch_assoc();
$out['overview']['operators_on_duty'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc();
$out['overview']['avg_rating'] = $row['a'] !== null ? round((float)$row['a'], 2) : 0;

$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereBookings} AND b.status='pending'")->fetch_assoc();
$out['overview']['pending_approvals'] = (int)($row['c'] ?? 0);

$mb = [];
for ($m = 1; $m <= 12; $m++) { $mb[(string)$m] = 0; }
$res = $conn->query("SELECT MONTH(created_at) AS m, COUNT(*) AS c FROM bookings b WHERE {$whereB} AND YEAR(created_at) = {$year} GROUP BY m");
while ($r = $res->fetch_assoc()) { $mb[(string)$r['m']] = (int)$r['c']; }
$out['overview']['monthly_bookings'] = array_values($mb);

$mr = [];
for ($m = 1; $m <= 12; $m++) { $mr[(string)$m] = 0; }
$res = $conn->query("SELECT MONTH(created_at) AS m, COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereB} AND payment_status='paid' AND YEAR(created_at) = {$year} GROUP BY m");
while ($r = $res->fetch_assoc()) { $mr[(string)$r['m']] = (float)$r['s']; }
$out['overview']['monthly_revenue'] = array_values($mr);

$statuses = ['pending' => 0, 'confirmed' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY status");
while ($r = $res->fetch_assoc()) { $statuses[$r['status']] = (int)$r['c']; }
$out['overview']['status_breakdown'] = $statuses;

$svc = [];
$res = $conn->query("SELECT service_type, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY service_type ORDER BY c DESC");
while ($r = $res->fetch_assoc()) { $svc[] = ['type' => $r['service_type'], 'count' => (int)$r['c']]; }
$out['overview']['service_types'] = $svc;

// ── BOOKINGS ──
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='confirmed'")->fetch_assoc();
$out['bookings']['confirmed'] = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='completed'")->fetch_assoc();
$out['bookings']['completed'] = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='cancelled'")->fetch_assoc();
$out['bookings']['cancelled'] = (int)($row['c'] ?? 0);

$dbl = $conn->query("SELECT equipment_id, booking_date, COUNT(*) AS c FROM bookings b WHERE {$whereB} GROUP BY equipment_id, booking_date HAVING c > 1");
$out['bookings']['double_bookings'] = (int)$dbl->num_rows;

$bk = [];
$sql = "SELECT b.booking_id, u.name AS customer_name, b.service_type, e.equipment_name, o.name AS operator_name, b.booking_date, b.status, b.estimated_total_cost
        FROM bookings b
        LEFT JOIN users u ON u.user_id = b.customer_id
        LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
        LEFT JOIN users o ON o.user_id = b.operator_id
        WHERE {$whereBookings}
        ORDER BY b.created_at DESC LIMIT 500";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) { $bk[] = $r; }
$out['bookings']['rows'] = $bk;

// ── EQUIPMENT ──
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere}")->fetch_assoc();
$out['equipment']['total_fleet'] = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere} AND status='in_use'")->fetch_assoc();
$out['equipment']['in_use'] = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COUNT(*) AS c FROM equipment WHERE {$eqWhere} AND status='maintenance'")->fetch_assoc();
$out['equipment']['under_maintenance'] = (int)($row['c'] ?? 0);
$row = $conn->query("SELECT COALESCE(SUM(cost),0) AS s FROM equipment_maintenance WHERE {$whereEM}")->fetch_assoc();
$out['equipment']['maintenance_cost'] = (float)($row['s'] ?? 0);

$eqUtil = [];
$res = $conn->query("SELECT category, COUNT(*) AS total, SUM(CASE WHEN status='in_use' THEN 1 ELSE 0 END) AS in_use FROM equipment WHERE {$eqWhere} GROUP BY category");
while ($r = $res->fetch_assoc()) { $eqUtil[] = $r; }
$out['equipment']['util_by_type'] = $eqUtil;

$maintChart = [];
$res = $conn->query("SELECT maintenance_type, SUM(CASE WHEN status='scheduled' THEN 1 ELSE 0 END) AS scheduled, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed FROM equipment_maintenance WHERE {$whereEM} GROUP BY maintenance_type");
while ($r = $res->fetch_assoc()) { $maintChart[] = $r; }
$out['equipment']['maintenance_chart'] = $maintChart;

$eqRows = [];
$res = $conn->query("SELECT equipment_name, category, total_usage_hours, last_maintenance, status FROM equipment WHERE {$eqWhere} ORDER BY equipment_name");
while ($r = $res->fetch_assoc()) {
    $next = null;
    if (!empty($r['last_maintenance'])) {
        $next = date('Y-m-d', strtotime($r['last_maintenance'] . ' +90 days'));
    }
    $r['next_service_due'] = $next;
    $eqRows[] = $r;
}
$out['equipment']['rows'] = $eqRows;

// ── OPERATORS ──
$row = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='operator'")->fetch_assoc();
$out['operators']['total'] = (int)($row['c'] ?? 0);

$availSql = "SELECT COUNT(*) AS c FROM users WHERE role='operator' AND user_id NOT IN (SELECT DISTINCT operator_id FROM bookings WHERE status='in_progress' AND operator_id IS NOT NULL)";
$row = $conn->query($availSql)->fetch_assoc();
$out['operators']['available'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COUNT(*) AS c FROM bookings b WHERE {$whereB} AND status='completed'")->fetch_assoc();
$completedAll = (int)($row['c'] ?? 0);
$out['operators']['avg_jobs'] = $out['operators']['total'] > 0 ? round($completedAll / $out['operators']['total'], 1) : 0;

$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc();
$out['operators']['avg_rating'] = $row['a'] !== null ? round((float)$row['a'], 2) : 0;

$opJobs = [];
$res = $conn->query("SELECT u.user_id, u.name, COUNT(b.booking_id) AS jobs FROM users u LEFT JOIN bookings b ON b.operator_id=u.user_id AND {$whereBookings} WHERE u.role='operator' GROUP BY u.user_id ORDER BY jobs DESC");
while ($r = $res->fetch_assoc()) { $opJobs[] = $r; }
$out['operators']['jobs_per_operator'] = $opJobs;

$opRatings = [];
$res = $conn->query("SELECT u.user_id, u.name, ROUND(AVG(bf.rating),2) AS avg_rating FROM users u LEFT JOIN booking_feedback bf ON bf.operator_id=u.user_id AND {$whereBF} WHERE u.role='operator' GROUP BY u.user_id");
while ($r = $res->fetch_assoc()) { $opRatings[] = $r; }
$out['operators']['ratings'] = $opRatings;

$opRows = [];
$sql = "SELECT u.name,
               COUNT(b.booking_id) AS jobs_completed,
               ROUND(AVG(bf.rating),2) AS avg_rating,
               ROUND(SUM(CASE WHEN b.status='completed' THEN COALESCE(TIMESTAMPDIFF(MINUTE,b.operator_start_time,b.operator_end_time),0) ELSE 0 END)/60,1) AS hours_logged,
               COUNT(DISTINCT b.equipment_id) AS equipment_used,
               CASE WHEN u.user_id IN (SELECT DISTINCT operator_id FROM bookings WHERE status='in_progress' AND operator_id IS NOT NULL) THEN 'busy' ELSE 'available' END AS availability
        FROM users u
        LEFT JOIN bookings b ON b.operator_id=u.user_id AND b.status='completed' AND {$whereBookings}
        LEFT JOIN booking_feedback bf ON bf.operator_id=u.user_id
        WHERE u.role='operator'
        GROUP BY u.user_id
        ORDER BY jobs_completed DESC";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) { $opRows[] = $r; }
$out['operators']['rows'] = $opRows;

// ── REVENUE ──
$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='paid'")->fetch_assoc();
$out['revenue']['total'] = (float)($row['s'] ?? 0);

$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.payment_status='unpaid'")->fetch_assoc();
$out['revenue']['pending'] = (float)($row['s'] ?? 0);

$row = $conn->query("SELECT COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereBookings} AND b.status='cancelled'")->fetch_assoc();
$out['revenue']['refunds'] = 0;

$out['revenue']['penalties'] = 0;

$revSvc = [];
$res = $conn->query("SELECT service_type, COALESCE(SUM(estimated_total_cost),0) AS s FROM bookings b WHERE {$whereB} AND payment_status='paid' GROUP BY service_type");
while ($r = $res->fetch_assoc()) { $revSvc[] = ['type' => $r['service_type'], 'amount' => (float)$r['s']]; }
$out['revenue']['by_service'] = $revSvc;

$txRows = [];
$sql = "SELECT bp.id AS transaction_id, bp.booking_id, u.name AS customer_name, bp.amount, bp.provider, bp.status, bp.created_at
        FROM booking_payments bp
        LEFT JOIN bookings b ON b.booking_id = bp.booking_id
        LEFT JOIN users u ON u.user_id = bp.user_id
        WHERE {$whereBP}
        ORDER BY bp.created_at DESC LIMIT 500";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) { $txRows[] = $r; }
$out['revenue']['transactions'] = $txRows;

// ── FEEDBACK ──
$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc();
$out['feedback']['total'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} AND rating=5")->fetch_assoc();
$out['feedback']['five_star'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} AND rating IN (1,2)")->fetch_assoc();
$out['feedback']['low_star'] = (int)($row['c'] ?? 0);

$row = $conn->query("SELECT AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF}")->fetch_assoc();
$out['feedback']['avg'] = $row['a'] !== null ? round((float)$row['a'], 2) : 0;

$dist = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
$res = $conn->query("SELECT rating, COUNT(*) AS c FROM booking_feedback bf WHERE {$whereBF} GROUP BY rating");
while ($r = $res->fetch_assoc()) { $dist[(string)$r['rating']] = (int)$r['c']; }
$out['feedback']['distribution'] = array_values($dist);

$rt = [];
$res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, AVG(rating) AS a FROM booking_feedback bf WHERE {$whereBF} GROUP BY month ORDER BY month LIMIT 12");
while ($r = $res->fetch_assoc()) { $rt[] = ['month' => $r['month'], 'avg' => $r['a'] !== null ? round((float)$r['a'], 2) : 0]; }
$out['feedback']['over_time'] = $rt;

$fbRows = [];
$sql = "SELECT bf.booking_id, b.service_type, bf.rating, bf.comment, bf.created_at, c.name AS customer_name
        FROM booking_feedback bf
        LEFT JOIN bookings b ON b.booking_id = bf.booking_id
        LEFT JOIN users c ON c.user_id = bf.customer_id
        WHERE {$whereBF}
        ORDER BY bf.created_at DESC LIMIT 100";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) { $fbRows[] = $r; }
$out['feedback']['recent'] = $fbRows;

$conn->close();

echo json_encode($out, JSON_PRETTY_PRINT);
exit();
