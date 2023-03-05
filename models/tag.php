<?php
$name = sanitizeInput($_POST['name']);
$type = intval($_POST['type']);
$color = preg_replace("/[^0-9a-zA-Z_]/", "", sanitizeInput($_POST['color']));
$icon = preg_replace("/[^0-9a-zA-Z_]/", "", sanitizeInput($_POST['icon']));
