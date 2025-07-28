<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$role = intval($_POST['role']);
$force_mfa = intval($_POST['force_mfa'] ?? 0);
