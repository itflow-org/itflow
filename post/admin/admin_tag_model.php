<?php
$name = sanitizeInput($_POST['name']);
$type = intval($_POST['type']);
$color = sanitizeInput($_POST['color']);
$icon = preg_replace("/[^0-9a-zA-Z-]/", "", sanitizeInput($_POST['icon']));
