<?php
$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$role = intval($_POST['role']);
$force_mfa = intval($_POST['force_mfa'] ?? 0);
