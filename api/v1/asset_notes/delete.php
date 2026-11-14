<?php
/*
 * API - Asset Notes - Delete
 * POST /api/v1/asset_notes/delete.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$note_kind = 'asset';
$note_op = 'delete';
require_once '../notes_body.php';
