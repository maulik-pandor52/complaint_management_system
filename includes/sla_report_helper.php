<?php
require_once __DIR__ . '/app_helper.php';
require_once __DIR__ . '/status_lookup.php';

function format_sla_duration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return sprintf('%02dh %02dm', $hours, $minutes);
}

function format_sla_timer(?string $endAt, int $nowTs): array
{
    if ($endAt === null || $endAt === '') {
        return [
            'label' => 'N/A',
            'mode' => 'none',
            'seconds' => 0,
        ];
    }

    $deadlineTs = strtotime($endAt);
    if ($deadlineTs === false) {
        return [
            'label' => 'N/A',
            'mode' => 'none',
            'seconds' => 0,
        ];
    }

    $delta = $deadlineTs - $nowTs;
    if ($delta >= 0) {
        return [
            'label' => format_sla_duration($delta) . ' remaining',
            'mode' => 'remaining',
            'seconds' => $delta,
        ];
    }

    return [
        'label' => format_sla_duration(abs($delta)) . ' breached',
        'mode' => 'breached',
        'seconds' => abs($delta),
    ];
}

function format_elapsed_label(string $prefix, int $seconds): array
{
    return [
        'label' => $prefix . ' ' . format_sla_duration($seconds),
        'mode' => 'fixed',
        'seconds' => $seconds,
        'is_live' => false,
    ];
}

function calculate_deadline_from_created(string $createdAt, int $hours): string
{
    $createdTs = strtotime($createdAt);
    if ($createdTs === false) {
        return '';
    }

    return date('Y-m-d H:i:s', $createdTs + ($hours * 3600));
}

function calculate_live_timer(string $createdAt, ?string $eventAt, int $slaHours, int $nowTs): array
{
    $deadline = calculate_deadline_from_created($createdAt, $slaHours);
    $createdTs = strtotime($createdAt);
    $deadlineTs = strtotime($deadline);

    if ($createdTs === false || $deadlineTs === false) {
        return [
            'deadline' => $deadline,
            'status' => 'Unknown',
            'timer' => ['label' => 'N/A', 'mode' => 'none', 'seconds' => 0, 'is_live' => false],
            'is_breached' => false,
        ];
    }

    if (!empty($eventAt)) {
        $eventTs = strtotime($eventAt);
        if ($eventTs !== false) {
            $elapsed = max(0, $eventTs - $createdTs);
            $within = $eventTs <= $deadlineTs;
            return [
                'deadline' => $deadline,
                'status' => $within ? 'Within SLA' : 'Breached',
                'timer' => $within ? format_elapsed_label('Completed in', $elapsed) : format_elapsed_label('Breached by', $eventTs - $deadlineTs),
                'is_breached' => !$within,
            ];
        }
    }

    $delta = $deadlineTs - $nowTs;
    if ($delta >= 0) {
        return [
            'deadline' => $deadline,
            'status' => 'Within SLA',
            'timer' => [
                'label' => format_sla_duration($delta) . ' remaining',
                'mode' => 'remaining',
                'seconds' => $delta,
                'is_live' => true,
            ],
            'is_breached' => false,
        ];
    }

    return [
        'deadline' => $deadline,
        'status' => 'Breached',
        'timer' => [
            'label' => format_sla_duration(abs($delta)) . ' breached',
            'mode' => 'breached',
            'seconds' => abs($delta),
            'is_live' => true,
        ],
        'is_breached' => true,
    ];
}

function calculate_overall_sla_badge(bool $initialSlaBreached, bool $resolutionSlaBreached): string
{
    if ($resolutionSlaBreached) {
        return 'Escalated';
    }

    if ($initialSlaBreached) {
        return 'Delayed';
    }

    return 'Within SLA';
}

function earliest_sla_event_at(?string $firstAt, ?string $secondAt): ?string
{
    $firstTs = !empty($firstAt) ? strtotime($firstAt) : false;
    $secondTs = !empty($secondAt) ? strtotime($secondAt) : false;

    if ($firstTs === false && $secondTs === false) {
        return null;
    }

    if ($firstTs === false) {
        return $secondAt;
    }

    if ($secondTs === false) {
        return $firstAt;
    }

    return $firstTs <= $secondTs ? $firstAt : $secondAt;
}

function get_live_sla_report_rows(mysqli $conn): array
{
    $ID_PENDING = get_status_id_or($conn, 'Pending', 1);
    $ID_RESOLVED = get_status_id_or($conn, 'Resolved', 3);
    $ID_CLOSED = get_status_id_or($conn, 'Closed', 4);

    $sql = "
        SELECT
            c.complaint_id,
            c.title,
            c.description,
            c.priority,
            c.created_at,
            c.initial_sla_due,
            c.resolution_sla_due,
            c.status_id,
            s.status_name,
            cat.category_name,
            a.level1 AS campus,
            a.level2 AS building,
            a.level3 AS spot,
            assign_info.assigned_staff,
            assign_info.first_assignment_at,
            response_info.first_response_at,
            resolved_info.resolved_at
        FROM complaints c
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        LEFT JOIN area_master a ON c.area_id = a.area_id
        LEFT JOIN status_master s ON c.status_id = s.status_id
        LEFT JOIN (
            SELECT
                ass.complaint_id,
                MIN(ass.assigned_at) AS first_assignment_at,
                GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS assigned_staff
            FROM assignments ass
            LEFT JOIN users u ON ass.staff_id = u.user_id
            GROUP BY ass.complaint_id
        ) assign_info ON assign_info.complaint_id = c.complaint_id
        LEFT JOIN (
            SELECT
                h.complaint_id,
                MIN(h.updated_at) AS first_response_at
            FROM complaint_history h
            WHERE h.status_id <> ?
            GROUP BY h.complaint_id
        ) response_info ON response_info.complaint_id = c.complaint_id
        LEFT JOIN (
            SELECT
                h.complaint_id,
                MIN(h.updated_at) AS resolved_at
            FROM complaint_history h
            WHERE h.status_id IN (?, ?)
            GROUP BY h.complaint_id
        ) resolved_info ON resolved_info.complaint_id = c.complaint_id
        ORDER BY c.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('iii', $ID_PENDING, $ID_RESOLVED, $ID_CLOSED);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $nowTs = time();

    while ($row = $result->fetch_assoc()) {
        $firstResponseAt = earliest_sla_event_at($row['first_assignment_at'], $row['first_response_at']);
        $resolutionEventAt = in_array((int)$row['status_id'], [$ID_RESOLVED, $ID_CLOSED], true) && !empty($row['resolved_at'])
            ? $row['resolved_at']
            : null;

        $initialSla = calculate_live_timer($row['created_at'], $firstResponseAt, 6, $nowTs);
        $resolutionSla = calculate_live_timer($row['created_at'], $resolutionEventAt, 30, $nowTs);
        $initialSlaBreached = $initialSla['is_breached'];
        $resolutionSlaBreached = $resolutionSla['is_breached'];
        $overallSlaBadge = calculate_overall_sla_badge($initialSlaBreached, $resolutionSlaBreached);
        $initialSlaStatus = $initialSlaBreached ? 'Breached' : 'Within SLA';
        $initialSlaDisplayStatus = $initialSlaBreached ? 'Delayed' : 'Within SLA';
        $resolutionSlaStatus = $resolutionSlaBreached ? 'Breached' : 'Within SLA';
        $isDelayed = $overallSlaBadge === 'Delayed';
        $isEscalated = $overallSlaBadge === 'Escalated';

        $rows[] = [
            'complaint_id' => (int)$row['complaint_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category' => $row['category_name'] ?: 'Uncategorized',
            'campus' => $row['campus'] ?: '-',
            'building' => $row['building'] ?: '-',
            'spot' => $row['spot'] ?: '-',
            'priority' => $row['priority'],
            'status_name' => $row['status_name'] ?: 'Unknown',
            'display_status_name' => $overallSlaBadge,
            'created_at' => $row['created_at'],
            'assigned_staff' => $row['assigned_staff'] ?: 'Unassigned',
            'initial_sla_start' => $row['created_at'],
            'initial_sla_deadline' => $initialSla['deadline'],
            'resolution_sla_start' => $row['created_at'],
            'resolution_sla_deadline' => $resolutionSla['deadline'],
            'current_time' => date('Y-m-d H:i:s', $nowTs),
            'initial_timer' => $initialSla['timer'],
            'resolution_timer' => $resolutionSla['timer'],
            'initial_sla_status' => $initialSlaStatus,
            'initial_sla_display_status' => $initialSlaDisplayStatus,
            'resolution_sla_status' => $resolutionSlaStatus,
            'overall_sla_badge' => $overallSlaBadge,
            'initial_status' => $initialSlaDisplayStatus,
            'resolution_status' => $resolutionSlaStatus,
            'escalated' => $overallSlaBadge,
            'is_escalated' => $isEscalated,
            'is_delayed' => $isDelayed,
            'initial_sla_breached' => $initialSlaBreached,
            'resolution_sla_breached' => $resolutionSlaBreached,
            'resolved_at' => $row['resolved_at'],
            'first_assignment_at' => $row['first_assignment_at'],
            'first_response_at' => $row['first_response_at'],
            'status_id' => (int)$row['status_id'],
        ];
    }

    $stmt->close();
    return $rows;
}

function format_pdf_datetime(?string $datetime): string
{
    if (empty($datetime)) {
        return 'N/A';
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return 'N/A';
    }

    return date('d M Y, h:i A', $ts);
}
?>
