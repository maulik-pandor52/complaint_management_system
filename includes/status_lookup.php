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
 * Verify if a status_id exists in the database.
 */
function verify_status_id(mysqli $conn, int $status_id): bool
{
    static $valid_ids = null;
    if ($valid_ids === null) {
        $valid_ids = [];
        $res = $conn->query("SELECT status_id FROM status_master");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $valid_ids[] = (int)$row['status_id'];
            }
        }
    }
    return in_array($status_id, $valid_ids, true);
}

/**
 * Get a status_id, but fall back to a known numeric id.
 * Now safer: returns null if the fallback ID also doesn't exist in DB.
 */
function get_status_id_or(mysqli $conn, string $status_name, int $fallback_id): ?int
{
    $id = get_status_id($conn, $status_name);
    if ($id !== null) return $id;

    if (verify_status_id($conn, $fallback_id)) {
        return $fallback_id;
    }

    return null;
}

