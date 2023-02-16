<?php
$name = sanitizeInput($_POST['name']);
$type = intval($_POST['type']);
$color = sanitizeInput($_POST['color']);
$icon = sanitizeInput($_POST['icon']);
