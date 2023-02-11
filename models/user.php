<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$email = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['email'])));
$default_company = intval($_POST['default_company']);
$role = intval($_POST['role']);
