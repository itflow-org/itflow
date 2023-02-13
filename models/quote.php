<?php
$date = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['date'])));
$category = intval($_POST['category']);
$scope = trim(strip_tags(mysqli_real_escape_string($mysqli, $_POST['scope'])));
