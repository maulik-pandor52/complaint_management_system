<?php
/**
 * Assignment ownership helpers.
 * Ensures each complaint is assigned only once.
 */

function ensure_assignment_active_schema(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $col = $conn->query("SHOW COLUMNS FROM assignments LIKE 'is_active'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE assignments ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER assigned_at");
        $conn->query("
            UPDATE assignments a
            LEFT JOIN (
                SELECT complaint_id, MAX(assignment_id) AS active_assignment_id
                FROM assignments
                GROUP BY complaint_id
            ) latest ON latest.complaint_id = a.complaint_id
            SET a.is_active = CASE WHEN a.assignment_id = latest.active_assignment_id THEN 1 ELSE 0 END
        ");
    }

    if ($col) {
        $col->close();
    }

    $onceIdx = $conn->query("SHOW INDEX FROM assignments WHERE Key_name = 'uq_assignment_complaint_once'");
    if ($onceIdx && $onceIdx->num_rows === 0) {
        // Old data may already contain duplicate assignments. Keep the first assignment
        // as the permanent owner, then enforce the one-complaint-one-assignment rule.
        $conn->query("
            DELETE duplicate_assignment
            FROM assignments duplicate_assignment
            JOIN assignments first_assignment
              ON first_assignment.complaint_id = duplicate_assignment.complaint_id
             AND first_assignment.assignment_id < duplicate_assignment.assignment_id
        ");
        $conn->query("UPDATE assignments SET is_active = 1");

        try {
            $conn->query("ALTER TABLE assignments ADD UNIQUE KEY uq_assignment_complaint_once (complaint_id)");
        } catch (mysqli_sql_exception $e) {
            error_log('Unable to add unique assignment constraint: ' . $e->getMessage());
        }
    }

    if ($onceIdx) {
        $onceIdx->close();
    }
}

function get_complaint_assignment(mysqli $conn, int $complaint_id): ?array
{
    ensure_assignment_active_schema($conn);

    $stmt = $conn->prepare("
        SELECT a.assignment_id, a.staff_id, a.is_active, u.name AS staff_name
        FROM assignments a
        JOIN users u ON a.staff_id = u.user_id
        WHERE a.complaint_id = ?
        ORDER BY a.assigned_at ASC, a.assignment_id ASC
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function get_active_assignment(mysqli $conn, int $complaint_id): ?array
{
    return get_complaint_assignment($conn, $complaint_id);
}

function assign_complaint_to_staff(mysqli $conn, int $complaint_id, int $staff_id, int $admin_id): array
{
    ensure_assignment_active_schema($conn);

    $conn->begin_transaction();

    try {
        $current = get_complaint_assignment($conn, $complaint_id);
        if ($current) {
            throw new RuntimeException('Complaint already assigned');
        }

        $insert = $conn->prepare("INSERT INTO assignments (complaint_id, staff_id, assigned_by, is_active) VALUES (?, ?, ?, 1)");
        if (!$insert) {
            throw new RuntimeException('Unable to prepare assignment insert.');
        }
        $insert->bind_param("iii", $complaint_id, $staff_id, $admin_id);
        $ok = $insert->execute();
        $insertError = $insert->error;
        $insert->close();

        if (!$ok) {
            throw new RuntimeException('Unable to assign staff. ' . $insertError);
        }

        $conn->commit();

        return [
            'ok' => true,
            'previous_staff_name' => null,
            'message' => 'Complaint successfully assigned!',
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        return [
            'ok' => false,
            'previous_staff_name' => null,
            'message' => $e->getMessage(),
        ];
    }
}
?>
