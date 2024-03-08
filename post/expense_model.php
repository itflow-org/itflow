<?php
$date = sanitizeInput($_POST['date']);
$amount = floatval($_POST['amount']);
$account = intval($_POST['account']);
$vendor = intval($_POST['vendor']);
$client = intval($_POST['client']);
$category = intval($_POST['category']);
$description = sanitizeInput($_POST['description']);
$reference = sanitizeInput($_POST['reference']);
$product = intval($_POST['product']);
$product_quantity = intval($_POST['product_quantity']);
