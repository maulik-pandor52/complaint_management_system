<?php
// includes/flash_messages.php

function set_flash_message($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type, // 'success', 'error', 'info'
        'message' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']); // clear immediately

        $type = $msg['type'];
        $icon = 'info-circle';
        if ($type === 'success') $icon = 'check-circle';
        if ($type === 'error') { $type = 'danger'; $icon = 'exclamation-circle'; }

        echo "<div class='alert alert-{$type} d-flex align-items-center mb-4 border-0 shadow-sm' style='padding: 1.25rem; border-radius: 12px; background: rgba(var(--primary-rgb), 0.1);'>";
        echo "<i class='fas fa-{$icon} me-3 fs-4'></i>";
        echo "<div class='fw-bold'>" . htmlspecialchars($msg['message']) . "</div>";
        echo "</div>";
    }
}
?>
