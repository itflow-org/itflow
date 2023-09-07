<?php
$name = sanitizeInput($_POST['name']);
$address = sanitizeInput($_POST['address']);
$city = sanitizeInput($_POST['city']);
$state = sanitizeInput($_POST['state']);
$zip = sanitizeInput($_POST['zip']);
$country = sanitizeInput($_POST['country']);
$phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
$email = sanitizeInput($_POST['email']);
$website = sanitizeInput($_POST['website']);
