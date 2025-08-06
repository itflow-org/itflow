<?php

if (!isset($config_enable_setup) || $config_enable_setup == 1) {
    header("Location: /setup");
    exit;
}
