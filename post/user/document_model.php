<?php
$client_id = intval($_POST['client_id']);
$name = sanitizeInput($_POST['name']);
$folder = intval($_POST['folder']);
$description = sanitizeInput($_POST['description']);
$content = mysqli_real_escape_string($mysqli,$_POST['content']);
$content_raw = sanitizeInput($_POST['name'] . " " . str_replace("<", " <", $_POST['content']));
// Content Raw is used for FULL INDEX searching. Adding a space before HTML tags to allow spaces between newlines, bulletpoints, etc. for searching.
