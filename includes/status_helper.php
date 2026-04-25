<?php
/**
 * Resolvex Status Helper
 * Centralized mapping for status colors and icons
 */

/**
 * Get CSS class and Icon for a given status name or ID
 */
function get_status_config($status_name, $is_overdue = false) {
    if ($is_overdue) {
        return ['class' => 'status-red', 'icon' => 'fa-triangle-exclamation'];
    }

    $status_name = strtolower(trim($status_name));

    switch ($status_name) {
        case 'pending':
        case 'reopened - pending approval':
        case 'in review':
        case 'waiting':
        case 'verified':
            return ['class' => 'status-yellow', 'icon' => 'fa-clock-rotate-left'];
        
        case 'in progress':
        case 'reopened - assigned':
        case 'assigned':
            return ['class' => 'status-blue', 'icon' => 'fa-spinner fa-spin-pulse'];
        
        case 'resolved':
        case 'closed':
        case 'completed':
            return ['class' => 'status-green', 'icon' => 'fa-circle-check'];
        
        case 'rejected':
        case 'declined':
        case 'urgent':
            return ['class' => 'status-red', 'icon' => 'fa-circle-xmark'];

        case 'escalated':
            return ['class' => 'status-red', 'icon' => 'fa-triangle-exclamation'];
        
        default:
            return ['class' => 'status-gray', 'icon' => 'fa-circle-question'];
    }
}

/**
 * Render a complete HTML badge for a status
 */
function render_status_badge($status_name, $is_overdue = false) {
    $config = get_status_config($status_name, $is_overdue);
    $display_name = $is_overdue ? "OVERDUE" : htmlspecialchars($status_name);
    
    return "<span class='status-badge {$config['class']}'>
                <i class='fas {$config['icon']}'></i>
                {$display_name}
            </span>";
}

/**
 * Get color class for Priority
 */
function get_priority_config($priority) {
    $priority = strtolower(trim($priority));
    switch ($priority) {
        case 'high':
        case 'urgent':
            return ['class' => 'status-red', 'icon' => 'fa-angles-up'];
        case 'medium':
        case 'mid':
            return ['class' => 'status-yellow', 'icon' => 'fa-angle-up'];
        case 'low':
            return ['class' => 'status-blue', 'icon' => 'fa-angle-down'];
        default:
            return ['class' => 'status-gray', 'icon' => 'fa-minus'];
    }
}

/**
 * Render a complete HTML badge for Priority
 */
function render_priority_badge($priority) {
    $config = get_priority_config($priority);
    $display_name = htmlspecialchars(ucfirst($priority));
    
    return "<span class='status-badge {$config['class']}' style='padding: 0.25rem 0.75rem; font-size: 0.7rem;'>
                <i class='fas {$config['icon']}'></i>
                {$display_name}
            </span>";
}
?>
