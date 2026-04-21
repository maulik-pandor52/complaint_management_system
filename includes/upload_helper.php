<?php
function uploadFile($file)
{
    $targetDir = "../assets/uploads/";

    $fileName = time() . "_" . basename($file["name"]);
    $targetFile = $targetDir . $fileName;

    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Allowed types
    $allowed = ["jpg", "jpeg", "png", "pdf"];

    if (!in_array($fileType, $allowed)) {
        return ["status" => false, "msg" => "Invalid file type"];
    }

    if ($file["size"] > 2000000) {
        return ["status" => false, "msg" => "File too large"];
    }

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["status" => true, "path" => "assets/uploads/" . $fileName];
    }

    return ["status" => false, "msg" => "Upload failed"];
}
?>