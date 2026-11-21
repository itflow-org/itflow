<?php

/*
 * ITFlow - GET /agent/kb_file.php?file_id=
 * A file that goes with a Knowledge Base article, for anyone who can read
 * the Knowledge Base (Support). &action=view shows it in the browser when it
 * is a kind that is safe to (kbFileSend()).
 */

require_once "../config.php";
require_once "../functions.php";
require_once "../includes/check_login.php";

$row = kbCan(1) ? kbArticleFileRow($_GET['file_id'] ?? 0) : null;
if (!$row) {
    http_response_code(404);
    exit('File not found');
}

kbFileSend($row, ($_GET['action'] ?? '') === 'view');
