<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/signin.php');
    exit();
}

if ($_SESSION['role'] !== 'customer') {
    switch ($_SESSION['role']) {
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
require_once __DIR__ . '/../../includes/stripe_checkout.php';

$rows = [];
$customerId = (int)$_SESSION['user_id'];
$customerName = $_SESSION['name'] ?? 'Customer';
$payConfigured = fes_stripe_configured();

try {
    $conn = getDBConnection();
    $sql = "SELECT b.booking_id, b.booking_date, b.service_type, b.status,
                   b.estimated_total_cost, b.payment_status,
                   COALESCE(NULLIF(b.service_location, ''), b.field_address) AS service_location,
                   e.equipment_name
            FROM bookings b
            JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
            WHERE b.customer_id = ?
            ORDER BY b.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) {
            $rows[] = $r;
        }
        $stmt->close();
    }
    $conn->close();
} catch (Throwable $e) {
    error_log('Customer payments: ' . $e->getMessage());
}

$flash = null;
$flashType = 'info';
$pay = $_GET['payment'] ?? '';
switch ($pay) {
    case 'success':
        $flash = 'Payment received. Thank you.';
        $flashType = 'success';
        break;
    case 'pending':
        $flash = 'Payment is still processing. Refresh this page in a moment.';
        $flashType = 'warning';
        break;
    case 'cancelled':
        $flash = 'Checkout was cancelled. You can try again when you are ready.';
        $flashType = 'neutral';
        break;
    case 'failed':
        $flash = 'Payment was not completed.';
        $flashType = 'error';
        break;
    case 'issue':
        $flash = 'We could not confirm the payment. Check your bookings or contact support if money was taken.';
        $flashType = 'warning';
        break;
    case 'not_configured':
        $flash = 'Online payments are not set up yet. Configure Stripe in includes/payment_config.php.';
        $flashType = 'warning';
        break;
    case 'checkout_error':
        $d = isset($_GET['detail']) ? (string)$_GET['detail'] : '';
        $flash = 'Could not start checkout' . ($d !== '' ? ': ' . htmlspecialchars(rawurldecode($d), ENT_QUOTES, 'UTF-8') : '.');
        $flashType = 'error';
        break;
    case 'invalid':
        $flash = 'That booking was not found.';
        $flashType = 'error';
        break;
    case 'already_paid':
        $flash = 'This booking is already paid.';
        $flashType = 'info';
        break;
    case 'cancelled_booking':
        $flash = 'Cancelled bookings cannot be paid.';
        $flashType = 'error';
        break;
    case 'no_amount':
        $flash = 'This booking has no amount to pay.';
        $flashType = 'error';
        break;
    case 'error':
        $flash = 'Something went wrong. Please try again.';
        $flashType = 'error';
        break;
    case 'receipt_unavailable':
        $flash = 'Receipt is not available for that booking. It may not be paid yet or does not belong to your account.';
        $flashType = 'warning';
        break;
    case 'pdf_unavailable':
        $flash = 'PDF download is not available. Run composer install on the server, then try again.';
        $flashType = 'warning';
        break;
    case 'pdf_error':
        $flash = 'The receipt PDF could not be generated. Please try again or use Print / Save as PDF on the receipt page.';
        $flashType = 'error';
        break;
    default:
        break;
}

function fes_payment_status_badge(string $ps): array
{
    switch ($ps) {
        case 'paid':
            return ['Paid', 'bg-emerald-50 text-emerald-800'];
        case 'pending':
            return ['Pending', 'bg-amber-50 text-amber-800'];
        case 'failed':
            return ['Failed', 'bg-red-50 text-red-800'];
        default:
            return ['Unpaid', 'bg-gray-100 text-gray-700'];
    }
}

function fes_job_status_badge(string $st): string
{
    $map = [
        'pending' => 'bg-amber-50 text-amber-700',
        'confirmed' => 'bg-blue-50 text-blue-700',
        'in_progress' => 'bg-purple-50 text-purple-700',
        'completed' => 'bg-emerald-50 text-emerald-700',
        'cancelled' => 'bg-gray-100 text-gray-700',
    ];
    return $map[$st] ?? 'bg-gray-100 text-gray-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - FES</title>
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
                    boxShadow: { card: '0 4px 15px rgba(0,0,0,0.05)' }
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
                            <h1 class="text-xl font-semibold text-gray-900">Payments</h1>
                            <p class="text-xs text-gray-500 mt-1">Welcome back, <?php echo htmlspecialchars($customerName); ?></p>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto p-6">
                    <?php if ($flash !== null): ?>
                        <?php
                        switch ($flashType) {
                            case 'success':
                                $box = 'border-emerald-200 bg-emerald-50 text-emerald-900';
                                break;
                            case 'error':
                                $box = 'border-red-200 bg-red-50 text-red-900';
                                break;
                            case 'warning':
                                $box = 'border-amber-200 bg-amber-50 text-amber-900';
                                break;
                            case 'neutral':
                                $box = 'border-slate-200 bg-slate-50 text-slate-800';
                                break;
                            default:
                                $box = 'border-blue-200 bg-blue-50 text-blue-900';
                        }
                        ?>
                        <div class="mb-5 rounded-xl border px-4 py-3 text-sm <?php echo $box; ?>">
                            <?php echo $flash; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$payConfigured): ?>
                        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950">
                            <strong class="font-semibold">Stripe Checkout is not active.</strong>
                            Run <code class="bg-white/60 px-1 rounded">composer install</code>, set
                            <code class="bg-white/60 px-1 rounded">FES_STRIPE_SECRET_KEY</code> (Dashboard → Developers → API keys),
                            <code class="bg-white/60 px-1 rounded">FES_PUBLIC_BASE_URL</code> (HTTPS public URL, no trailing slash), and
                            <code class="bg-white/60 px-1 rounded">FES_STRIPE_CURRENCY</code> must be <code class="bg-white/60 px-1 rounded">mwk</code> (Malawi Kwacha) in
                            <code class="bg-white/60 px-1 rounded">includes/payment_config.php</code>.
                        </div>
                    <?php endif; ?>

                    <section class="bg-white rounded-xl shadow-card p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-semibold text-gray-900">Booking payments</h2>
                            <span class="text-xs text-gray-500"><?php echo count($rows); ?> bookings</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 border-b uppercase tracking-wider">
                                        <th class="py-3 pr-4">Booking</th>
                                        <th class="py-3 pr-4">Equipment</th>
                                        <th class="py-3 pr-4">Date</th>
                                        <th class="py-3 pr-4">Job status</th>
                                        <th class="py-3 pr-4">Amount</th>
                                        <th class="py-3 pr-4">Payment</th>
                                        <th class="py-3 pr-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm text-gray-900">
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-gray-500">No bookings yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <?php
                                            $bid = (int)$row['booking_id'];
                                            $jobSt = (string)($row['status'] ?? 'pending');
                                            $paySt = (string)($row['payment_status'] ?? 'unpaid');
                                            [$payLabel, $payClass] = fes_payment_status_badge($paySt);
                                            $jobClass = fes_job_status_badge($jobSt);
                                            $cost = (float)($row['estimated_total_cost'] ?? 0);
                                            $canPay = $payConfigured
                                                && $jobSt !== 'cancelled'
                                                && $paySt !== 'paid'
                                                && $cost > 0;
                                            ?>
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="py-3 pr-4 font-medium text-fes-red">#BK-<?php echo $bid; ?></td>
                                                <td class="py-3 pr-4"><?php echo htmlspecialchars($row['equipment_name'] ?? '—'); ?></td>
                                                <td class="py-3 pr-4">
                                                    <?php echo !empty($row['booking_date']) ? htmlspecialchars(date('M d, Y', strtotime($row['booking_date']))) : '—'; ?>
                                                </td>
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $jobClass; ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $jobSt))); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 pr-4">MK <?php echo number_format($cost); ?></td>
                                                <td class="py-3 pr-4">
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?php echo $payClass; ?>">
                                                        <?php echo htmlspecialchars($payLabel); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 pr-4 text-right">
                                                    <?php if ($canPay): ?>
                                                        <form method="post" action="pay_start.php" class="inline">
                                                            <input type="hidden" name="booking_id" value="<?php echo $bid; ?>">
                                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-fes-red hover:bg-[#b71c1c] text-white text-xs font-semibold px-3 py-1.5 rounded-lg">
                                                                <i class="fas fa-lock"></i> Pay with Stripe
                                                            </button>
                                                        </form>
                                                    <?php elseif ($paySt === 'paid'): ?>
                                                        <div class="flex flex-col items-end gap-1.5 sm:flex-row sm:flex-wrap sm:justify-end sm:items-center">
                                                            <a href="pay_receipt.php?booking_id=<?php echo $bid; ?>" class="inline-flex items-center gap-1.5 text-fes-red hover:text-[#b71c1c] text-xs font-semibold whitespace-nowrap">
                                                                <i class="fas fa-file-invoice"></i> View receipt
                                                            </a>
                                                            <a href="pay_receipt.php?booking_id=<?php echo $bid; ?>&amp;dl=1" class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 text-xs font-medium border border-gray-200 rounded-lg px-2.5 py-1 whitespace-nowrap bg-white hover:bg-gray-50">
                                                                <i class="fas fa-download"></i> PDF
                                                            </a>
                                                        </div>
                                                    <?php elseif ($jobSt === 'cancelled'): ?>
                                                        <span class="text-xs text-gray-400">N/A</span>
                                                    <?php else: ?>
                                                        <span class="text-xs text-gray-400">—</span>
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
