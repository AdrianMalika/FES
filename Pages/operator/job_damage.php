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

$operatorId = (int)($_SESSION['user_id'] ?? 0);
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$damageFormError = '';
$reportSaved = isset($_GET['saved']) && $_GET['saved'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['description'], $_POST['severity'])) {
    $postBid = (int)$_POST['booking_id'];
    $description = trim((string)$_POST['description']);
    $severity = trim((string)$_POST['severity']);
    $allowedSev = ['minor', 'major', 'critical'];

    if ($postBid <= 0) {
        $damageFormError = 'Invalid booking.';
    } elseif ($description === '' || mb_strlen($description) > 8000) {
        $damageFormError = 'Please enter a description (max 8000 characters).';
    } elseif (!in_array($severity, $allowedSev, true)) {
        $damageFormError = 'Please select a valid severity.';
    } else {
        $photoPath = '';
        if (!empty($_FILES['photo']['name']) || (isset($_FILES['photo']['error']) && (int)$_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE)) {
            if (!isset($_FILES['photo']['error']) || (int)$_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $damageFormError = 'Photo upload failed. Try a smaller file or a different image.';
            } elseif ((int)$_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $damageFormError = 'Photo must be 5 MB or less.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['photo']['tmp_name']);
                $mimeToExt = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($mimeToExt[$mime])) {
                    $damageFormError = 'Photo must be JPEG, PNG, or WebP.';
                } else {
                    $uploadDir = __DIR__ . '/../../assets/uploads/damage_reports/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = $mimeToExt[$mime];
                    $newName = 'dr_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $target = $uploadDir . $newName;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                        $photoPath = 'assets/uploads/damage_reports/' . $newName;
                    } else {
                        $damageFormError = 'Could not save the photo.';
                    }
                }
            }
        }

        if ($damageFormError === '') {
            try {
                $conn = getDBConnection();
                $pb = (int)$postBid;
                $oid = (int)$operatorId;
                $chkSql = "SELECT b.booking_id, b.equipment_id FROM bookings b WHERE b.booking_id = {$pb} AND b.operator_id = {$oid} LIMIT 1";
                $chkRes = $conn->query($chkSql);
                $brow = ($chkRes && ($r = $chkRes->fetch_assoc())) ? $r : null;
                if (!$brow) {
                    $damageFormError = 'This booking was not found or is not assigned to you.';
                } else {
                    $eqId = (string)$brow['equipment_id'];
                    $ins = false;
                    if ($photoPath !== '') {
                        $ins = $conn->prepare('INSERT INTO damage_reports (booking_id, operator_id, equipment_id, description, severity, photo_path) VALUES (?, ?, ?, ?, ?, ?)');
                        if ($ins) {
                            $ins->bind_param('iissss', $postBid, $operatorId, $eqId, $description, $severity, $photoPath);
                        }
                    } else {
                        $ins = $conn->prepare('INSERT INTO damage_reports (booking_id, operator_id, equipment_id, description, severity) VALUES (?, ?, ?, ?, ?)');
                        if ($ins) {
                            $ins->bind_param('iisss', $postBid, $operatorId, $eqId, $description, $severity);
                        }
                    }
                    if (!empty($ins) && $ins->execute()) {
                        $ins->close();
                        $conn->close();
                        header('Location: job_damage.php?id=' . $postBid . '&saved=1');
                        exit();
                    }
                    if (!empty($ins)) {
                        $ins->close();
                    }
                    $damageFormError = 'Could not save the report. If this persists, ask an admin to run the database migration (add_damage_reports.sql).';
                }
                $conn->close();
            } catch (Exception $e) {
                error_log('Operator damage report save: ' . $e->getMessage());
                $damageFormError = 'Could not save the report. Check that the damage_reports table exists (see database/add_damage_reports.sql).';
            }
        }
    }

    if ($damageFormError !== '') {
        $bookingId = $postBid;
    }
}

$booking = null;
$jobPickerList = [];
$bookingDamageReports = [];

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
            $drSql = "SELECT damage_report_id, description, severity, status, admin_notes, created_at, updated_at, photo_path
                      FROM damage_reports
                      WHERE booking_id = {$bid} AND operator_id = {$oid}
                      ORDER BY created_at DESC";
            try {
                $drRes = $conn->query($drSql);
            } catch (mysqli_sql_exception $e) {
                $drRes = false;
                error_log('Operator job_damage damage_reports list: ' . $e->getMessage());
            }
            if ($drRes) {
                while ($drRow = $drRes->fetch_assoc()) {
                    $bookingDamageReports[] = $drRow;
                }
            }
        }
    } else {
        $oid = (int)$operatorId;
        $sql = "SELECT b.booking_id, b.booking_date, b.status, b.service_type,
                       e.equipment_name, e.equipment_id AS eq_code
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
                WHERE b.operator_id = {$oid} AND b.status <> 'cancelled'
                ORDER BY b.booking_date DESC, b.booking_id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $jobPickerList[] = $row;
            }
        }
    }

    $conn->close();
} catch (Exception $e) {
    error_log('Operator job_damage load error: ' . $e->getMessage());
}

$showPicker = ($bookingId <= 0);
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

/** Status pill classes — match operator jobs.php */
function fes_damage_status_badge_class(string $status): string
{
    $map = [
        'pending' => 'bg-amber-50 text-amber-700',
        'confirmed' => 'bg-blue-50 text-blue-700',
        'in_progress' => 'bg-purple-50 text-purple-700',
        'completed' => 'bg-emerald-50 text-emerald-700',
        'cancelled' => 'bg-gray-100 text-gray-700',
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-700';
}

/** Damage report workflow status (submitted / acknowledged / closed) */
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $showPicker ? 'Report equipment damage' : ('Report damage — ' . htmlspecialchars($bookingLabel)); ?> - FES Operator</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Report equipment damage</h1>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php if ($showPicker): ?>
                            Choose which booking the damage report is for.
                        <?php elseif ($booking): ?>
                            Booking <?php echo htmlspecialchars($bookingLabel); ?>
                        <?php else: ?>
                            Job not found.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="<?php echo $showPicker ? 'jobs.php' : 'job_details.php?id=' . urlencode((string)$bookingId); ?>" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> <?php echo $showPicker ? 'Back to jobs' : 'Back to job'; ?>
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if ($showPicker): ?>
                <section class="bg-white rounded-xl shadow-card p-5 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-gray-900">Select a job</h2>
                        <?php if (!empty($jobPickerList)): ?>
                            <span class="text-xs text-gray-500"><?php echo count($jobPickerList); ?> booking<?php echo count($jobPickerList) === 1 ? '' : 's'; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Pick which booking the damage relates to.</p>

                    <?php if (empty($jobPickerList)): ?>
                        <p class="text-sm text-gray-500">You have no assigned jobs yet.</p>
                        <a href="jobs.php" class="mt-3 inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow text-sm">
                            <i class="fas fa-briefcase"></i> Go to My Jobs
                        </a>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                            <?php foreach ($jobPickerList as $j):
                                $st = (string)($j['status'] ?? '');
                                $badgeClass = fes_damage_status_badge_class($st);
                                $stLabel = ucfirst(str_replace('_', ' ', $st));
                                ?>
                                <li>
                                    <a href="job_damage.php?id=<?php echo (int)$j['booking_id']; ?>" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 text-sm">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-medium text-gray-900">#BK-<?php echo (int)$j['booking_id']; ?></span>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                                    <?php echo htmlspecialchars($stLabel); ?>
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?php echo htmlspecialchars($j['equipment_name'] ?? 'Equipment'); ?>
                                                · <?php echo !empty($j['booking_date']) ? htmlspecialchars(date('M j, Y', strtotime($j['booking_date']))) : ''; ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right text-gray-400 shrink-0"></i>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            <?php elseif (!$booking): ?>
                <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                    This booking was not found or is not assigned to you.
                    <div class="mt-4">
                        <a href="job_damage.php" class="text-fes-red font-medium text-sm hover:underline">Choose another job</a>
                    </div>
                </div>

            <?php else: ?>
                <section class="bg-white rounded-xl shadow-card p-6">
                    <?php if ($reportSaved): ?>
                        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            Your damage report was submitted successfully. An administrator will review it.
                        </div>
                    <?php endif; ?>
                    <?php if ($damageFormError !== ''): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <?php echo htmlspecialchars($damageFormError); ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-sm text-gray-600 mb-5">Reports are sent to the admin for review and follow-up.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Booking</div>
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($bookingLabel); ?></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($equipmentDisplay); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($bookingDamageReports)): ?>
                        <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50/80 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-200 bg-white/80">
                                <h2 class="text-sm font-semibold text-gray-900">
                                    <i class="fas fa-clipboard-check text-slate-500 mr-2"></i>
                                    Your reports for this job
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">Office status and messages from the administrator appear here.</p>
                            </div>
                            <ul class="divide-y divide-slate-200">
                                <?php foreach ($bookingDamageReports as $dr):
                                    $drSt = (string)($dr['status'] ?? '');
                                    $drBadge = fes_operator_dr_report_status_badge_class($drSt);
                                    $submitted = !empty($dr['created_at']) ? date('M j, Y — H:i', strtotime($dr['created_at'])) : '—';
                                    $updated = !empty($dr['updated_at']) ? date('M j, Y — H:i', strtotime($dr['updated_at'])) : '';
                                    $notes = trim((string)($dr['admin_notes'] ?? ''));
                                    ?>
                                    <li class="px-4 py-4 bg-white">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
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
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Your description</div>
                                        <p class="text-sm text-gray-700 whitespace-pre-wrap mb-3"><?php echo htmlspecialchars((string)($dr['description'] ?? '')); ?></p>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Severity</div>
                                        <div class="text-sm text-gray-800 mb-3"><?php echo htmlspecialchars(ucfirst((string)($dr['severity'] ?? ''))); ?></div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Message from office</div>
                                        <div class="text-sm text-gray-800 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2 whitespace-pre-wrap">
                                            <?php echo $notes !== '' ? htmlspecialchars($notes) : '<span class="text-gray-400 italic">No message yet — the admin will add notes when they review your report.</span>'; ?>
                                        </div>
                                        <?php if (!empty($dr['photo_path'])): ?>
                                            <div class="mt-3">
                                                <a href="../../<?php echo htmlspecialchars((string)$dr['photo_path']); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-fes-red hover:underline">
                                                    <i class="fas fa-image"></i> View your photo
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="job_damage.php?id=<?php echo (int)$booking['booking_id']; ?>" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$booking['booking_id']; ?>">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description of damage / fault</label>
                            <textarea name="description" rows="4" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red" placeholder="Describe what happened, when it started, and whether the machine is still usable."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Severity level</label>
                            <select name="severity" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                <option value="">Select severity</option>
                                <option value="minor">Minor</option>
                                <option value="major">Major</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Photo (optional)</label>
                            <input type="file" name="photo" accept="image/*" class="w-full text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                            <p class="mt-1 text-xs text-gray-500">Upload a photo of the damage. Max 5 MB.</p>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3">
                            <a href="job_details.php?id=<?php echo urlencode((string)$bookingId); ?>" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-fes-red hover:bg-[#b71c1c] text-white rounded-lg text-sm font-medium shadow">
                                <i class="fas fa-paper-plane"></i>
                                Submit (sends to admin)
                            </button>
                        </div>
                    </form>
                </section>
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
