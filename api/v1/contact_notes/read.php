<?php
/*
 * API - Contact Notes - Read
 * GET /api/v1/contact_notes/read.php
 *
 * See api/v1/notes_body.php.
 */

require_once '../validate_api_key.php';
require_once '../require_get_method.php';

$note_kind = 'contact';
$note_op = 'read';
require_once '../notes_body.php';
