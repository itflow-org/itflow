<?php

// Add a line to a quote - see api/v1/billing_lines.php for what it takes

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$billing_type = 'quote';
$billing_op = 'create';
require_once '../billing_lines.php';
