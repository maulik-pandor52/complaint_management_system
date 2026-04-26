<?php
/**
 * SLA escalation engine.
 * Auto-marks overdue complaints as "Escalated" and logs the change in complaint_history.
 */

require_once __DIR__ . "/app_helper.php";
require_once __DIR__ . "/status_lookup.php";
require_once __DIR__ . "/sla_report_helper.php";

/**
 * Escalate overdue complaints.
 * Safe to call on every dashboard page load (it only updates when needed).
 */
function run_sla_escalation(mysqli $conn): void
{
    $ID_PENDING   = get_status_id_or($conn, "Pending", 1);
    $ID_RESOLVED  = get_status_id_or($conn, "Resolved", 3);
    $ID_CLOSED    = get_status_id_or($conn, "Closed", 4);
    $ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);

    // If critical statuses are missing from both DB and fallbacks, we must abort
    if ($ID_ESCALATED === null) {
        error_log("SLA Escalation Error: 'Escalated' status could not be found or verified.");
        return;
    }

    _sync_sla_deadlines($conn);

    $rows = get_live_sla_report_rows($conn);
    foreach ($rows as $row) {
        $cid = (int)$row['complaint_id'];
        $shouldEscalate = !empty($row['is_escalated']);
        $currentStatusId = (int)$row['status_id'];

        if ($shouldEscalate) {
            $reason = "Auto escalation: Resolution SLA breached";
            _escalate_one($conn, $cid, $ID_ESCALATED, $reason);
        } elseif ($currentStatusId === (int)$ID_ESCALATED) {
            _restore_false_escalation($conn, $cid, $ID_ESCALATED);
        }
    }
}

function _sync_sla_deadlines(mysqli $conn): void
{
    $sql = "
        UPDATE complaints
        SET
            initial_sla_due = DATE_ADD(created_at, INTERVAL 6 HOUR),
            resolution_sla_due = DATE_ADD(created_at, INTERVAL 30 HOUR)
        WHERE
            initial_sla_due IS NULL
            OR resolution_sla_due IS NULL
            OR initial_sla_due <> DATE_ADD(created_at, INTERVAL 6 HOUR)
            OR resolution_sla_due <> DATE_ADD(created_at, INTERVAL 30 HOUR)
    ";
    $conn->query($sql);
}

/**
 * Update complaint status to escalated if not already escalated, and insert history.
 */
function _escalate_one(mysqli $conn, int $complaint_id, ?int $escalated_status_id, string $remark): void
{
    if ($escalated_status_id === null) return;
    // Check current status (avoid double history spam)
    $stmt = $conn->prepare("SELECT status_id FROM complaints WHERE complaint_id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) return;

    if ((int)$row['status_id'] === (int)$escalated_status_id) {
        return;
    }

    // Update status
    $up = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
    if (!$up) return;
    $up->bind_param("ii", $escalated_status_id, $complaint_id);
    $ok = $up->execute();
    $up->close();

    if (!$ok) return;

    // History: updated_by = NULL means "System" (avoids FK violation if 0 user doesn't exist)
    $hist = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, NULL, ?)");
    if (!$hist) return;
    $hist->bind_param("iis", $complaint_id, $escalated_status_id, $remark);
    $hist->execute();
    $hist->close();
}

function _restore_false_escalation(mysqli $conn, int $complaint_id, int $escalated_status_id): void
{
    $latestEscalation = $conn->prepare("
        SELECT history_id, remark
        FROM complaint_history
        WHERE complaint_id = ? AND status_id = ?
        ORDER BY updated_at DESC, history_id DESC
        LIMIT 1
    ");
    if (!$latestEscalation) {
        return;
    }

    $latestEscalation->bind_param("ii", $complaint_id, $escalated_status_id);
    $latestEscalation->execute();
    $latestRow = $latestEscalation->get_result()->fetch_assoc();
    $latestEscalation->close();

    if (!$latestRow || stripos((string)$latestRow['remark'], 'Auto escalation:') !== 0) {
        return;
    }

    $previousStatusStmt = $conn->prepare("
        SELECT status_id
        FROM complaint_history
        WHERE complaint_id = ? AND status_id <> ?
        ORDER BY updated_at DESC, history_id DESC
        LIMIT 1
    ");
    if (!$previousStatusStmt) {
        return;
    }

    $previousStatusStmt->bind_param("ii", $complaint_id, $escalated_status_id);
    $previousStatusStmt->execute();
    $previousRow = $previousStatusStmt->get_result()->fetch_assoc();
    $previousStatusStmt->close();

    if (!$previousRow) {
        return;
    }

    $previousStatusId = (int)$previousRow['status_id'];
    $update = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
    if (!$update) {
        return;
    }
    $update->bind_param("ii", $previousStatusId, $complaint_id);
    $ok = $update->execute();
    $update->close();

    if (!$ok) {
        return;
    }

    $history = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, NULL, ?)");
    if (!$history) {
        return;
    }
    $remark = "Auto correction: complaint restored after SLA recalculation";
    $history->bind_param("iis", $complaint_id, $previousStatusId, $remark);
    $history->execute();
    $history->close();
}
