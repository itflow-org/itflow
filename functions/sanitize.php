<?php

// Input sanitization and upload validation
// Split from the former monolithic functions.php


function escapeHtml($unsanitizedInput) {
    //return htmlentities($unsanitizedInput ?? '');
    return htmlspecialchars($unsanitizedInput ?? '', ENT_QUOTES, 'UTF-8');
}

function escapeSql($input) {
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

function toAlphanumeric($string) {
    // Gets rid of non-alphanumerics
    return preg_replace('/[^A-Za-z0-9_-]/', '', $string);
}

function escapeUrl($url) {
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
function sanitizeFilename($filename, $strict = false) {
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

// Validate a single $_FILES[...] entry before saving it.
// Returns a safe, unguessable storage filename (random + original extension)
// on success, or false on ANY failure. The client's own filename is never used
// on disk, so path tricks and double extensions (evil.php.jpg) are irrelevant.
function checkFileUpload($file, $allowed_extensions) {
    // Must be a well-formed single-file upload (reject arrays / malformed entries)
    if (!isset($file['tmp_name'], $file['error'], $file['size'], $file['name'])
        || is_array($file['tmp_name'])) {
        return false;
    }

    // Must be a successful upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Must be a genuine HTTP upload, not an arbitrary server path
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    // Reject empty files and enforce the 500 MB ceiling
    $size = (int) $file['size'];
    if ($size <= 0 || $size > 500 * 1024 * 1024) {
        return false;
    }

    // Allow-list check against the FINAL extension only, case-insensitive
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $allowed_extensions, true)) {
        return false;
    }

    // Unguessable storage name. We deliberately do NOT hash file contents:
    // randomString(32) already guarantees uniqueness, and hashing would mean
    // reading the whole file into memory (up to 500 MB) for no downstream use.
    return randomString(32) . '.' . $extension;
}

// Neutralize spreadsheet formula injection (CWE-1236) in a value bound for a
// generated CSV export. This is NOT CSV-structure escaping — fputcsv already
// quotes fields, doubles enclosures, and handles embedded newlines. Its only
// job is to stop a spreadsheet app (Excel / LibreOffice Calc / Google Sheets)
// from *evaluating* a syntactically valid cell as a formula on open. Prefixing
// a single quote makes the app treat the cell as literal text.
function escapeCsvFormula($value) {
    if (!is_string($value) || $value === '') {
        return $value;
    }

    // Leave genuine numbers untouched so real data (e.g. negative amounts like
    // -42.50) isn't corrupted. An actual formula string is never is_numeric(),
    // so nothing dangerous slips through this early return.
    if (is_numeric($value)) {
        return $value;
    }

    // Leading characters that trigger formula / legacy-DDE evaluation on open.
    if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }

    return $value;
}

/**
 * Render a flash alert message for output.
 *
 * flashAlert() messages deliberately carry a little formatting markup - roughly
 * 480 of the ~653 call sites wrap a value in <strong> - while 429 of them also
 * interpolate PHP variables that are user controlled (custom link names, ticket
 * status names, saved payment descriptions and so on). Escaping the lot would
 * print the tags; printing the lot raw is an XSS hole.
 *
 * So: escape everything, then restore a fixed allowlist of formatting tags that
 * carry no attributes and cannot execute. Attribute injection, </script>, event
 * handlers and <a href> are all neutralised because the escaped forms are never
 * put back.
 *
 * Every place that displays $_SESSION['alert_message'] must use this. Before it
 * existed there were four separate implementations and one of them interpolated
 * raw.
 */
function alertMessageHtml($message) {

    $allowed_tags = [
        '<strong>', '</strong>',
        '<b>', '</b>',
        '<em>', '</em>',
        '<i>', '</i>',
        '<code>', '</code>',
        '<br>', '<br/>', '<br />',
    ];

    $safe = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');

    foreach ($allowed_tags as $tag) {
        $safe = str_replace(htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'), $tag, $safe);
    }

    return $safe;
}
