<?php
/*
 * API - Asset Notes - Archive
 * POST /api/v1/asset_notes/archive.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$note_kind = 'asset';
$note_op = 'archive';
require_once '../notes_body.php';
