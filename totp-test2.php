<?php

require_once("rfc6238.php");


$secretkey = "";

$gen = TokenAuth6238::getTokenCode($secretkey,$rangein30s = 3);

echo $gen;

?>