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

