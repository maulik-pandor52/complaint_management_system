<?php
/**
 * Shared application helpers for URLs, redirects, and labels.
 */

function app_name(): string
{
    return 'ResovelX';
}

function app_timezone(): string
{
    return 'Asia/Kolkata';
}

function init_app_timezone(): void
{
    if (date_default_timezone_get() !== app_timezone()) {
        date_default_timezone_set(app_timezone());
    }
}

function app_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

    if ($documentRoot !== '' && strpos($projectRoot, $documentRoot) === 0) {
        $basePath = substr($projectRoot, strlen($documentRoot));
        $basePath = '/' . trim($basePath, '/');
    } else {
        $basePath = '/complaint_management_system';
    }

    return $basePath === '/' ? '' : $basePath;
}

function app_url(string $path = ''): string
{
    $base = rtrim(app_base_path(), '/');
    $suffix = ltrim($path, '/');
    return $suffix === '' ? ($base === '' ? '/' : $base . '/') : ($base === '' ? '/' . $suffix : $base . '/' . $suffix);
}

function app_redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit();
}

init_app_timezone();
?>
