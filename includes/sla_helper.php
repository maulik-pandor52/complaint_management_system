<?php
function checkSLA($created_at, $response_hours, $resolution_hours)
{

    $created = strtotime($created_at);
    $now = time();

    $response_deadline = $created + ($response_hours * 3600);
    $resolution_deadline = $created + ($resolution_hours * 3600);

    if ($now > $resolution_deadline) {
        return "RESOLUTION SLA BREACHED";
    } elseif ($now > $response_deadline) {
        return "RESPONSE SLA BREACHED";
    } else {
        return "Within SLA";
    }
}
?>