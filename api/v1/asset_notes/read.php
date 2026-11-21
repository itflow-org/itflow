<?php
/*
 * API - Asset Notes - Read
 * GET /api/v1/asset_notes/read.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$note_kind = 'asset';
$note_op = 'read';
require_once '../notes_body.php';
