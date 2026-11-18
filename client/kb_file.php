<?php

/*
 * Client Portal
 * GET /client/kb_file.php?file_id= - a file that goes with a Help article,
 * for a contact whose company the article is shared with (kbClientSql()).
 * &action=view shows it in the browser when it is a kind that is safe to.
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

$row = kbArticleFileRow($_GET['file_id'] ?? 0);
if (!$row || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM kb_articles WHERE kb_article_id = " . intval($row['kb_article_file_kb_article_id']) . " AND " . kbClientSql($session_client_id)))) {
    http_response_code(404);
    exit('File not found');
}

kbFileSend($row, ($_GET['action'] ?? '') === 'view');
