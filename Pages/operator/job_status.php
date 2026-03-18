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
$operatorName = $_SESSION['name'] ?? 'Operator';
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';
$booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['new_status'])) {
    $bookingId = intval($_POST['booking_id']);
    $newStatus = $_POST['new_status'];
    $allowed = ['in_progress', 'completed'];
    if ($bookingId > 0 && in_array($newStatus, $allowed, true)) {
        try {
            $conn = getDBConnection();
            $sql = "UPDATE bookings 
                    SET status = ?,
                        operator_start_time = IF(?, IFNULL(operator_start_time, NOW()), operator_start_time),
                        operator_end_time = IF(?, IFNULL(operator_end_time, NOW()), operator_end_time),
                        updated_at = NOW()
                    WHERE booking_id = ? AND operator_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $isStart = $newStatus === 'in_progress' ? 1 : 0;
                $isEnd = $newStatus === 'completed' ? 1 : 0;
                $stmt->bind_param('siiii', $newStatus, $isStart, $isEnd, $bookingId, $operatorId);
                if ($stmt->execute()) {
                    $message = 'Job status updated.';
                }
                $stmt->close();
            }
            $conn->close();
        } catch (Exception $e) {
            error_log('Operator job status update error: ' . $e->getMessage());
            $error = 'Failed to update job status.';
        }
    }
}

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.booking_id, b.status, b.booking_date, b.service_type,
                       e.equipment_name,
                       u.name AS customer_name
                FROM bookings b
                LEFT JOIN equipment e ON e.equipment_id = b.equipment_id
                LEFT JOIN users u ON u.user_id = b.customer_id
                WHERE b.booking_id = ? AND b.operator_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ii', $bookingId, $operatorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $booking = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Operator job status fetch error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Job Status - FES Operator</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Update Job Status</h1>
                    <p class="text-xs text-gray-500 mt-1">Booking #BK-<?php echo htmlspecialchars((string)$bookingId); ?></p>
                </div>
            </div>

            <a href="job_details.php?id=<?php echo urlencode((string)$bookingId); ?>" class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Back to Job
            </a>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if (!empty($message)): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$booking): ?>
                <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                    Job not found or you don’t have access.
                </div>
            <?php else: ?>
                <section class="bg-white rounded-xl shadow-card p-6 max-w-2xl">
                    <div class="mb-4">
                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Customer</div>
                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['customer_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="mb-6 text-sm text-gray-600">
                        <?php echo htmlspecialchars($booking['equipment_name'] ?? ''); ?> · <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?> · <?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?>
                    </div>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars((string)$bookingId); ?>">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                            <select name="new_status" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-fes-red focus:border-fes-red">
                                <option value="in_progress" <?php echo ($booking['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo ($booking['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-medium px-4 py-2.5 rounded-lg shadow">
                                <i class="fas fa-save"></i> Save Status
                            </button>
                            <a href="job_details.php?id=<?php echo urlencode((string)$bookingId); ?>" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
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
