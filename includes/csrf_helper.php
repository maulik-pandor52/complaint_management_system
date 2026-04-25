<?php
/**
 * Minimal CSRF protection helpers.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function is_valid_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_token(): void
{
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid request token. Please refresh the page and try again.');
    }
}
?>
