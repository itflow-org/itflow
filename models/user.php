<?php
$name = sanitizeInput($_POST['name']);
$email = sanitizeInput($_POST['email']);
$default_company = intval($_POST['default_company']);
$role = intval($_POST['role']);
