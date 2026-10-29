<?php

// Filesystem and embedded-image helpers
// Split from the former monolithic functions.php


/** What a file attached to a ticket can be - by its extension. Canned responses' files too. */
const TICKET_ATTACHMENT_EXTENSIONS = [
    'jpg', 'jpeg', 'gif', 'png', 'webp', 'pdf', 'txt', 'md', 'doc', 'docx',
    'odt', 'csv', 'xls', 'xlsx', 'ods', 'pptx', 'odp', 'zip', 'tar', 'gz',
    'xml', 'msg', 'json', 'wav', 'mp3', 'ogg', 'mov', 'mp4', 'av1', 'ovpn'
];

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

    $allowed_extensions = TICKET_ATTACHMENT_EXTENSIONS;

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

/*
 * Files & documents section - how a file's type is shown, and the client's
 * folder tree. Shared by agent/files.php, the document page, the file and
 * document quick looks and the upload, move and link modals.
 */

/**
 * What kind of file an extension is: its icon, a short label, and the colour
 * family its tile is drawn in (.itflow-file-tone-* in itflow_custom.css).
 * Leans on getFileIcon() for the icon where the two agree, so the portal and
 * the agent side still read from one list of extensions.
 */
function fileTypeInfo($extension) {
    $extension = strtolower(trim((string) $extension));
    $kinds = [
        ['pdf', ['pdf'], 'file-pdf', 'PDF'],
        ['word', ['doc', 'docx', 'odt', 'rtf', 'pages'], 'file-word', 'Word'],
        ['excel', ['xls', 'xlsx', 'ods', 'csv', 'numbers'], 'file-excel', 'Spreadsheet'],
        ['slides', ['ppt', 'pptx', 'odp'], 'file-powerpoint', 'Presentation'],
        ['image', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'svg'], 'file-image', 'Image'],
        ['media', ['mp4', 'mov', 'av1', 'avi', 'mkv', 'webm', 'wmv'], 'file-video', 'Video'],
        ['media', ['mp3', 'wav', 'ogg', 'flac', 'm4a'], 'file-audio', 'Audio'],
        ['archive', ['zip', 'tar', 'gz', 'tgz', '7z', 'rar'], 'file-archive', 'Archive'],
        ['text', ['txt', 'md', 'log'], 'file-alt', 'Text'],
        ['text', ['msg', 'eml'], 'envelope', 'Email'],
        ['code', ['json', 'cfg', 'conf', 'ini', 'xml', 'yaml', 'yml', 'ovpn', 'ps1', 'bat', 'sh', 'unf', 'unifi', 'stk', 'swb'], 'file-code', 'Config / script'],
        ['drawing', ['vsdx', 'drawio'], 'project-diagram', 'Diagram'],
        ['cert', ['pfx', 'p12', 'cer', 'crt', 'pem', 'key'], 'certificate', 'Certificate / key'],
    ];
    foreach ($kinds as [$tone, $extensions, $icon, $label]) {
        if (in_array($extension, $extensions, true)) {
            return ['tone' => $tone, 'icon' => $icon, 'label' => $label];
        }
    }
    return ['tone' => 'other', 'icon' => getFileIcon($extension), 'label' => $extension !== '' ? strtoupper($extension) . ' file' : 'File'];
}

/** A file's type as a coloured tile - the files list, linked items, the quick look. $size is '', 'sm' or 'lg'. */
function fileTypeTile($extension, $size = '') {
    $info = fileTypeInfo($extension);
    return '<span class="itflow-file-icon itflow-file-tone-' . $info['tone'] . ($size ? " itflow-file-icon-$size" : '') . '" title="' . escapeHtml($info['label']) . '" aria-hidden="true"><i class="fas fa-fw fa-' . $info['icon'] . '"></i></span>';
}

/** A created document's tile, in the same family as fileTypeTile(). */
function documentTile($size = '') {
    return '<span class="itflow-file-icon itflow-file-tone-document' . ($size ? " itflow-file-icon-$size" : '') . '" title="Document" aria-hidden="true"><i class="fas fa-fw fa-file-alt"></i></span>';
}

/**
 * Every folder a client has, in one query: id => [folder_id, folder_name,
 * parent_folder, children => [ids]], with 0 standing in for the root.
 * Folders whose parent no longer exists hang off the root, so nothing in
 * them is ever out of reach.
 */
function folderTree($client_id) {
    global $mysqli;
    $client_id = intval($client_id);
    $tree = [0 => ['folder_id' => 0, 'folder_name' => '/', 'parent_folder' => -1, 'children' => []]];
    $sql = mysqli_query($mysqli, "SELECT folder_id, folder_name, parent_folder FROM folders WHERE folder_client_id = $client_id ORDER BY folder_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $tree[intval($row['folder_id'])] = ['folder_id' => intval($row['folder_id']), 'folder_name' => $row['folder_name'], 'parent_folder' => intval($row['parent_folder']), 'children' => []];
    }
    foreach ($tree as $folder_id => $folder) {
        if ($folder_id === 0) {
            continue;
        }
        $parent = isset($tree[$folder['parent_folder']]) && $folder['parent_folder'] !== $folder_id ? $folder['parent_folder'] : 0;
        $tree[$folder_id]['parent_folder'] = $parent;
        $tree[$parent]['children'][] = $folder_id;
    }
    return $tree;
}

/** The folders from the root down to $folder_id, root left out: [[folder_id, folder_name], ...]. */
function folderPath($tree, $folder_id) {
    $path = [];
    $seen = [];
    $folder_id = intval($folder_id);
    while ($folder_id && isset($tree[$folder_id]) && !isset($seen[$folder_id])) {
        $seen[$folder_id] = true;
        array_unshift($path, ['folder_id' => $folder_id, 'folder_name' => $tree[$folder_id]['folder_name']]);
        $folder_id = $tree[$folder_id]['parent_folder'];
    }
    return $path;
}

/** $folder_id and every folder under it. */
function folderSubtree($tree, $folder_id) {
    $ids = [];
    $stack = [intval($folder_id)];
    while ($stack) {
        $id = array_pop($stack);
        if (!isset($tree[$id]) || isset($ids[$id])) {
            continue;
        }
        $ids[$id] = true;
        foreach ($tree[$id]['children'] as $child) {
            $stack[] = $child;
        }
    }
    return array_keys($ids);
}

/**
 * <option>s for a folder picker: the root, then each folder indented under
 * its parent, with $selected chosen. $exclude leaves out a folder and
 * everything under it - a folder cannot be moved into itself.
 */
function folderOptionsHtml($client_id, $selected = 0, $exclude = 0) {
    $tree = folderTree($client_id);
    $skip = $exclude ? array_flip(folderSubtree($tree, $exclude)) : [];
    $selected = intval($selected);
    $html = '<option value="0"' . ($selected === 0 ? ' selected' : '') . selectOptionData(['icon' => 'fas fa-home']) . '>Top level</option>';
    $walk = function ($parent_id, $depth) use (&$walk, $tree, $skip, $selected, &$html) {
        foreach ($tree[$parent_id]['children'] as $folder_id) {
            if (isset($skip[$folder_id])) {
                continue;
            }
            $path = implode(' / ', array_column(folderPath($tree, $tree[$folder_id]['parent_folder']), 'folder_name'));
            $html .= '<option value="' . $folder_id . '"' . ($folder_id === $selected ? ' selected' : '')
                . selectOptionData(['icon' => 'fas fa-folder', 'description' => $path])
                . '>' . str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . escapeHtml($tree[$folder_id]['folder_name']) . '</option>';
            $walk($folder_id, $depth + 1);
        }
    };
    $walk(0, 0);
    return $html;
}

/** $folder_id if it is one of the client's folders (0, the top level, always is), otherwise 0. */
function folderOwnedId($folder_id, $client_id) {
    global $mysqli;
    $folder_id = intval($folder_id);
    if (!$folder_id) {
        return 0;
    }
    $sql = mysqli_query($mysqli, "SELECT 1 FROM folders WHERE folder_id = $folder_id AND folder_client_id = " . intval($client_id) . " LIMIT 1");
    return mysqli_num_rows($sql) ? $folder_id : 0;
}

/** A folder's name for messages and logs - "Top level" for the root. Raw. */
function folderDisplayName($folder_id) {
    $folder_id = intval($folder_id);
    return $folder_id ? (string) getFieldById('folders', $folder_id, 'folder_name') : 'Top level';
}

/*
 * Deleting for good - shared by the single and bulk delete handlers in
 * agent/post/file.php and agent/post/document.php. Each checks the agent's
 * access to the record's client itself, and takes every link with it.
 */

/** Remove a stored file from disk - quietly, when it is already gone. */
function fileRemoveFromDisk($client_id, $file_reference_name) {
    $path = __DIR__ . "/../uploads/clients/" . intval($client_id) . "/" . basename((string) $file_reference_name);
    if ((string) $file_reference_name !== '' && is_file($path)) {
        unlink($path);
    }
}

/** Delete one file for good: the file on disk, its links and its row. Returns its name, or null if it is not there. */
function fileDeleteForGood($file_id) {
    global $mysqli, $session_name, $client_id;
    $file_id = intval($file_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT file_client_id, file_name, file_reference_name FROM files WHERE file_id = $file_id"));
    if (!$row) {
        return null;
    }
    $client_id = intval($row['file_client_id']);
    enforceClientAccess();
    fileRemoveFromDisk($client_id, $row['file_reference_name']);
    linkRemoveAll('file', $file_id);
    mysqli_query($mysqli, "DELETE FROM files WHERE file_id = $file_id");
    logAudit("File", "Delete", "$session_name deleted file " . escapeSql($row['file_name']), $client_id);
    return $row['file_name'];
}

/** Delete one document for good, with its versions, images and links. Returns its name, or null. */
function documentDeleteForGood($document_id) {
    global $mysqli, $session_name, $client_id;
    $document_id = intval($document_id);
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT document_client_id, document_name FROM documents WHERE document_id = $document_id"));
    if (!$row) {
        return null;
    }
    $client_id = intval($row['document_client_id']);
    enforceClientAccess();
    mysqli_query($mysqli, "DELETE FROM documents WHERE document_id = $document_id");
    mysqli_query($mysqli, "DELETE FROM document_versions WHERE document_version_document_id = $document_id");
    linkRemoveAll('document', $document_id);
    removeDirectory($_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/" . $document_id);
    logAudit("Document", "Delete", "$session_name deleted document " . escapeSql($row['document_name']) . " and all versions", $client_id);
    return $row['document_name'];
}

/**
 * What a document's full-text search runs over (document_content_raw): its
 * name and its text, tags stripped. A space goes in front of every tag first,
 * so "<li>one</li><li>two</li>" reads "one two", not "onetwo". Raw - escape
 * it for the query.
 */
function documentContentRaw($name, $html) {
    return trim(trim((string) $name) . ' ' . trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace('<', ' <', (string) $html)))));
}

/**
 * A document's HTML made safe to show - the HTMLPurifier setup the document
 * page, the document quick look and the version viewer all use.
 */
function documentHtmlPurify($html) {
    static $purifier = null;
    if ($purifier === null) {
        require_once __DIR__ . '/../libs/htmlpurifier/HTMLPurifier.standalone.php';
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('URI.AllowedSchemes', ['data' => true, 'src' => true, 'http' => true, 'https' => true]);
        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify((string) $html);
}
