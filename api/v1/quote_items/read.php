<?php

// Read a quote's lines - see api/v1/billing_lines.php for what it takes

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$billing_type = 'quote';
$billing_op = 'read';
require_once '../billing_lines.php';
