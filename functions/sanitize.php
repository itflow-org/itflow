<?php

// Input sanitization and upload validation
// Split from the former monolithic functions.php


function escapeHtml($unsanitizedInput) {
    //return htmlentities($unsanitizedInput ?? '');
    return htmlspecialchars($unsanitizedInput ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($input) {
    global $mysqli;

    if (!empty($input)) {
        // Only convert encoding if it's NOT valid UTF-8
        if (!mb_check_encoding($input, 'UTF-8')) {
            // Try converting from Windows-1252 as a safe default fallback
            $input = mb_convert_encoding($input, 'UTF-8', 'Windows-1252');
        }
    }

    // Remove HTML and PHP tags
    $input = strip_tags((string) $input);

    // Trim white space
    $input = trim($input);

    // Escape for SQL
    $input = mysqli_real_escape_string($mysqli, $input);

    return $input;
}

function cleanInput($input) {
    // Only process non-empty input
    if (!empty($input)) {
        // Normalize encoding to UTF-8 if it’s not valid
        if (!mb_check_encoding($input, 'UTF-8')) {
            // Convert from Windows-1252 as a safe fallback
            $input = mb_convert_encoding($input, 'UTF-8', 'Windows-1252');
        }
    }

    // Remove HTML and PHP tags
    $input = strip_tags((string) $input);

    // Trim whitespace
    $input = trim($input);

    return $input;
}

function strtoAZaz09($string)
{
    // Gets rid of non-alphanumerics
    return preg_replace('/[^A-Za-z0-9_-]/', '', $string);
}

function sanitize_url($url) {
    $allowed = ['http', 'https', 'file', 'ftp', 'ftps', 'sftp', 'dav', 'webdav', 'caldav', 'carddav',  'ssh', 'telnet', 'smb', 'rdp', 'vnc', 'rustdesk', 'anydesk', 'connectwise', 'splashtop', 'sip', 'sips', 'ldap', 'ldaps'];
    $parts = parse_url($url ?? '');
    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), $allowed)) {
        // Remove the scheme and colon
        $pos = strpos($url, ':');
        $without_scheme = $url;
        if ($pos !== false) {
            $without_scheme = substr($url, $pos + 1); // This keeps slashes (e.g. //pizza.com)
        }
        // Prepend 'unsupported://' (strip any leading slashes from $without_scheme to avoid triple slashes)
        $unsupported = 'unsupported://' . ltrim($without_scheme, '/');
        return htmlspecialchars($unsupported, ENT_QUOTES, 'UTF-8');
    }

    // Safe schemes: return escaped original URL
    return htmlspecialchars($url ?? '', ENT_QUOTES, 'UTF-8');
}

// Sanitize File Names
function sanitize_filename($filename, $strict = false) {
    // Remove path information and dots around the filename
    $filename = basename($filename);

    // Replace spaces and underscores with dashes
    $filename = str_replace([' ', '_'], '-', $filename);

    // Remove anything which isn't a word, number, dot, or dash
    $filename = preg_replace('/[^A-Za-z0-9\.\-]/', '', $filename);

    // Optionally make filename strict alphanumeric (keep dot and dash)
    if ($strict) {
        $filename = preg_replace('/[^A-Za-z0-9\.\-]/', '', $filename);
    }

    // Avoid multiple consecutive dashes
    $filename = preg_replace('/-+/', '-', $filename);

    // Remove leading/trailing dots and dashes
    $filename = trim($filename, '.-');

    // Ensure it’s not empty
    if (empty($filename)) {
        $filename = 'file';
    }

    return $filename;
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
    $hashedContent = hash('md5', $fileContent);

    // Generate a secure filename using the hashed content
    $secureFilename = $hashedContent . randomString(2) . '.' . $extension;

    return $secureFilename;
}
