<?php
$date = sanitizeInput($_POST['date']);
$category = intval($_POST['category']);
$scope = sanitizeInput($_POST['scope']);
$invoice_discount = floatval($_POST['invoice_discount']);