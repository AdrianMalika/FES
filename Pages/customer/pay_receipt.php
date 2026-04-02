<?php

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../auth/signin.php');
    exit;
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId < 1) {
    header('Location: payments.php?payment=invalid');
    exit;
}

$download = isset($_GET['dl']) && $_GET['dl'] === '1';

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/fes_date.php';
if (is_readable(__DIR__ . '/../../includes/payment_config.php')) {
    require_once __DIR__ . '/../../includes/payment_config.php';
}

$customerId = (int)$_SESSION['user_id'];
$customerName = trim((string)($_SESSION['name'] ?? 'Customer'));
$customerEmail = trim((string)($_SESSION['email'] ?? ''));

$receipt = null;
$paymentRow = null;

try {
    $conn = getDBConnection();
    $sql = 'SELECT b.booking_id, b.booking_date, b.service_type, b.status, b.estimated_total_cost,
                    b.payment_status, b.payment_paid_at, b.payment_tx_ref,
                    e.equipment_name
             FROM bookings b
             LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
             WHERE b.booking_id = ? AND b.customer_id = ? AND b.payment_status = \'paid\'
             LIMIT 1';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $bookingId, $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $receipt = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($receipt) {
        $pstmt = $conn->prepare(
            "SELECT amount, currency, tx_ref, updated_at FROM booking_payments
             WHERE booking_id = ? AND user_id = ? AND status = 'paid' ORDER BY id DESC LIMIT 1"
        );
        if ($pstmt) {
            $pstmt->bind_param('ii', $bookingId, $customerId);
            $pstmt->execute();
            $pres = $pstmt->get_result();
            $paymentRow = $pres ? $pres->fetch_assoc() : null;
            $pstmt->close();
        }
    }
    $conn->close();
} catch (Throwable $e) {
    error_log('pay_receipt: ' . $e->getMessage());
}

if (!$receipt) {
    header('Location: payments.php?payment=receipt_unavailable');
    exit;
}

$amountWhole = $paymentRow ? (int)$paymentRow['amount'] : (int)max(1, (int)ceil((float)($receipt['estimated_total_cost'] ?? 0)));
$paidAt = fes_format_date_safe($receipt['payment_paid_at'] ?? null, 'M d, Y · H:i', '—');
$txRef = trim((string)($receipt['payment_tx_ref'] ?? ''));
if ($txRef === '' && is_array($paymentRow)) {
    $txRef = trim((string)($paymentRow['tx_ref'] ?? ''));
}
$receiptNo = 'RCP-BK-' . $bookingId . ($txRef !== '' ? '-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $txRef), 0, 8)) : '');
$issued = date('M d, Y · H:i');

$bid = (int)$receipt['booking_id'];
$equip = htmlspecialchars((string)($receipt['equipment_name'] ?? '—'), ENT_QUOTES, 'UTF-8');
$svc = htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($receipt['service_type'] ?? ''))), ENT_QUOTES, 'UTF-8');

$bkDate = htmlspecialchars(fes_format_date_safe($receipt['booking_date'] ?? null, 'M d, Y', '—'), ENT_QUOTES, 'UTF-8');
$amountMk = 'MK ' . number_format($amountWhole);
$custNameH = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
$custEmailH = htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8');

if ($download) {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_readable($autoload)) {
        header('Location: payments.php?payment=pdf_unavailable');
        exit;
    }
    require_once $autoload;
    require_once __DIR__ . '/../../includes/fes_receipt_pdf.php';
    try {
        $html = fes_build_receipt_pdf_html(
            $receipt,
            $customerName,
            $customerEmail,
            $amountWhole,
            $receiptNo,
            $txRef
        );
        $options = new Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream('FES-Receipt-BK-' . $bid . '.pdf', ['Attachment' => true]);
    } catch (Throwable $e) {
        error_log('pay_receipt pdf: ' . $e->getMessage());
        header('Location: payments.php?payment=pdf_error');
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment receipt — BK-<?php echo $bid; ?> — FES</title>
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
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body>
    <div class="min-h-screen w-full bg-gray-100 py-10 px-4">
        <div class="max-w-lg mx-auto no-print mb-6 flex items-center justify-between gap-4">
            <a href="payments.php" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
                Back to Payments
            </a>
        </div>
        <div class="max-w-lg mx-auto">

            <div class="bg-white rounded-xl shadow-card p-6">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900">Receipt details</h2>
                                <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($receiptNo, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Amount paid</div>
                                <div class="mt-1 text-2xl font-semibold text-fes-red"><?php echo htmlspecialchars($amountMk, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Booking</div>
                                <div class="text-gray-900 font-medium">#BK-<?php echo $bid; ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service date</div>
                                <div class="text-gray-900 font-medium"><?php echo $bkDate; ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Equipment</div>
                                <div class="text-gray-900 font-medium"><?php echo $equip; ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Service type</div>
                                <div class="text-gray-900 font-medium"><?php echo $svc; ?></div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Bill to</div>
                                <div class="text-gray-900 font-medium"><?php echo $custNameH; ?></div>
                                <?php if ($custEmailH !== ''): ?>
                                    <div class="text-sm text-gray-600 mt-0.5"><?php echo $custEmailH; ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Paid on</div>
                                <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($paidAt, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Payment method</div>
                                <div class="text-gray-900 font-medium">Card (Stripe)</div>
                            </div>
                            <?php if ($txRef !== ''): ?>
                            <div class="md:col-span-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Reference</div>
                                <div class="text-gray-900 font-medium text-xs break-all"><?php echo htmlspecialchars($txRef, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-500 text-center">
                            Issued <?php echo htmlspecialchars($issued, ENT_QUOTES, 'UTF-8'); ?> · Malawi Kwacha (MK)<br>
                            Farm Equipment Services — This receipt confirms payment for the booking shown above.
                        </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-center gap-3 no-print">
                <a href="pay_receipt.php?booking_id=<?php echo $bid; ?>&amp;dl=1" class="inline-flex items-center gap-2 border border-gray-200 text-gray-700 font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 text-sm bg-white">
                    <i class="fas fa-download"></i> Download PDF
                </a>
                <button type="button" class="inline-flex items-center gap-2 bg-fes-red hover:bg-[#b71c1c] text-white font-semibold px-4 py-2 rounded-lg text-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> Print / Save as PDF
                </button>
            </div>

        </div>
    </div>
</body>
</html>