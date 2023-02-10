<?php

if (file_exists("config.php")) {
    header("Location: login.php");

} else {
	header("Location: setup.php");
}

?>