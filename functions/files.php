<?php

// Filesystem and embedded-image helpers
// Split from the former monolithic functions.php


function removeDirectory($path) {
    if (!file_exists($path)) {
        return;
    }

    $files = glob($path . '/*');
    foreach ($files as $file) {
        is_dir($file) ? removeDirectory($file) : unlink($file);
    }
    rmdir($path);
}

function copyDirectory($src, $dst) {
    if (!is_dir($src)) {
        return;
    }

    if (!is_dir($dst)) {
        mkdir($dst, 0775, true);
    }

    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $srcPath = $src . '/' . $item;
        $dstPath = $dst . '/' . $item;

        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

function mkdirMissing($dir) {
    if (!is_dir($dir)) {
        mkdir($dir);
    }
}

function saveBase64Images(string $html, string $baseFsPath, string $baseWebPath, int $ownerId): string {
    // Normalize paths
    $baseFsPath  = rtrim($baseFsPath, '/\\') . '/';
    $baseWebPath = rtrim($baseWebPath, '/\\') . '/';

    $targetDir = $baseFsPath . $ownerId . "/";

    $folderCreated = false;   // <-- NEW FLAG
    $savedAny      = false;   // <-- Track if ANY images processed

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $imgs = $dom->getElementsByTagName('img');

    foreach ($imgs as $img) {
        $src = $img->getAttribute('src');

        // Match base64 images
        if (preg_match('/^data:image\/([a-zA-Z0-9+]+);base64,(.*)$/s', $src, $matches)) {

            $savedAny = true;  // <-- We are actually saving at least 1 image

            // Create folder ONLY when needed
            if (!$folderCreated) {
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0775, true);
                }
                $folderCreated = true;
            }

            $mimeType = strtolower($matches[1]);
            $base64   = $matches[2];

            $binary = base64_decode($base64);
            if ($binary === false) {
                continue;
            }

            // Extension mapping
            switch ($mimeType) {
                case 'jpeg':
                case 'jpg': $ext = 'jpg'; break;
                case 'png': $ext = 'png'; break;
                case 'gif': $ext = 'gif'; break;
                case 'webp': $ext = 'webp'; break;
                default: $ext = 'png';
            }

            // Secure random filename
            $uid = bin2hex(random_bytes(16));
            $filename = "img_{$uid}.{$ext}";

            $filePath = $targetDir . $filename;

            if (file_put_contents($filePath, $binary) !== false) {
                $webPath = "/" . $baseWebPath . $ownerId . "/" . $filename;
                $img->setAttribute('src', $webPath);
            }
        }
    }

    // If no images were processed, return original HTML immediately
    if (!$savedAny) {
        return $html;
    }

    // Extract body content only
    $body = $dom->getElementsByTagName('body')->item(0);

    if ($body) {
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }
        return $innerHTML;
    }

    return $html;
}

function cleanupUnusedImages(string $html, string $folderFsPath, string $folderWebPath) {

    $folderFsPath  = rtrim($folderFsPath, '/\\') . '/';
    $folderWebPath = rtrim($folderWebPath, '/\\') . '/';

    if (!is_dir($folderFsPath)) {
        return; // no folder = nothing to delete
    }

    // 1. Get all files currently on disk
    $filesOnDisk = glob($folderFsPath . "*");

    // 2. Find all <img src="">
    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
    $htmlImagePaths = $matches[1] ?? [];

    // Normalize paths: keep only filenames belonging to this template folder
    $referencedFiles = [];

    foreach ($htmlImagePaths as $src) {
        if (strpos($src, $folderWebPath) !== false) {
            $filename = basename($src);
            $referencedFiles[] = $filename;
        }
    }

    // 3. Delete any physical file not referenced in the HTML
    foreach ($filesOnDisk as $filePath) {
        $filename = basename($filePath);

        if (!in_array($filename, $referencedFiles)) {
            unlink($filePath);
        }
    }
}

/*
 * Ticket attachment uploads
 *
 * Shared by the agent ticket page, the new ticket modal and the client portal
 * reply form, so the allowed extension list has one definition rather than one
 * per caller.
 *
 * Files land in uploads/tickets/<ticket id>/ under an unguessable reference name
 * and are only ever served back through the ticket_attachment.php endpoints,
 * which re-check permissions and force a safe Content-Type.
 *
 * Pass $reply_id to attach to a specific reply, or null to attach to the ticket
 * itself - the ticket page reads reply_id IS NULL as "belongs to the ticket".
 *
 * Returns a list of what was stored - ['name' => original name, 'path' => path
 * relative to the app root, 'size' => bytes] - so a caller can pass the same
 * files to the mail queue. Anything the extension allow-list or checkFileUpload()
 * rejects is skipped silently, as it always has been.
 */
function saveTicketAttachments($ticket_id, $reply_id = null, $field_name = 'attachments') {

    global $mysqli;

    $ticket_id = intval($ticket_id);

    $stored_attachments = [];

    if (!$ticket_id || empty($_FILES[$field_name]) || !isset($_FILES[$field_name]['name'])) {
        return $stored_attachments;
    }

    $allowed_extensions = array(
        'jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx',
        'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz',
        'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn'
    );

    // A single-file input posts scalars, a multiple one posts arrays - normalize
    $names = $_FILES[$field_name]['name'];
    if (!is_array($names)) {
        return $stored_attachments;
    }

    mkdirMissing('../uploads/tickets/');
    $upload_file_dir = "../uploads/tickets/" . $ticket_id . "/";
    mkdirMissing($upload_file_dir);

    if ($reply_id === null) {
        $reply_id_sql = 'NULL';
    } else {
        $reply_id_sql = intval($reply_id);
    }

    for ($i = 0; $i < count($names); $i++) {

        $single_file = [
            'name' => $_FILES[$field_name]['name'][$i],
            'type' => $_FILES[$field_name]['type'][$i],
            'tmp_name' => $_FILES[$field_name]['tmp_name'][$i],
            'error' => $_FILES[$field_name]['error'][$i],
            'size' => $_FILES[$field_name]['size'][$i]
        ];

        $attachment_reference_name = checkFileUpload($single_file, $allowed_extensions);

        if (!$attachment_reference_name) {
            continue;
        }

        $destination_path = $upload_file_dir . $attachment_reference_name;

        if (!move_uploaded_file($single_file['tmp_name'], $destination_path)) {
            continue;
        }

        $attachment_name = escapeSql($single_file['name']);

        mysqli_query($mysqli, "INSERT INTO ticket_attachments SET ticket_attachment_name = '$attachment_name', ticket_attachment_reference_name = '$attachment_reference_name', ticket_attachment_reply_id = $reply_id_sql, ticket_attachment_ticket_id = $ticket_id");

        // Path is relative to the app root, not the caller, so the mail cron can
        // resolve it from its own directory
        $stored_attachments[] = [
            'name' => $single_file['name'],
            'path' => "uploads/tickets/$ticket_id/$attachment_reference_name",
            'size' => (int) $single_file['size']
        ];
    }

    return $stored_attachments;
}

/*
 * Splits a stored attachment list into what the mail queue will carry and what it
 * will not, against MAX_EMAIL_ATTACHMENT_BYTES applied to the message as a whole.
 *
 * Oversized files stay on the ticket - the recipient can still download them from
 * the portal - and the caller is expected to say so rather than leaving the agent
 * to assume everything went out.
 *
 * Returns ['send' => [...], 'skipped' => [...]] in the input order.
 */
function filterEmailableAttachments($attachments) {

    $result = ['send' => [], 'skipped' => []];
    $running_total = 0;

    foreach ($attachments as $attachment) {
        $size = intval($attachment['size'] ?? 0);

        if ($size > 0 && $running_total + $size <= MAX_EMAIL_ATTACHMENT_BYTES) {
            $running_total += $size;
            $result['send'][] = $attachment;
        } else {
            $result['skipped'][] = $attachment;
        }
    }

    return $result;
}
