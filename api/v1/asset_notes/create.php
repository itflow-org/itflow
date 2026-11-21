<?php
/*
 * API - Asset Notes - Create
 * POST /api/v1/asset_notes/create.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$note_kind = 'asset';
$note_op = 'create';
require_once '../notes_body.php';
