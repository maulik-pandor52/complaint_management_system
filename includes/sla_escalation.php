<?php
/**
 * SLA escalation engine.
 * Auto-marks overdue complaints as "Escalated" and logs the change in complaint_history.
 */

require_once __DIR__ . "/status_lookup.php";

/**
 * Escalate overdue complaints.
 * Safe to call on every dashboard page load (it only updates when needed).
 */
function run_sla_escalation(mysqli $conn): void
{
    $ID_PENDING   = get_status_id_or($conn, "Pending", 1);
    $ID_ASSIGNED  = get_status_id_or($conn, "Assigned", 2);
    $ID_RESOLVED  = get_status_id_or($conn, "Resolved", 3);
    $ID_CLOSED    = get_status_id_or($conn, "Closed", 4);
    $ID_VERIFIED  = get_status_id_or($conn, "Verified", 7);
    $ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);

    // Find candidates that crossed initial SLA (still pending/verified)
    $sql_init = "
        SELECT complaint_id, status_id
        FROM complaints
        WHERE status_id IN (?, ?)
          AND initial_sla_due IS NOT NULL
          AND initial_sla_due < NOW()
    ";
    $stmt1 = $conn->prepare($sql_init);
    if ($stmt1) {
        $stmt1->bind_param("ii", $ID_PENDING, $ID_VERIFIED);
        $stmt1->execute();
        $res1 = $stmt1->get_result();
        while ($row = $res1->fetch_assoc()) {
            $cid = (int)$row['complaint_id'];
            _escalate_one($conn, $cid, $ID_ESCALATED, "Auto escalation: Initial SLA breached");
        }
        $stmt1->close();
    }

    // Find candidates that crossed resolution SLA (still open)
    $sql_res = "
        SELECT complaint_id, status_id
        FROM complaints
        WHERE status_id NOT IN (?, ?, ?)
          AND resolution_sla_due IS NOT NULL
          AND resolution_sla_due < NOW()
    ";
    $stmt2 = $conn->prepare($sql_res);
    if ($stmt2) {
        $stmt2->bind_param("iii", $ID_RESOLVED, $ID_CLOSED, $ID_ESCALATED);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $cid = (int)$row['complaint_id'];
            _escalate_one($conn, $cid, $ID_ESCALATED, "Auto escalation: Resolution SLA breached");
        }
        $stmt2->close();
    }
}

/**
 * Update complaint status to escalated if not already escalated, and insert history.
 */
function _escalate_one(mysqli $conn, int $complaint_id, int $escalated_status_id, string $remark): void
{
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

    // History: updated_by = 0 means "System"
    $hist = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, 0, ?)");
    if (!$hist) return;
    $hist->bind_param("iis", $complaint_id, $escalated_status_id, $remark);
    $hist->execute();
    $hist->close();
}

