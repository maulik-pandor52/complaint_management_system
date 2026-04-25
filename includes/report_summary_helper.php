<?php
require_once __DIR__ . '/app_helper.php';

function get_reports_summary_data(mysqli $conn): array
{
    $resolvedAtSubquery = "(SELECT complaint_id, MIN(updated_at) AS resolved_at FROM complaint_history WHERE status_id IN (3, 4) GROUP BY complaint_id)";

    $categoryStats = [];
    $perfQuery = "
        SELECT 
            cat.category_name,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, c.created_at, h.resolved_at) / 60), 0) AS avg_hours,
            COUNT(c.complaint_id) AS total_complaints
        FROM complaint_categories cat
        LEFT JOIN complaints c ON cat.category_id = c.category_id
        LEFT JOIN $resolvedAtSubquery h ON c.complaint_id = h.complaint_id
        GROUP BY cat.category_id, cat.category_name
        ORDER BY cat.category_name ASC
    ";
    $perfRes = mysqli_query($conn, $perfQuery);
    if ($perfRes) {
        while ($row = mysqli_fetch_assoc($perfRes)) {
            if ((int)$row['total_complaints'] > 0) {
                $categoryStats[] = [
                    'category_name' => $row['category_name'] ?: 'Uncategorized',
                    'avg_hours' => round((float)$row['avg_hours'], 1),
                    'total_complaints' => (int)$row['total_complaints'],
                ];
            }
        }
    }

    $totals = [
        'total_complaints' => 0,
        'resolved_closed' => 0,
    ];
    $totalsRes = mysqli_query($conn, "SELECT COUNT(*) AS total_complaints, SUM(CASE WHEN status_id IN (3, 4) THEN 1 ELSE 0 END) AS resolved_closed FROM complaints");
    if ($totalsRes && ($row = mysqli_fetch_assoc($totalsRes))) {
        $totals['total_complaints'] = (int)$row['total_complaints'];
        $totals['resolved_closed'] = (int)$row['resolved_closed'];
    }

    $sqlInSla = "SELECT COUNT(*) AS c FROM complaints c JOIN $resolvedAtSubquery h ON c.complaint_id = h.complaint_id WHERE h.resolved_at <= c.resolution_sla_due";
    $sqlBreached = "SELECT COUNT(*) AS c FROM complaints c LEFT JOIN $resolvedAtSubquery h ON c.complaint_id = h.complaint_id WHERE (h.resolved_at IS NOT NULL AND h.resolved_at > c.resolution_sla_due) OR (h.resolved_at IS NULL AND c.status_id NOT IN (3, 4) AND NOW() > c.resolution_sla_due)";

    $inSlaCount = 0;
    $breachedCount = 0;

    $inSlaRes = mysqli_query($conn, $sqlInSla);
    if ($inSlaRes && ($row = mysqli_fetch_assoc($inSlaRes))) {
        $inSlaCount = (int)$row['c'];
    }

    $breachedRes = mysqli_query($conn, $sqlBreached);
    if ($breachedRes && ($row = mysqli_fetch_assoc($breachedRes))) {
        $breachedCount = (int)$row['c'];
    }

    $totalRelevant = $inSlaCount + $breachedCount;
    $adherenceRate = $totalRelevant > 0 ? round(($inSlaCount / $totalRelevant) * 100, 1) : null;

    return [
        'generated_at' => date('d M Y, h:i A'),
        'sla_adherence_rate' => $adherenceRate,
        'complaints_within_sla' => $inSlaCount,
        'sla_breached_complaints' => $breachedCount,
        'category_stats' => $categoryStats,
        'total_complaints' => $totals['total_complaints'],
        'resolved_closed' => $totals['resolved_closed'],
        'has_data' => $totals['total_complaints'] > 0,
    ];
}
?>
