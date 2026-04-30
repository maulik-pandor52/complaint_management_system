<?php
/**
 * Database Connection Configuration
 */

function getDBConnection(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $host = "localhost";
        $user = "root";
        $pass = "";
        $db   = "complaint_database";

        // Enable internal error reporting for mysqli to throw exceptions
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = @mysqli_connect($host, $user, $pass, $db);
            mysqli_set_charset($conn, 'utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection is temporarily unavailable. Please try again later.');
        }
    }

    return $conn;
}

// Global connection variable for legacy compatibility
$conn = getDBConnection();
?>
