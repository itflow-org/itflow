<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$label = sanitizeInput($_POST['label']);
$type = sanitizeInput($_POST['type']);
