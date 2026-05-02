<?php
declare(strict_types=1);

/**
 * FES Operational Email Notifications
 * 
 * Centralized email functions for operational events:
 * - Damage report submitted (to admin)
 * - Job completed (to admin)
 * 
 * Admin recipient: adrianmalika01@gmail.com (always included)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Send email to admin when operator submits a damage report.
 *
 * @param mysqli $conn Database connection
 * @param int $damageReportId Damage report ID
 * @param int $bookingId Booking ID
 * @param int $operatorId Operator who submitted the report
 * @param string $severity Damage severity (minor/major/critical)
 * @param string $description Damage description
 */
function fes_send_damage_report_email(
    mysqli $conn,
    int $damageReportId,
    int $bookingId,
    int $operatorId,
    string $severity,
    string $description
): void {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        error_log('Damage report email: vendor/autoload.php missing');
        return;
    }
    require_once $autoload;

    $configPath = __DIR__ . '/email_config.php';
    if (!is_readable($configPath)) {
        error_log('Damage report email: email_config.php missing');
        return;
    }
    $config = include $configPath;
    if (!is_array($config) || empty($config['host']) || empty($config['username']) || empty($config['from_email'])) {
        error_log('Damage report email: invalid email_config.php');
        return;
    }

    $adminEmail = 'adrianmalika01@gmail.com';

    // Fetch booking details
    $bk = null;
    $bkStmt = $conn->prepare(
        'SELECT b.booking_id, b.equipment_id,
                u.name AS operator_name, u.email AS operator_email,
                e.equipment_name
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.operator_id
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

    $operatorName = htmlspecialchars(trim((string)($bk['operator_name'] ?? 'Operator')), ENT_QUOTES, 'UTF-8');
    $equipName = htmlspecialchars(trim((string)($bk['equipment_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($equipName === '') {
        $equipName = htmlspecialchars((string)($bk['equipment_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    }
    $severityUpper = strtoupper(htmlspecialchars($severity, ENT_QUOTES, 'UTF-8'));
    $safeDesc = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
    $safeDescPlain = $description;

    // Severity color
    $severityColor = '#f59e0b'; // amber
    $severityIcon = '⚠️';
    if ($severity === 'major') {
        $severityColor = '#ef4444'; // red
        $severityIcon = '🔴';
    } elseif ($severity === 'critical') {
        $severityColor = '#991b1b'; // dark red
        $severityIcon = '🚨';
    }

    // Build URL
    $base = '';
    if (defined('FES_PUBLIC_BASE_URL')) {
        $base = rtrim((string)FES_PUBLIC_BASE_URL, '/');
    }
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . '/FES';
    }
    $damageUrl = $base . '/Pages/admin/damage_reports.php';
    $bookingUrl = $base . '/Pages/admin/booking-details.php?id=' . $bookingId;

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

        $mail->setFrom($config['from_email'], $config['from_name'] ?? 'FES System');
        $mail->addAddress($adminEmail, 'Admin');

        $mail->isHTML(true);
        $mail->Subject = "[FES] Damage Report #{$damageReportId} - {$severityUpper} severity";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; padding: 16px; background: #f8f9fa;'>
              <div style='background: #ffffff; padding: 22px; border-radius: 10px; border: 1px solid #eee;'>
                <h2 style='margin: 0 0 8px 0; color: #D32F2F;'>{$severityIcon} Damage Report Submitted</h2>
                <p style='margin: 0 0 14px 0; color: #444;'>An operator has submitted a damage report that requires your review.</p>
                <div style='background: #f8f9fa; padding: 14px; border-radius: 8px;'>
                  <p style='margin: 0;'><b>Report ID:</b> #{$damageReportId}</p>
                  <p style='margin: 6px 0 0 0;'><b>Booking:</b> BK-{$bookingId}</p>
                  <p style='margin: 6px 0 0 0;'><b>Equipment:</b> {$equipName}</p>
                  <p style='margin: 6px 0 0 0;'><b>Reported By:</b> {$operatorName}</p>
                  <p style='margin: 6px 0 0 0;'><b>Severity:</b> <span style='color: {$severityColor}; font-weight: bold;'>{$severityUpper}</span></p>
                </div>
                <div style='margin-top: 14px; padding: 12px; background: #fff3cd; border-left: 4px solid {$severityColor}; border-radius: 4px;'>
                  <p style='margin: 0; font-weight: bold; color: #856404;'>Description:</p>
                  <p style='margin: 6px 0 0 0; color: #856404;'>{$safeDesc}</p>
                </div>
                <div style='margin-top: 16px; text-align: center;'>
                  <a href='" . htmlspecialchars($damageUrl, ENT_QUOTES, 'UTF-8') . "' style='display: inline-block; padding: 12px 18px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 8px;'>
                    View Damage Reports
                  </a>
                  <a href='" . htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') . "' style='display: inline-block; padding: 12px 18px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                    View Booking
                  </a>
                </div>
              </div>
            </div>";
        
        $mail->AltBody =
            "Damage Report Submitted\n\n" .
            "Report ID: #{$damageReportId}\n" .
            "Booking: BK-{$bookingId}\n" .
            "Equipment: " . trim((string)($bk['equipment_name'] ?? $bk['equipment_id'] ?? '')) . "\n" .
            "Reported By: " . trim((string)($bk['operator_name'] ?? 'Operator')) . "\n" .
            "Severity: {$severityUpper}\n\n" .
            "Description:\n{$safeDescPlain}\n\n" .
            "View damage reports: {$damageUrl}\n" .
            "View booking: {$bookingUrl}\n";

        $mail->send();
    } catch (MailException $e) {
        error_log('Damage report email PHPMailer: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('Damage report email: ' . $e->getMessage());
    }
}

/**
 * Send email to admin when operator marks a job as completed.
 *
 * @param mysqli $conn Database connection
 * @param int $bookingId Booking ID
 * @param int $operatorId Operator who completed the job
 */
function fes_send_job_completed_email(
    mysqli $conn,
    int $bookingId,
    int $operatorId
): void {
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        error_log('Job completed email: vendor/autoload.php missing');
        return;
    }
    require_once $autoload;

    $configPath = __DIR__ . '/email_config.php';
    if (!is_readable($configPath)) {
        error_log('Job completed email: email_config.php missing');
        return;
    }
    $config = include $configPath;
    if (!is_array($config) || empty($config['host']) || empty($config['username']) || empty($config['from_email'])) {
        error_log('Job completed email: invalid email_config.php');
        return;
    }

    $adminEmail = 'adrianmalika01@gmail.com';

    // Fetch booking details
    $bk = null;
    $bkStmt = $conn->prepare(
        'SELECT b.booking_id, b.equipment_id, b.service_type, b.booking_date, b.operator_start_time, b.operator_end_time,
                u.name AS operator_name, u.email AS operator_email,
                c.name AS customer_name, c.email AS customer_email, c.phone_number AS customer_phone,
                e.equipment_name
         FROM bookings b
         LEFT JOIN users u ON u.user_id = b.operator_id
         LEFT JOIN customers c ON c.customer_id = b.customer_id
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

    $operatorName = htmlspecialchars(trim((string)($bk['operator_name'] ?? 'Operator')), ENT_QUOTES, 'UTF-8');
    $customerName = htmlspecialchars(trim((string)($bk['customer_name'] ?? 'Customer')), ENT_QUOTES, 'UTF-8');
    $equipName = htmlspecialchars(trim((string)($bk['equipment_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    if ($equipName === '') {
        $equipName = htmlspecialchars((string)($bk['equipment_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    }
    $serviceType = htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($bk['service_type'] ?? ''))), ENT_QUOTES, 'UTF-8');
    $serviceDate = !empty($bk['booking_date']) ? htmlspecialchars(date('M j, Y', strtotime((string)$bk['booking_date'])), ENT_QUOTES, 'UTF-8') : '—';

    // Calculate duration
    $duration = 'N/A';
    if (!empty($bk['operator_start_time']) && !empty($bk['operator_end_time'])) {
        $start = new DateTime($bk['operator_start_time']);
        $end = new DateTime($bk['operator_end_time']);
        $diff = $start->diff($end);
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;
        $duration = sprintf('%d hours %d minutes', $hours, $minutes);
    }

    $startTime = !empty($bk['operator_start_time']) ? htmlspecialchars(date('M j, Y H:i', strtotime((string)$bk['operator_start_time'])), ENT_QUOTES, 'UTF-8') : '—';
    $endTime = !empty($bk['operator_end_time']) ? htmlspecialchars(date('M j, Y H:i', strtotime((string)$bk['operator_end_time'])), ENT_QUOTES, 'UTF-8') : '—';

    // Build URL
    $base = '';
    if (defined('FES_PUBLIC_BASE_URL')) {
        $base = rtrim((string)FES_PUBLIC_BASE_URL, '/');
    }
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host . '/FES';
    }
    $bookingUrl = $base . '/Pages/admin/booking-details.php?id=' . $bookingId;

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

        $mail->setFrom($config['from_email'], $config['from_name'] ?? 'FES System');
        $mail->addAddress($adminEmail, 'Admin');

        $mail->isHTML(true);
        $mail->Subject = "[FES] Job Completed - BK-{$bookingId}";
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; padding: 16px; background: #f8f9fa;'>
              <div style='background: #ffffff; padding: 22px; border-radius: 10px; border: 1px solid #eee;'>
                <h2 style='margin: 0 0 8px 0; color: #10b981;'>✅ Job Completed</h2>
                <p style='margin: 0 0 14px 0; color: #444;'>An operator has marked a booking as completed.</p>
                <div style='background: #f8f9fa; padding: 14px; border-radius: 8px;'>
                  <p style='margin: 0;'><b>Booking:</b> BK-{$bookingId}</p>
                  <p style='margin: 6px 0 0 0;'><b>Customer:</b> {$customerName}</p>
                  <p style='margin: 6px 0 0 0;'><b>Operator:</b> {$operatorName}</p>
                  <p style='margin: 6px 0 0 0;'><b>Equipment:</b> {$equipName}</p>
                  <p style='margin: 6px 0 0 0;'><b>Service Type:</b> {$serviceType}</p>
                  <p style='margin: 6px 0 0 0;'><b>Service Date:</b> {$serviceDate}</p>
                </div>
                <div style='margin-top: 14px; padding: 12px; background: #e8f5e9; border-left: 4px solid #10b981; border-radius: 4px;'>
                  <p style='margin: 0; font-weight: bold; color: #2e7d32;'>Work Duration:</p>
                  <p style='margin: 6px 0 0 0; color: #2e7d32;'><b>{$duration}</b></p>
                  <p style='margin: 6px 0 0 0; font-size: 13px; color: #2e7d32;'>Started: {$startTime}</p>
                  <p style='margin: 4px 0 0 0; font-size: 13px; color: #2e7d32;'>Completed: {$endTime}</p>
                </div>
                <div style='margin-top: 16px; padding: 12px; background: #fff3cd; border-radius: 8px;'>
                  <p style='margin: 0; font-weight: bold; color: #856404;'>Next Steps:</p>
                  <ul style='margin: 8px 0 0 0; padding-left: 20px; color: #856404;'>
                    <li>Verify equipment condition</li>
                    <li>Check for customer feedback</li>
                    <li>Process final invoice if needed</li>
                    <li>Update equipment status</li>
                  </ul>
                </div>
                <div style='margin-top: 16px; text-align: center;'>
                  <a href='" . htmlspecialchars($bookingUrl, ENT_QUOTES, 'UTF-8') . "' style='display: inline-block; padding: 12px 18px; background: #10b981; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                    View Booking Details
                  </a>
                </div>
              </div>
            </div>";
        
        $mail->AltBody =
            "Job Completed\n\n" .
            "Booking: BK-{$bookingId}\n" .
            "Customer: " . trim((string)($bk['customer_name'] ?? 'Customer')) . "\n" .
            "Operator: " . trim((string)($bk['operator_name'] ?? 'Operator')) . "\n" .
            "Equipment: " . trim((string)($bk['equipment_name'] ?? $bk['equipment_id'] ?? '')) . "\n" .
            "Service Type: " . ucfirst(str_replace('_', ' ', (string)($bk['service_type'] ?? ''))) . "\n" .
            "Service Date: " . (!empty($bk['booking_date']) ? date('M j, Y', strtotime((string)$bk['booking_date'])) : '—') . "\n\n" .
            "Work Duration: {$duration}\n" .
            "Started: {$startTime}\n" .
            "Completed: {$endTime}\n\n" .
            "View booking: {$bookingUrl}\n";

        $mail->send();
    } catch (MailException $e) {
        error_log('Job completed email PHPMailer: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('Job completed email: ' . $e->getMessage());
    }
}
