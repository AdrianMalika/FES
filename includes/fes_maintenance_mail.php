<?php
declare(strict_types=1);

/**
 * Daily digest to the maintenance contact when items are due soon or overdue.
 * Sends at most once per calendar day (first admin visit to maintenance.php that finds issues).
 */

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function fes_maintenance_notify_email(): string
{
    return 'adrianmalika01@gmail.com';
}

function fes_try_create_maintenance_notify_table(mysqli $conn): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `fes_maintenance_email_sent` (
  `sent_date` DATE NOT NULL,
  PRIMARY KEY (`sent_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    if (!$conn->query($sql)) {
        throw new RuntimeException($conn->error ?: 'CREATE TABLE fes_maintenance_email_sent failed');
    }
}

/**
 * @return list<array<string,mixed>>
 */
function fes_maintenance_fetch_almost_due_jobs(mysqli $conn): array
{
    $sql = "SELECT m.maintenance_id, m.equipment_id, e.equipment_name, m.maintenance_type, m.scheduled_date, m.status
            FROM equipment_maintenance m
            INNER JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = m.equipment_id COLLATE utf8mb4_unicode_ci
            WHERE m.status IN ('scheduled','in_progress')
              AND m.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY m.scheduled_date ASC, m.maintenance_id ASC";
    $out = [];
    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException($conn->error ?: 'almost due jobs query failed');
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $res->close();
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function fes_maintenance_fetch_overdue_jobs(mysqli $conn): array
{
    $sql = "SELECT m.maintenance_id, m.equipment_id, e.equipment_name, m.maintenance_type, m.scheduled_date, m.status
            FROM equipment_maintenance m
            INNER JOIN equipment e ON e.equipment_id COLLATE utf8mb4_unicode_ci = m.equipment_id COLLATE utf8mb4_unicode_ci
            WHERE m.status IN ('scheduled','in_progress')
              AND m.scheduled_date < CURDATE()
            ORDER BY m.scheduled_date ASC, m.maintenance_id ASC";
    $out = [];
    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException($conn->error ?: 'overdue jobs query failed');
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $res->close();
    return $out;
}

/**
 * Equipment approaching 90-day service window (76–90 days since last service).
 *
 * @return list<array<string,mixed>>
 */
function fes_maintenance_fetch_almost_due_equipment(mysqli $conn): array
{
    $sql = "SELECT equipment_id, equipment_name, last_maintenance,
                   DATEDIFF(CURDATE(), last_maintenance) AS days_since
            FROM equipment
            WHERE status <> 'retired'
              AND last_maintenance IS NOT NULL
              AND DATEDIFF(CURDATE(), last_maintenance) BETWEEN 76 AND 90
            ORDER BY days_since DESC, equipment_name ASC";
    $out = [];
    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException($conn->error ?: 'almost due equipment query failed');
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $res->close();
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function fes_maintenance_fetch_overdue_equipment(mysqli $conn): array
{
    $sql = "SELECT equipment_id, equipment_name, last_maintenance,
                   CASE WHEN last_maintenance IS NULL THEN NULL ELSE DATEDIFF(CURDATE(), last_maintenance) END AS days_since
            FROM equipment
            WHERE status <> 'retired'
              AND (
                last_maintenance IS NULL
                OR DATEDIFF(CURDATE(), last_maintenance) > 90
              )
            ORDER BY (last_maintenance IS NULL) DESC, equipment_name ASC";
    $out = [];
    $res = $conn->query($sql);
    if (!$res) {
        throw new RuntimeException($conn->error ?: 'overdue equipment query failed');
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $res->close();
    return $out;
}

function fes_maintenance_digest_already_sent_today(mysqli $conn, string $todayYmd): bool
{
    $st = $conn->prepare('SELECT 1 FROM fes_maintenance_email_sent WHERE sent_date = ? LIMIT 1');
    if (!$st) {
        throw new RuntimeException($conn->error);
    }
    $st->bind_param('s', $todayYmd);
    $st->execute();
    $ok = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $ok;
}

function fes_maintenance_mark_digest_sent(mysqli $conn, string $todayYmd): void
{
    $st = $conn->prepare('INSERT IGNORE INTO fes_maintenance_email_sent (sent_date) VALUES (?)');
    if (!$st) {
        throw new RuntimeException($conn->error);
    }
    $st->bind_param('s', $todayYmd);
    $st->execute();
    $st->close();
}

/**
 * @param list<array<string,mixed>> $rows
 */
function fes_maintenance_html_job_rows(array $rows): string
{
    if ($rows === []) {
        return '<p style="color:#666;">None.</p>';
    }
    $html = '<table style="width:100%;border-collapse:collapse;font-size:14px;"><tr style="background:#f5f5f5;text-align:left;"><th style="padding:8px;border:1px solid #ddd;">Equipment</th><th style="padding:8px;border:1px solid #ddd;">Type</th><th style="padding:8px;border:1px solid #ddd;">Scheduled</th><th style="padding:8px;border:1px solid #ddd;">Status</th></tr>';
    foreach ($rows as $r) {
        $name = htmlspecialchars((string)($r['equipment_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $eid = htmlspecialchars((string)($r['equipment_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars((string)($r['maintenance_type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $sd = htmlspecialchars((string)($r['scheduled_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $st = htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;">' . $name . '<br><span style="color:#888;font-size:12px;">' . $eid . '</span></td><td style="padding:8px;border:1px solid #ddd;">' . $type . '</td><td style="padding:8px;border:1px solid #ddd;">' . $sd . '</td><td style="padding:8px;border:1px solid #ddd;">' . $st . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * @param list<array<string,mixed>> $rows
 */
function fes_maintenance_html_equip_rows(array $rows, bool $overdue): string
{
    if ($rows === []) {
        return '<p style="color:#666;">None.</p>';
    }
    $html = '<table style="width:100%;border-collapse:collapse;font-size:14px;"><tr style="background:#f5f5f5;text-align:left;"><th style="padding:8px;border:1px solid #ddd;">Equipment</th><th style="padding:8px;border:1px solid #ddd;">Last serviced</th><th style="padding:8px;border:1px solid #ddd;">Days</th></tr>';
    foreach ($rows as $r) {
        $name = htmlspecialchars((string)($r['equipment_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $eid = htmlspecialchars((string)($r['equipment_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lm = $r['last_maintenance'] !== null && (string)$r['last_maintenance'] !== ''
            ? htmlspecialchars((string)$r['last_maintenance'], ENT_QUOTES, 'UTF-8')
            : '—';
        if ($overdue && ($r['last_maintenance'] === null || trim((string)$r['last_maintenance']) === '')) {
            $days = 'Never';
        } elseif ($r['days_since'] !== null && $r['days_since'] !== '') {
            $days = htmlspecialchars((string)(int)$r['days_since'], ENT_QUOTES, 'UTF-8');
        } else {
            $days = '—';
        }
        $html .= '<tr><td style="padding:8px;border:1px solid #ddd;">' . $name . '<br><span style="color:#888;font-size:12px;">' . $eid . '</span></td><td style="padding:8px;border:1px solid #ddd;">' . $lm . '</td><td style="padding:8px;border:1px solid #ddd;">' . $days . '</td></tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * Sends one combined email per day when there is anything to report.
 */
function fes_maintenance_send_daily_digest_if_needed(mysqli $conn): void
{
    $todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');

    if (fes_maintenance_digest_already_sent_today($conn, $todayYmd)) {
        return;
    }

    $jobsSoon = fes_maintenance_fetch_almost_due_jobs($conn);
    $jobsOver = fes_maintenance_fetch_overdue_jobs($conn);
    $equipSoon = fes_maintenance_fetch_almost_due_equipment($conn);
    $equipOver = fes_maintenance_fetch_overdue_equipment($conn);

    if ($jobsSoon === [] && $jobsOver === [] && $equipSoon === [] && $equipOver === []) {
        return;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    $cfgPath = __DIR__ . '/email_config.php';
    if (!is_readable($autoload) || !is_readable($cfgPath)) {
        error_log('Maintenance digest: vendor/autoload or email_config missing');
        return;
    }

    /** @var array<string,mixed> $config */
    $config = include $cfgPath;
    if (!is_array($config) || empty($config['host']) || empty($config['username'])) {
        error_log('Maintenance digest: invalid email_config');
        return;
    }

    $to = fes_maintenance_notify_email();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/Pages/admin/maintenance.php');
    $adminDir = dirname($script);
    $pagesDir = dirname($adminDir);
    $basePath = dirname($pagesDir);
    $maintUrl = $scheme . '://' . $host . rtrim($basePath, '/') . '/Pages/admin/maintenance.php';

    $safeUrl = htmlspecialchars($maintUrl, ENT_QUOTES, 'UTF-8');
    $safeTo = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');

    $body = '<div style="font-family:Arial,sans-serif;max-width:720px;margin:0 auto;padding:16px;background:#f8f9fa;">'
        . '<div style="background:#fff;padding:24px;border-radius:8px;border:1px solid #eee;">'
        . '<h1 style="color:#D32F2F;margin:0 0 8px 0;font-size:22px;">FES equipment maintenance</h1>'
        . '<p style="color:#444;margin:0 0 20px 0;">Hello, this message is for <strong>' . $safeTo . '</strong> — your Farming &amp; Engineering Services maintenance summary.</p>'

        . '<h2 style="color:#E65100;font-size:16px;margin:24px 0 8px 0;">Due soon (next 7 days)</h2>'
        . '<p style="color:#666;font-size:13px;margin:0 0 8px 0;">Scheduled or in-progress jobs with a date from today through the next 7 days.</p>'
        . fes_maintenance_html_job_rows($jobsSoon)

        . '<h2 style="color:#C62828;font-size:16px;margin:24px 0 8px 0;">Overdue jobs</h2>'
        . '<p style="color:#666;font-size:13px;margin:0 0 8px 0;">Scheduled or in-progress work whose scheduled date has passed.</p>'
        . fes_maintenance_html_job_rows($jobsOver)

        . '<h2 style="color:#E65100;font-size:16px;margin:24px 0 8px 0;">Equipment — service due soon (76–90 days)</h2>'
        . '<p style="color:#666;font-size:13px;margin:0 0 8px 0;">Machines that have not been serviced in 76–90 days (approaching the 90-day guideline).</p>'
        . fes_maintenance_html_equip_rows($equipSoon, false)

        . '<h2 style="color:#C62828;font-size:16px;margin:24px 0 8px 0;">Equipment — overdue for service</h2>'
        . '<p style="color:#666;font-size:13px;margin:0 0 8px 0;">No recorded service in 90+ days, or never serviced (excluding retired).</p>'
        . fes_maintenance_html_equip_rows($equipOver, true)

        . '<p style="margin-top:24px;font-size:14px;"><a href="' . $safeUrl . '" style="color:#D32F2F;font-weight:bold;">Open maintenance in FES admin</a></p>'
        . '</div></div>';

    $plain = "FES equipment maintenance (for {$to})\n\n"
        . "DUE SOON JOBS (next 7 days): " . count($jobsSoon) . " row(s)\n"
        . "OVERDUE JOBS: " . count($jobsOver) . " row(s)\n"
        . "EQUIPMENT DUE SOON (76-90 days): " . count($equipSoon) . " row(s)\n"
        . "EQUIPMENT OVERDUE: " . count($equipOver) . " row(s)\n\n"
        . "Admin: {$maintUrl}\n";

    require_once $autoload;

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->Debugoutput = static function (string $str, int $level): void {
        error_log('PHPMailer maintenance digest: ' . $str);
    };
    $mail->isSMTP();
    $mail->Host = (string)$config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$config['username'];
    $mail->Password = (string)($config['password'] ?? '');
    $mail->SMTPSecure = (string)($config['encryption'] ?? 'ssl');
    $mail->Port = (int)($config['port'] ?? 465);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->Timeout = 30;
    $mail->setFrom((string)$config['from_email'], (string)($config['from_name'] ?? 'FES System'));
    $mail->addAddress($to, 'Maintenance contact');
    $mail->isHTML(true);
    $mail->Subject = '[FES] Maintenance: due soon & overdue — ' . $todayYmd;
    $mail->Body = $body;
    $mail->AltBody = $plain;

    try {
        $mail->send();
    } catch (MailException $e) {
        error_log('Maintenance digest PHPMailer: ' . $e->getMessage());
        throw $e;
    }

    fes_maintenance_mark_digest_sent($conn, $todayYmd);
}
