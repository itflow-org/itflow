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

/*
 * FontAwesome icon name for a file extension.
 *
 * Lived in client/functions.php until agent/files.php needed the same mapping
 * for its gallery view. The portal reaches this file through the root
 * functions.php it already loads, so moving it up costs the portal nothing and
 * leaves one list of extensions rather than two to drift apart.
 */
function getFileIcon($file_extension) {
    $file_extension = strtolower($file_extension);

    // Document icons
    if (in_array($file_extension, ['pdf'])) {
        return 'file-pdf';
    } elseif (in_array($file_extension, ['doc', 'docx'])) {
        return 'file-word';
    } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
        return 'file-excel';
    } elseif (in_array($file_extension, ['ppt', 'pptx'])) {
        return 'file-powerpoint';
    } elseif (in_array($file_extension, ['txt', 'md', 'rtf'])) {
        return 'file-alt';
    } elseif (in_array($file_extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return 'file-archive';
    } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        return 'file-image';
    } elseif (in_array($file_extension, ['mp4', 'avi', 'mov', 'wmv', 'flv'])) {
        return 'file-video';
    } elseif (in_array($file_extension, ['mp3', 'wav', 'ogg', 'flac'])) {
        return 'file-audio';
    } elseif (in_array($file_extension, ['html', 'htm', 'css', 'js', 'php', 'py', 'java'])) {
        return 'file-code';
    } else {
        return 'file';
    }
}

/*
 * MIME types safe to render inline in a browser.
 *
 * Anything not on this list is served Content-Disposition: attachment - HTML
 * and SVG in particular are stored-XSS vectors when a browser renders them from
 * our own origin. Four file-serving entry points enforced it from four
 * identical copies of the array; the gallery preview in agent/files.php has to
 * agree with them about what it can frame, so the list is defined once here.
 *
 * application/json is on the list because a browser renders it as text, not as
 * markup, and every serving point sends X-Content-Type-Options: nosniff so it
 * cannot be re-interpreted as HTML.
 */
function getInlineViewableMimeTypes() {
    return [
        "application/pdf",
        "image/png",
        "image/jpeg",
        "image/gif",
        "image/webp",
        "text/plain",
        "application/json"
    ];
}

/*
 * Tidies raw text into something worth showing in a preview tile.
 *
 * Two callers, two different messes, one clean-up:
 *
 *   documents  - document_content_raw is TinyMCE HTML that has been through
 *                strip_tags(), which removes the TAGS and leaves the ENTITIES.
 *                A paragraph of "Hello&nbsp;world &amp; friends" arrives with
 *                those sequences intact, and escaping it for output turned them
 *                into a literal &amp;nbsp; on screen. Decode first, escape last.
 *
 *   text files - whatever bytes are on disk: a UTF-8 BOM that renders as a
 *                stray glyph, CRLF line endings, stray control characters, and
 *                the possibility that a file claiming text/plain is not text.
 *
 * Returns '' when there is nothing worth showing, so callers fall back to the
 * file-type icon rather than printing noise.
 */
function cleanTextExcerpt($text, $length = 400) {
    $text = (string) $text;

    // A BOM is invisible metadata to a text editor and a stray glyph in HTML
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

    // Entities left behind by strip_tags(), plus TinyMCE's non-breaking spaces,
    // which are U+00A0 once decoded and read as odd gaps
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);

    // Drop anything that is not valid UTF-8 rather than letting a half-decoded
    // byte render as a replacement character
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    // Control characters, keeping the two that carry meaning in a preview.
    // No /u modifier on purpose: these are all single-byte ASCII, and every
    // continuation byte of a multi-byte character is >= 0x80, so a byte-wise
    // strip cannot damage one. With /u the whole call returns null the moment
    // the subject holds a stray invalid byte, blanking the excerpt instead of
    // cleaning it.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Stripped HTML leaves long runs of blank lines and indentation
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{2,}/', "\n", $text);
    $text = trim($text);

    // Cut on a character boundary - a byte-wise substr can split a multi-byte
    // character and leave a broken glyph at the end of every tile
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
    }

    return (string) $text;
}

/*
 * First few hundred characters of a text file, for a grid tile preview.
 *
 * Reads a bounded chunk rather than the whole file - a 40MB log has no business
 * being loaded into a page that shows two dozen tiles - and re-applies the same
 * realpath containment check the file-serving endpoints use, because the caller
 * is building a path from a database column.
 *
 * Returns '' when the file is missing, unreadable, escapes uploads/, or does
 * not look like text at all. The last case matters: a mislabelled binary served
 * as text/plain would otherwise fill the tile with garbage.
 */
function getFileTextExcerpt($client_id, $file_reference_name, $length = 400) {
    $client_id = intval($client_id);

    $uploads_base = realpath(__DIR__ . "/../uploads");
    $file_path = realpath(__DIR__ . "/../uploads/clients/$client_id/$file_reference_name");

    if ($file_path === false || $uploads_base === false || strpos($file_path, $uploads_base) !== 0) {
        return '';
    }

    if (!is_file($file_path) || !is_readable($file_path)) {
        return '';
    }

    $handle = fopen($file_path, 'rb');
    if ($handle === false) {
        return '';
    }
    // Read well past the target so cleaning and the character-boundary cut
    // still have a full excerpt to work with
    $raw = fread($handle, $length * 4);
    fclose($handle);

    if ($raw === false || $raw === '') {
        return '';
    }

    // Does this actually look like text? Count the bytes no text file should
    // carry; a few percent is a mislabelled binary, not a stray character.
    $control_bytes = strlen(preg_replace('/[^\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw));
    if ($control_bytes > 0 && ($control_bytes / strlen($raw)) > 0.05) {
        return '';
    }

    return cleanTextExcerpt($raw, $length);
}
