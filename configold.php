<?php

$dbhost = 'localhost';
$dbusername = 'itflow_dev';
$dbpassword = 'pizzaParty23!';
$database = 'itflow_dev';
$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database) or die('Database Connection Failed');
$config_app_name = 'ITFlow';
$config_base_url = 'dev.itflow.org';
$config_https_only = TRUE;
$repo_branch = 'develop';
$installation_id = 'bRsidH9yPPxgcPEgw9WDvoMXz3f8S1L9';
$config_enable_setup = 0;

