<?php

// File related functions
function removeDirectory($path)
{
    if (!file_exists($path)) {
        return;
    }

    $files = glob($path . '/*');
    foreach ($files as $file) {
        is_dir($file) ? removeDirectory($file) : unlink($file);
    }
    rmdir($path);
}

function mkdirMissing($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir);
    }
}

// Pass $_FILE['file'] to check an uploaded file before saving it
function checkFileUpload($file, $allowed_extensions)
{
    // Variables
    $name = $file['name'];
    $tmp = $file['tmp_name'];
    $size = $file['size'];

    $extarr = explode('.', $name);
    $extension = strtolower(end($extarr));

    // Check a file is actually attached/uploaded
    if ($tmp === '') {
        // No file uploaded
        return false;
    }

    // Check the extension is allowed
    if (!in_array($extension, $allowed_extensions)) {
        // Extension not allowed
        return false;
    }

    // Check the size is under 500 MB
    $maxSizeBytes = 500 * 1024 * 1024; // 500 MB
    if ($size > $maxSizeBytes) {
        return "File size exceeds the limit.";
    }

    // Read the file content
    $fileContent = file_get_contents($tmp);

    // Hash the file content using SHA-256
    $hashedContent = hash('sha256', $fileContent);

    // Generate a secure filename using the hashed content
    $secureFilename = $hashedContent . randomString(2) . '.' . $extension;

    return $secureFilename;
}
