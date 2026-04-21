<?php
/**
 * Status lookup helpers.
 * Purpose: avoid hardcoding status_id values everywhere.
 */

/**
 * Get status_id by status_name (case-insensitive).
 * Returns null if not found.
 */
function get_status_id(mysqli $conn, string $status_name): ?int
{
    static $cache = [];

    $key = strtolower(trim($status_name));
    if ($key === '') return null;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("SELECT status_id FROM status_master WHERE LOWER(status_name) = LOWER(?) LIMIT 1");
    if (!$stmt) {
        $cache[$key] = null;
        return null;
    }

    $stmt->bind_param("s", $status_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $cache[$key] = $row ? (int)$row['status_id'] : null;
    return $cache[$key];
}

/**
 * Get a status_id, but fall back to a known numeric id (to keep compatibility
 * with existing databases where IDs are fixed).
 */
function get_status_id_or(mysqli $conn, string $status_name, int $fallback_id): int
{
    $id = get_status_id($conn, $status_name);
    return $id !== null ? $id : $fallback_id;
}

