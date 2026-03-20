<?php
/**
 * Set equipment.status from all bookings linked to this equipment_id.
 * Mirrors logic used when assigning operators and when operators update job status.
 *
 * Rules:
 * - Any booking in_progress for this equipment => equipment in_use
 * - Else any pending or confirmed => equipment retired (reserved/upcoming in this app)
 * - Else => equipment available
 *
 * @param mysqli $conn        Active DB connection
 * @param string $equipmentId equipment.equipment_id (varchar)
 */
function recalculate_equipment_status_from_bookings(mysqli $conn, string $equipmentId): void
{
    if ($equipmentId === '') {
        return;
    }

    $inProgressCount = 0;
    $pendingConfirmedCount = 0;

    $inStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE equipment_id = ? AND status = 'in_progress'");
    if ($inStmt) {
        $inStmt->bind_param('s', $equipmentId);
        $inStmt->execute();
        $res = $inStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $inProgressCount = (int)($row['cnt'] ?? 0);
        $inStmt->close();
    }

    $pcStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE equipment_id = ? AND status IN ('pending','confirmed')");
    if ($pcStmt) {
        $pcStmt->bind_param('s', $equipmentId);
        $pcStmt->execute();
        $res = $pcStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $pendingConfirmedCount = (int)($row['cnt'] ?? 0);
        $pcStmt->close();
    }

    if ($inProgressCount > 0) {
        $newStatus = 'in_use';
    } elseif ($pendingConfirmedCount > 0) {
        $newStatus = 'retired';
    } else {
        $newStatus = 'available';
    }

    $eqUpd = $conn->prepare('UPDATE equipment SET status = ?, updated_at = NOW() WHERE equipment_id = ?');
    if ($eqUpd) {
        $eqUpd->bind_param('ss', $newStatus, $equipmentId);
        $eqUpd->execute();
        $eqUpd->close();
    }
}
