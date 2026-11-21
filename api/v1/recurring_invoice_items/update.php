<?php

// Change a line on a recurring invoice - see api/v1/billing_lines.php for what it takes

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$billing_type = 'recurring';
$billing_op = 'update';
require_once '../billing_lines.php';
