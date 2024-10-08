<?php
$name = preg_replace("(^https?://)", "", sanitizeInput($_POST['name']));
$description = sanitizeInput($_POST['description']);
$registrar = intval($_POST['registrar']);
$dnshost = intval($_POST['dnshost']);
$webhost = intval($_POST['webhost']);
$mailhost = intval($_POST['mailhost']);
$expire = sanitizeInput($_POST['expire']);
$notes = sanitizeInput($_POST['notes']);

