<?php
$iPod = stripos($_SERVER['HTTP_USER_AGENT'], "iPod");
$iPhone = stripos($_SERVER['HTTP_USER_AGENT'], "iPhone");
$iPad = stripos($_SERVER['HTTP_USER_AGENT'], "iPad");

$session_map_source = ($iPod || $iPhone || $iPad) ? "apple" : "google";

$session_mobile = isMobile();
