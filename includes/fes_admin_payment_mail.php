<?php

/**
 * Email all admin users when a booking payment is recorded (Stripe Checkout).
 *
 * Primary inbox (always notified, deduped against DB admins):
 * adrianmalika01@gmail.com
 */

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * @param int $amountWhole Whole Malawi Kwacha (same as booking_payments.amount)
 * @param string $currencyUpper Stripe row currency (expected MWK); kept for caller compatibility
 */
function fes_send_admin_payment_received_email(mysqli $conn, int $bookingId, int $amountWhole, string $currencyUpper): void
{
    $bookingId = max(1, $bookingId);
    $amountWhole = max(0, $amountWhole);

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        error_log('Payment admin notify: vendor/autoload.php missing');
        return;
    }
    require_once $autoload;

    $configPath = __DIR__ . '/email_config.php';
    if (!is_readable($configPath)) {
        error_log('Payment admin notify: email_config.php missing');
        return;
    }
    $config = include $configPath;
    if (!is_array($config) || empty($config['host']) || empty($config['username']) || empty($config['from_email'])) {
        error_log('Payment admin notify: invalid email_config.php');
        return;
    }

    $primaryNotify = 'adrianmalika01@gmail.com';

    $admins = [];
    $adminStmt = $conn->prepare("SELECT name, email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email <> ''");
    if ($adminStmt) {
        $adminStmt->execute();
        $adminRes = $adminStmt->get_result();
        while ($r = $adminRes->fetch_assoc()) {
            $admins[] = $r;
        }
        $adminStmt->close();
    }

    $bk = null;
    $bkStmt = $conn->prepare(
        'SELECT b.booking_id, b.booking_date, b.service_type, b.estimated_total_cost,
                u.name AS customer_name, u.email AS customer_email,
                e.equipment_name
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.customer_id
         LEFT JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = b.equipment_id COLLATE utf8mb4_unicode_ci
         WHERE b.booking_id = ? LIMIT 1'
    );
    if ($bkStmt) {
        $bkStmt->bind_param('i', $bookingId);
        $bkStmt->execute();
        $bkRes = $bkStmt->get_result();
        $bk = $bkRes ? $bkRes->fetch_assoc() : null;
        $bkStmt->close();
    }

    $custName = htmlspecialchars(trim((string)($bk['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($custName === '') {
        $custName = 'Customer';
    }
    $custEmail = htmlspecialchars(trim((string)($bk['customer_email'] ?? '')), ENT_QUOTES, 'UTF-8');
    $equip = htmlspecialchars(trim((string)($bk['equipment_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($equip === '') {
        $equip = '—';
    }
    $svcType = htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($bk['service_type'] ?? ''))), ENT_QUOTES, 'UTF-8');
    $bkDate = !empty($bk['booking_date']) ? htmlspecialchars(date('M j, Y', strtotime((string)$bk['booking_date'])), ENT_QUOTES, 'UTF-8') : '—';

    // App amounts are always whole Malawi Kwacha (MK).
    $amountLabel = 'MK ' . number_format($amountWhole);

    $base = '';
    if (defined('FES_PUBLIC_BASE_URL')) {
        $base = rtrim((string)FES_PUBLIC_BASE_URL, '/');
    }
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . '/FES';
    }
    $adminLink = $base . '/Pages/admin/booking-details.php?id=' . $bookingId;

    $safeBid = (int)$bookingId;

    try {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function ($str, $level) {
            error_log("PHPMailer Debug: $str");
        };
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = (int)$config['port'];
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->Timeout = 30;

        $mail->setFrom($config['from_email'], $config['from_name'] ?? 'FES');
        $added = [];
        if ($primaryNotify !== '' && filter_var($primaryNotify, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($primaryNotify, 'Admin');
            $added[strtolower($primaryNotify)] = true;
        }
        foreach ($admins as $a) {
            $em = trim((string)($a['email'] ?? ''));
            if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $key = strtolower($em);
            if (isset($added[$key])) {
                continue;
            }
            $mail->addAddress($em, $a['name'] ?? 'Admin');
            $added[$key] = true;
        }
        if ($added === []) {
            error_log('Payment admin notify: no valid admin addresses');
            return;
        }

        $mail->isHTML(true);
        $mail->Subject = 'Payment received: BK-' . $safeBid;
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; padding: 16px; background: #f8f9fa;'>
              <div style='background: #ffffff; padding: 22px; border-radius: 10px; border: 1px solid #eee;'>
                <h2 style='margin: 0 0 8px 0; color: #D32F2F;'>Payment received</h2>
                <p style='margin: 0 0 14px 0; color: #444;'>A customer completed online payment for a booking.</p>
                <div style='background: #f8f9fa; padding: 14px; border-radius: 8px;'>
                  <p style='margin: 0;'><b>Booking:</b> BK-{$safeBid}</p>
                  <p style='margin: 6px 0 0 0;'><b>Amount paid:</b> {$amountLabel}</p>
                  <p style='margin: 6px 0 0 0;'><b>Customer:</b> {$custName}" . ($custEmail !== '' ? " ({$custEmail})" : '') . "</p>
                  <p style='margin: 6px 0 0 0;'><b>Equipment:</b> {$equip}</p>
                  <p style='margin: 6px 0 0 0;'><b>Service date:</b> {$bkDate}</p>
                  <p style='margin: 6px 0 0 0;'><b>Service type:</b> {$svcType}</p>
                </div>
                <div style='margin-top: 16px; text-align: center;'>
                  <a href='" . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . "' style='display: inline-block; padding: 12px 18px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                    View booking
                  </a>
                </div>
              </div>
            </div>";
        $plainEmail = trim((string)($bk['customer_email'] ?? ''));
        $mail->AltBody =
            "Payment received\n\n" .
            "Booking: BK-{$safeBid}\n" .
            "Amount paid: {$amountLabel}\n" .
            'Customer: ' . trim((string)($bk['customer_name'] ?? 'Customer')) .
            ($plainEmail !== '' ? " ({$plainEmail})" : '') . "\n" .
            'Equipment: ' . trim((string)($bk['equipment_name'] ?? '')) . "\n" .
            "Service date: " . (!empty($bk['booking_date']) ? date('M j, Y', strtotime((string)$bk['booking_date'])) : '—') . "\n" .
            'Service type: ' . ucfirst(str_replace('_', ' ', (string)($bk['service_type'] ?? ''))) . "\n\n" .
            "View booking: {$adminLink}\n";

        $mail->send();
    } catch (MailException $e) {
        error_log('Payment admin notify PHPMailer: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('Payment admin notify: ' . $e->getMessage());
    }
}
