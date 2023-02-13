<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$description = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['description'])));
$price = floatval($_POST['price']);
$category = intval($_POST['category']);
$tax = intval($_POST['tax']);
