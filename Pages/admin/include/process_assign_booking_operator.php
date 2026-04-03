<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../bookings.php');
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../bookings.php');
    exit();
}

require_once '../../../includes/database.php';
require_once '../../../includes/equipment_status_from_bookings.php';
require_once '../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$booking_id = (int)($_POST['booking_id'] ?? 0);
$operator_id_raw = trim((string)($_POST['operator_id'] ?? ''));
$operator_id = ($operator_id_raw === '') ? null : (int)$operator_id_raw;

if ($booking_id <= 0) {
    $_SESSION['error'] = 'Invalid booking selected.';
    header('Location: ../booking-details.php?id=' . $booking_id);
    exit();
}

try {
    $conn = getDBConnection();

    $bkStmt = $conn->prepare('SELECT booking_id, booking_date, service_type, equipment_id, status FROM bookings WHERE booking_id = ?');
    $bkStmt->bind_param('i', $booking_id);
    $bkStmt->execute();
    $bkRes = $bkStmt->get_result();
    $booking = $bkRes->fetch_assoc();
    $bkStmt->close();

    if (!$booking) {
        $_SESSION['error'] = 'Booking not found.';
        header('Location: ../booking-details.php?id=' . $booking_id);
        exit();
    }

    $eqStmt = $conn->prepare('SELECT category FROM equipment WHERE equipment_id = ?');
    $eqStmt->bind_param('s', $booking['equipment_id']);
    $eqStmt->execute();
    $eqRes = $eqStmt->get_result();
    $equipment = $eqRes->fetch_assoc();
    $eqStmt->close();

    if ($operator_id !== null) {
        $opStmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE user_id = ? AND role = 'operator'");
        $opStmt->bind_param('i', $operator_id);
        $opStmt->execute();
        $opRes = $opStmt->get_result();
        $operator = $opRes->fetch_assoc();
        $opStmt->close();

        if (!$operator) {
            $_SESSION['error'] = 'Selected operator is invalid.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }

        // Workload rule: operator cannot be assigned to another active booking on the same date.
        $busyStmt = $conn->prepare("SELECT booking_id FROM bookings WHERE operator_id = ? AND booking_date = ? AND status IN ('pending','confirmed','in_progress') AND booking_id <> ? LIMIT 1");
        $busyStmt->bind_param('isi', $operator_id, $booking['booking_date'], $booking_id);
        $busyStmt->execute();
        $busyRes = $busyStmt->get_result();
        $busy = $busyRes->fetch_assoc();
        $busyStmt->close();

        if ($busy) {
            $_SESSION['error'] = 'Cannot assign operator. This operator already has an active booking on the same date.';
            header('Location: ../booking-details.php?id=' . $booking_id);
            exit();
        }

        // Skills rule: if skills exist, operator must match equipment category or service type.
        $skillStmt = $conn->prepare('SELECT skill_name FROM operator_skills WHERE operator_id = ?');
        $skillStmt->bind_param('i', $operator_id);
        $skillStmt->execute();
        $skillRes = $skillStmt->get_result();
        $skills = [];
        while ($row = $skillRes->fetch_assoc()) {
            $skills[] = strtolower($row['skill_name']);
        }
        $skillStmt->close();

        if (!empty($skills)) {
            $category = strtolower($equipment['category'] ?? '');
            $serviceType = strtolower($booking['service_type'] ?? '');
            $matched = false;
            foreach ($skills as $skill) {
                if (($category !== '' && strpos($skill, $category) !== false) ||
                    ($serviceType !== '' && strpos($skill, $serviceType) !== false)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $_SESSION['error'] = 'Cannot assign operator. Operator skills do not match this booking.';
                header('Location: ../booking-details.php?id=' . $booking_id);
                exit();
            }
        }
    }

    if ($operator_id === null) {
        $updStmt = $conn->prepare('UPDATE bookings SET operator_id = NULL, updated_at = NOW() WHERE booking_id = ?');
        $updStmt->bind_param('i', $booking_id);
    } else {
        $updStmt = $conn->prepare('UPDATE bookings SET operator_id = ?, updated_at = NOW() WHERE booking_id = ?');
        $updStmt->bind_param('ii', $operator_id, $booking_id);
    }

    if ($updStmt->execute()) {
        $_SESSION['success'] = 'Operator assignment updated successfully.';

        // Recalculate equipment status based on booking statuses for this equipment.
        try {
            $equipmentId = (string)($booking['equipment_id'] ?? '');
            recalculate_equipment_status_from_bookings($conn, $equipmentId);
        } catch (Exception $e) {
            error_log('Equipment status update error: ' . $e->getMessage());
        }

        // Email notify operator when assigned
        if ($operator_id !== null && !empty($operator['email'])) {
            try {
                $config = include '../../../includes/email_config.php';
                if (is_array($config) && !empty($config['host']) && !empty($config['username'])) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $jobLink = $scheme . '://' . $host . '/FES/Pages/operator/job_details.php?id=' . urlencode((string)$booking_id);

                    $mail = new PHPMailer(true);
                    $mail->SMTPDebug = 0;
                    $mail->Debugoutput = function ($str, $level) {
                        error_log("PHPMailer Debug: $str");
                    };
                    $mail->isSMTP();
                    $mail->Host       = $config['host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config['username'];
                    $mail->Password   = $config['password'];
                    $mail->SMTPSecure = $config['encryption'];
                    $mail->Port       = $config['port'];
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];
                    $mail->Timeout = 30;

                    $mail->setFrom($config['from_email'], $config['from_name']);
                    $mail->addAddress($operator['email'], $operator['name'] ?? 'Operator');
                    $mail->isHTML(true);

                    $safeName = htmlspecialchars((string)($operator['name'] ?? 'Operator'), ENT_QUOTES, 'UTF-8');
                    $safeDate = htmlspecialchars((string)($booking['booking_date'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $safeEquip = htmlspecialchars((string)($booking['equipment_id'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $safeService = htmlspecialchars((string)($booking['service_type'] ?? ''), ENT_QUOTES, 'UTF-8');

                    $mail->Subject = 'New job assigned: BK-' . (string)$booking_id;
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 640px; margin: 0 auto; padding: 16px; background: #f8f9fa;'>
                          <div style='background: #ffffff; padding: 22px; border-radius: 10px; border: 1px solid #eee;'>
                            <h2 style='margin: 0 0 8px 0; color: #D32F2F;'>New job assigned</h2>
                            <p style='margin: 0 0 14px 0; color: #444;'>Hello {$safeName}, you have been assigned a new booking/job.</p>
                            <div style='background: #f8f9fa; padding: 14px; border-radius: 8px;'>
                              <p style='margin: 0;'><b>Booking ID:</b> BK-{$booking_id}</p>
                              <p style='margin: 6px 0 0 0;'><b>Date:</b> {$safeDate}</p>
                              <p style='margin: 6px 0 0 0;'><b>Equipment:</b> {$safeEquip}</p>
                              <p style='margin: 6px 0 0 0;'><b>Service:</b> {$safeService}</p>
                            </div>
                            <div style='margin-top: 16px; text-align: center;'>
                              <a href='{$jobLink}' style='display: inline-block; padding: 12px 18px; background: #D32F2F; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;'>
                                View job details
                              </a>
                            </div>
                          </div>
                        </div>
                    ";

                    $mail->AltBody =
                        "New job assigned\n\n" .
                        "Booking ID: BK-{$booking_id}\n" .
                        "Date: " . ($booking['booking_date'] ?? '') . "\n" .
                        "Equipment: " . ($booking['equipment_id'] ?? '') . "\n" .
                        "Service: " . ($booking['service_type'] ?? '') . "\n\n" .
                        "View job: {$jobLink}\n";

                    $mail->send();
                }
            } catch (MailException $e) {
                error_log('Operator assignment email failed: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log('Operator assignment email error: ' . $e->getMessage());
            }
        }
    } else {
        $_SESSION['error'] = 'Failed to update operator assignment.';
    }
    $updStmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log('Operator assignment error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unexpected error while updating assignment.';
}

header('Location: ../booking-details.php?id=' . $booking_id);
exit();
?>

