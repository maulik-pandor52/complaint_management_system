<?php
$conn = new mysqli("localhost", "root", "", "complaint_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function describe_table($conn, $table) {
    echo "--- Table: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

describe_table($conn, "complaints");
describe_table($conn, "complaint_history");
describe_table($conn, "status_master");
describe_table($conn, "complaint_categories");

$conn->close();
?>
