<?php
/*
 * API - Contact Notes - Unarchive
 * POST /api/v1/contact_notes/unarchive.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_post_method.php';

$note_kind = 'contact';
$note_op = 'unarchive';
require_once '../notes_body.php';
