<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

// Check if user has customer role
if ($_SESSION['role'] !== 'customer') {
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            exit();
        case 'operator':
            header('Location: ../operator/dashboard.php');
            exit();
        default:
            header('Location: ../auth/signin.php');
            exit();
    }
}

require_once __DIR__ . '/../../includes/database.php';

$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = null;

if ($bookingId > 0) {
    try {
        $conn = getDBConnection();
        $sql = "SELECT b.*, e.equipment_name, e.category, e.location AS equipment_location, e.daily_rate, e.hourly_rate, e.status AS equipment_status
                FROM bookings b
                JOIN equipment e ON e.equipment_id = b.equipment_id
                WHERE b.booking_id = ? AND b.customer_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $customerId = intval($_SESSION['user_id']);
            $stmt->bind_param('ii', $bookingId, $customerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $booking = $row;
            }
            $stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        error_log('Booking details error: ' . $e->getMessage());
    }
}

$customerName = $_SESSION['name'] ?? 'Customer';
$status = $booking['status'] ?? 'pending';
$badgeClasses = [
    'pending' => 'bg-amber-50 text-amber-700',
    'confirmed' => 'bg-blue-50 text-blue-700',
    'in_progress' => 'bg-purple-50 text-purple-700',
    'completed' => 'bg-emerald-50 text-emerald-700',
    'cancelled' => 'bg-gray-100 text-gray-700'
];
$badgeClass = $badgeClasses[$status] ?? 'bg-gray-100 text-gray-700';
$serviceLocation = $booking ? trim($booking['service_location'] ?? '') : '';
if (empty($serviceLocation) && !empty($booking['field_address'])) {
    $serviceLocation = $booking['field_address'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - FES</title>
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
                    fontFamily: {
                        display: ['"Barlow Condensed"', 'sans-serif'],
                        body: ['Barlow', 'sans-serif'],
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
    </style>
</head>
<body>
    <div class="min-h-screen w-full bg-gray-100">
        <div class="flex min-h-screen">
            <?php include __DIR__ . '/include/sidebar.php'; ?>

            <div id="fes-dashboard-overlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

            <div class="flex-1 flex flex-col min-w-0 md:ml-64">
                <header class="bg-white px-6 py-7 flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <button id="fes-dashboard-menu-btn" class="md:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg border border-gray-200 text-gray-600" aria-label="Open menu" aria-controls="fes-dashboard-sidebar" aria-expanded="false">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <div class="text-sm text-gray-500">Customer</div>
                            <h1 class="text-xl font-semibold text-gray-900">Booking Details</h1>
                            <p class="text-xs text-gray-500 mt-1">Welcome back, <?php echo htmlspecialchars($customerName); ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="bookings.php" class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 font-medium px-4 py-2 rounded-lg hover:bg-gray-50">
                            <i class="fas fa-arrow-left"></i>
                            Back to Bookings
                        </a>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6">
                    <?php if (!$booking): ?>
                        <div class="bg-white rounded-xl shadow-card p-6 text-center text-gray-600">
                            Booking not found or you don’t have access.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                            <div class="bg-white rounded-xl shadow-card p-5">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Booking ID</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900">#BK-<?php echo htmlspecialchars((string)$booking['booking_id']); ?></div>
                                <div class="mt-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl shadow-card p-5">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Service Date</div>
                                <div class="mt-2 text-2xl font-semibold text-gray-900">
                                    <?php echo !empty($booking['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($booking['booking_date']))) : 'N/A'; ?>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">Days: <?php echo htmlspecialchars((string)($booking['service_days'] ?? 1)); ?></div>
                            </div>
                            <div class="bg-white rounded-xl shadow-card p-5">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Estimated Total</div>
                                <div class="mt-2 text-2xl font-semibold text-fes-red">
                                    MK <?php echo number_format((float)($booking['estimated_total_cost'] ?? 0)); ?>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">Service: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['service_type'] ?? 'N/A'))); ?></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <section class="lg:col-span-2 bg-white rounded-xl shadow-card p-6">
                                <h2 class="text-base font-semibold text-gray-900 mb-4">Booking Information</h2>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_name'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(ucfirst($booking['category'] ?? '')); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment Location</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['equipment_location'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-gray-500 mt-1">Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $booking['equipment_status'] ?? 'N/A'))); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service Location</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($serviceLocation ?: 'N/A'); ?></div>
                                        <?php if (!empty($booking['field_address']) && $serviceLocation !== $booking['field_address']): ?>
                                            <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($booking['field_address']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Contact Phone</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['contact_phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Field Size</div>
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($booking['field_hectares'] ?? 'Not specified'); ?> areas</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Notes</div>
                                        <div class="text-gray-700"><?php echo !empty($booking['notes']) ? htmlspecialchars($booking['notes']) : '—'; ?></div>
                                    </div>
                                </div>
                            </section>

                            <section class="bg-white rounded-xl shadow-card p-6">
                                <h2 class="text-base font-semibold text-gray-900 mb-4">Timeline</h2>
                                <div class="space-y-4 text-sm text-gray-600">
                                    <div class="flex items-center gap-3">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <div>Submitted: <?php echo !empty($booking['created_at']) ? htmlspecialchars(date('M d, Y · H:i', strtotime($booking['created_at']))) : 'N/A'; ?></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="h-2 w-2 rounded-full bg-gray-300"></span>
                                        <div>Last Update: <?php echo !empty($booking['updated_at']) ? htmlspecialchars(date('M d, Y · H:i', strtotime($booking['updated_at']))) : 'N/A'; ?></div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
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
