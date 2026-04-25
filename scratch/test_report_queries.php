<?php
$conn = new mysqli("localhost", "root", "", "complaint_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "--- Performance Analysis (Resolution Time) ---\n";
$sql1 = "
    SELECT 
        cat.category_name,
        AVG(TIMESTAMPDIFF(HOUR, c.created_at, h.resolved_at)) as avg_hours
    FROM (
        SELECT 
            complaint_id, 
            MIN(updated_at) as resolved_at 
        FROM complaint_history 
        WHERE status_id IN (3, 4) 
        GROUP BY complaint_id
    ) h
    JOIN complaints c ON h.complaint_id = c.complaint_id
    JOIN complaint_categories cat ON c.category_id = cat.category_id
    GROUP BY cat.category_id
";
$res1 = $conn->query($sql1);
if ($res1) {
    while ($row = $res1->fetch_assoc()) {
        echo "{$row['category_name']}: " . round($row['avg_hours'], 1) . " hours\n";
    }
} else {
    echo "SQL1 Error: " . $conn->error . "\n";
}

echo "\n--- SLA Health Stats ---\n";
// Resolved first timestamp helper
$resolved_at_cte = "
    SELECT 
        complaint_id, 
        MIN(updated_at) as resolved_at 
    FROM complaint_history 
    WHERE status_id IN (3, 4) 
    GROUP BY complaint_id
";

// Within SLA
$sql2 = "
    SELECT COUNT(*) as solved_in_sla
    FROM complaints c
    JOIN ($resolved_at_cte) h ON c.complaint_id = h.complaint_id
    WHERE h.resolved_at <= c.resolution_sla_due
";
$in_sla = $conn->query($sql2)->fetch_assoc()['solved_in_sla'];
echo "Resolved Within SLA: $in_sla\n";

// Breached
$sql3 = "
    SELECT COUNT(*) as breached
    FROM complaints c
    LEFT JOIN ($resolved_at_cte) h ON c.complaint_id = h.complaint_id
    WHERE (h.resolved_at IS NOT NULL AND h.resolved_at > c.resolution_sla_due)
       OR (h.resolved_at IS NULL AND c.status_id NOT IN (3, 4) AND NOW() > c.resolution_sla_due)
";
$breached = $conn->query($sql3)->fetch_assoc()['breached'];
echo "SLA Breached: $breached\n";

$conn->close();
?>
