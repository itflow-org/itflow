<?php
$date = sanitizeInput($_POST['date']);
$amount = floatval($_POST['amount']);
$account = intval($_POST['account']);
$vendor = intval($_POST['vendor']);
$category = intval($_POST['category']);
$description = sanitizeInput($_POST['description']);
$reference = sanitizeInput($_POST['reference']);
