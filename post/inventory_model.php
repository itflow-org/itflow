<?php

$inventory_location_id = intval($_POST['inventory_location_id']);
$inventory_new_location_id = intval($_POST['inventory_new_location_id']);
$inventory_product_id = intval($_POST['inventory_product_id']);
$inventory_quantity = intval($_POST['inventory_quantity']);


$inventory_location_name = sanitizeInput($_POST['name']);
$inventory_location_description = sanitizeInput($_POST['description']);
$inventory_location_address = sanitizeInput($_POST['address']);
$inventory_location_city = sanitizeInput($_POST['city']);
$inventory_location_state = sanitizeInput($_POST['state']);
$inventory_location_zip = sanitizeInput($_POST['zip']);
$inventory_location_country = sanitizeInput($_POST['country']);
$inventory_location_user_id = intval($_POST['user_id']);
