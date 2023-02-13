<?php
$name = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['name'])));
$type = intval($_POST['type']);
$color = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['color'])));
$icon = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['icon'])));
