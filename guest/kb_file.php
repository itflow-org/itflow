<?php

/*
 * GET /guest/kb_file.php?file_id= - a file that goes with an article on the
 * public help center: only while it is open (kbPublicOn()), and only an
 * article set for anyone to read (kbPublicSql()).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/load_global_settings.php';

header('X-Robots-Tag: noindex');

$row = kbPublicOn() ? kbArticleFileRow($_GET['file_id'] ?? 0) : null;
if (!$row || !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM kb_articles WHERE kb_article_id = " . intval($row['kb_article_file_kb_article_id']) . " AND " . kbPublicSql()))) {
    http_response_code(404);
    exit('File not found');
}

kbFileSend($row, false);
