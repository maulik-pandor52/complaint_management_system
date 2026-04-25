<?php
function uploadFile($file)
{
    $targetDir = "../assets/uploads/";
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];
    $dangerousExtensions = ['php', 'phtml', 'phar', 'exe', 'js', 'sh', 'bat', 'cmd', 'com', 'msi', 'dll'];
    $originalName = $file['name'] ?? '';
    $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]/', '', $originalName);
    $parts = array_values(array_filter(explode('.', $safeOriginalName), 'strlen'));

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ["status" => false, "msg" => "Invalid upload"];
    }

    if (count($parts) < 2) {
        return ["status" => false, "msg" => "Missing file extension"];
    }

    $extension = strtolower(end($parts));
    $intermediateExtensions = array_map('strtolower', array_slice($parts, 0, -1));

    if (!isset($allowedMimeTypes[$extension])) {
        return ["status" => false, "msg" => "Invalid file type"];
    }

    foreach ($intermediateExtensions as $part) {
        if (in_array($part, $dangerousExtensions, true)) {
            return ["status" => false, "msg" => "Unsafe file name detected"];
        }
    }

    if ($file["size"] > 2000000) {
        return ["status" => false, "msg" => "File too large"];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    if ($mimeType !== $allowedMimeTypes[$extension]) {
        return ["status" => false, "msg" => "File MIME type is not allowed"];
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return ["status" => false, "msg" => "Upload directory is not available"];
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["status" => true, "path" => "assets/uploads/" . $fileName];
    }

    return ["status" => false, "msg" => "Upload failed"];
}
?>
