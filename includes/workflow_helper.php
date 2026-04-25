<?php
/**
 * Workflow / status transition rules.
 * Keeps logic beginner-friendly and centralized.
 */

require_once __DIR__ . "/status_lookup.php";

/**
 * Return true if a status change is allowed.
 * Note: We use status names when possible, and fall back to common IDs.
 */
function is_valid_status_transition(mysqli $conn, int $from_status_id, int $to_status_id): bool
{
    // Common/fallback IDs used in the current project
    $ID_PENDING   = get_status_id_or($conn, "Pending", 1);
    $ID_ASSIGNED  = get_status_id_or($conn, "Assigned", 2);
    $ID_RESOLVED  = get_status_id_or($conn, "Resolved", 3);
    $ID_CLOSED    = get_status_id_or($conn, "Closed", 4);
    $ID_REOPEN_AP = get_status_id_or($conn, "Reopened - Pending Approval", 5);
    $ID_REOPEN_AS = get_status_id_or($conn, "Reopened - Assigned", 6);
    $ID_VERIFIED  = get_status_id_or($conn, "Verified", 7);
    $ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);
    $ID_IN_PROGRESS = get_status_id_or($conn, "In Progress", 10);

    // Allowed transitions (simple version for assignment)
    $allowed = [
        $ID_PENDING   => [$ID_VERIFIED, $ID_ESCALATED],
        $ID_VERIFIED  => [$ID_ASSIGNED, $ID_IN_PROGRESS, $ID_RESOLVED, $ID_ESCALATED],
        $ID_ASSIGNED  => [$ID_ASSIGNED, $ID_IN_PROGRESS, $ID_RESOLVED, $ID_ESCALATED], // allow "update remark" staying in assigned or moving to progress
        $ID_IN_PROGRESS => [$ID_IN_PROGRESS, $ID_RESOLVED, $ID_ESCALATED],
        $ID_ESCALATED => [$ID_ASSIGNED, $ID_IN_PROGRESS, $ID_RESOLVED], // can still work on escalated tickets
        $ID_RESOLVED  => [$ID_CLOSED, $ID_REOPEN_AP],
        $ID_CLOSED    => [$ID_REOPEN_AP],
        $ID_REOPEN_AP => [$ID_VERIFIED], // reopen approval returns to Verified
        $ID_REOPEN_AS => [$ID_IN_PROGRESS, $ID_RESOLVED, $ID_ESCALATED],
    ];

    // If from-status unknown, block by default
    if (!isset($allowed[$from_status_id])) {
        return false;
    }

    return in_array($to_status_id, $allowed[$from_status_id], true);
}

/**
 * Return a list of status IDs a STAFF member is allowed to set from the given status.
 */
function allowed_staff_status_targets(mysqli $conn, int $current_status_id): array
{
    $ID_ASSIGNED  = get_status_id_or($conn, "Assigned", 2);
    $ID_RESOLVED  = get_status_id_or($conn, "Resolved", 3);
    $ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);
    $ID_REOPEN_AS = get_status_id_or($conn, "Reopened - Assigned", 6);
    $ID_IN_PROGRESS = get_status_id_or($conn, "In Progress", 10);
    $ID_VERIFIED  = get_status_id_or($conn, "Verified", 7);

    // Staff can move Assigned/Reopened-Assigned/Escalated/Verified to In Progress or Resolved
    if (in_array($current_status_id, [$ID_ASSIGNED, $ID_REOPEN_AS, $ID_ESCALATED, $ID_IN_PROGRESS, $ID_VERIFIED], true)) {
        return [$ID_IN_PROGRESS, $ID_RESOLVED];
    }

    return [];
}

/**
 * Get a complaint's current status id.
 */
function get_complaint_status_id(mysqli $conn, int $complaint_id): ?int
{
    $stmt = $conn->prepare("SELECT status_id FROM complaints WHERE complaint_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['status_id'] : null;
}

/**
 * Verify that a complaint is assigned to the given staff member.
 */
function is_assigned_to_staff(mysqli $conn, int $complaint_id, int $staff_id): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM assignments WHERE complaint_id = ? AND staff_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ii", $complaint_id, $staff_id);
    $stmt->execute();
    $assigned = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $assigned;
}

/**
 * Insert complaint history in a single shared place.
 */
function add_complaint_history(mysqli $conn, int $complaint_id, int $status_id, ?int $updated_by, string $remark): bool
{
    $stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iiis", $complaint_id, $status_id, $updated_by, $remark);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Update complaint status and insert history as one transaction-safe action.
 */
function update_complaint_status_with_history(mysqli $conn, int $complaint_id, int $status_id, ?int $updated_by, string $remark): bool
{
    $conn->begin_transaction();

    try {
        $existsStmt = $conn->prepare("SELECT complaint_id FROM complaints WHERE complaint_id = ? LIMIT 1");
        if (!$existsStmt) {
            throw new RuntimeException('Unable to verify complaint before update.');
        }
        $existsStmt->bind_param("i", $complaint_id);
        $existsStmt->execute();
        $existsRow = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();

        if (!$existsRow) {
            throw new RuntimeException('Complaint does not exist for update.');
        }

        $stmt = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare complaint update.');
        }

        $stmt->bind_param("ii", $status_id, $complaint_id);
        $ok = $stmt->execute();
        $stmtError = $stmt->error;
        $stmt->close();

        if (!$ok) {
            throw new RuntimeException('Unable to update complaint status. ' . $stmtError);
        }

        if (!add_complaint_history($conn, $complaint_id, $status_id, $updated_by, $remark)) {
            throw new RuntimeException('Unable to write complaint history. ' . $conn->error);
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Workflow update failed: ' . $e->getMessage());
        return false;
    }
}
