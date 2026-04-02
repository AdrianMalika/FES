<?php

require_once __DIR__ . '/fes_date.php';

/**
 * Minimal HTML for Dompdf (no external CSS/JS). Uses DejaVu Sans (bundled with Dompdf).
 *
 * @param array<string,mixed> $receipt Row from bookings (+ equipment_name)
 */
function fes_build_receipt_pdf_html(
    array $receipt,
    string $customerName,
    string $customerEmail,
    int $amountWhole,
    string $receiptNo,
    string $txRef
): string {
    $e = static function (string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $bid = (int)$receipt['booking_id'];
    $bkDate = fes_format_date_safe($receipt['booking_date'] ?? null, 'M d, Y', '—');
    $equip = (string)($receipt['equipment_name'] ?? '—');
    $svc = ucfirst(str_replace('_', ' ', (string)($receipt['service_type'] ?? '')));
    $paidAt = fes_format_date_safe($receipt['payment_paid_at'] ?? null, 'M d, Y · H:i', '—');
    $issued = date('M d, Y · H:i');
    $amountMk = 'MK ' . number_format($amountWhole);

    $txBlock = '';
    if ($txRef !== '') {
        $txBlock = '<tr><td colspan="2" style="padding-top:10px;border-top:1px solid #eee;">'
            . '<div class="lab">Reference</div>'
            . '<div style="font-size:10px;word-break:break-all;">' . $e($txRef) . '</div></td></tr>';
    }

    $emailRow = '';
    if ($customerEmail !== '') {
        $emailRow = '<div style="font-size:10px;color:#444;margin-top:2px;">' . $e($customerEmail) . '</div>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
h1 { font-size: 15px; margin: 0 0 2px 0; color: #111; }
.sub { font-size: 10px; color: #666; margin: 0 0 16px 0; }
.box { border: 1px solid #e5e5e5; border-radius: 8px; padding: 16px; }
.top { border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 12px; overflow: hidden; }
.amt { text-align: right; }
.lab { font-size: 8px; color: #666; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 3px; }
.val { font-weight: 600; color: #111; }
.big { font-size: 20px; font-weight: 700; color: #D32F2F; margin-top: 4px; }
td { vertical-align: top; padding: 6px 12px 6px 0; }
.foot { margin-top: 14px; padding-top: 10px; border-top: 1px solid #eee; font-size: 9px; color: #666; text-align: center; line-height: 1.4; }
</style></head><body>'
        . '<div class="box">'
        . '<div class="top">'
        . '<div style="float:left;width:55%;">'
        . '<h1>Payment receipt</h1>'
        . '<p class="sub">' . $e($receiptNo) . '</p>'
        . '</div>'
        . '<div class="amt" style="float:right;width:40%;">'
        . '<div class="lab">Amount paid</div>'
        . '<div class="big">' . $e($amountMk) . '</div>'
        . '</div><div style="clear:both;"></div>'
        . '</div>'
        . '<table width="100%" cellspacing="0" cellpadding="0">'
        . '<tr><td width="50%"><div class="lab">Booking</div><div class="val">#BK-' . $bid . '</div></td>'
        . '<td width="50%"><div class="lab">Service date</div><div class="val">' . $e($bkDate) . '</div></td></tr>'
        . '<tr><td><div class="lab">Equipment</div><div class="val">' . $e($equip) . '</div></td>'
        . '<td><div class="lab">Service type</div><div class="val">' . $e($svc) . '</div></td></tr>'
        . '<tr><td colspan="2"><div class="lab">Bill to</div><div class="val">' . $e($customerName) . '</div>' . $emailRow . '</td></tr>'
        . '<tr><td><div class="lab">Paid on</div><div class="val">' . $e($paidAt) . '</div></td>'
        . '<td><div class="lab">Payment method</div><div class="val">Card (Stripe)</div></td></tr>'
        . $txBlock
        . '</table>'
        . '<div class="foot">Issued ' . $e($issued) . ' · Malawi Kwacha (MK)<br>'
        . 'Farm Equipment Services — This receipt confirms payment for the booking shown above.</div>'
        . '</div></body></html>';
}
