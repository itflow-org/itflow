<?php

/*
 * A starter content item whole, for Preview under Pick which
 * (admin/includes/starter_content_pick.php) - ?pack=&item= (its
 * starterItemKey()), answered in JSON as its name and the preview's html.
 * Admins only, as the two pages that ask are.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/check_login.php';
enforceAdminPermission();

define('FROM_STARTER_CONTENT', true);
require_once __DIR__ . '/post/starter_content_model.php';
require_once __DIR__ . '/includes/starter_content_pick.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pack = (string) ($_GET['pack'] ?? '');
$key = (string) ($_GET['item'] ?? '');
$row = null;
if (isset(starterContentPacks()[$pack]) && in_array($pack, starterPreviewPacks(), true)) {
    foreach (starterContentRows($pack) as $candidate) {
        if (starterItemKey($pack, $candidate) === $key) {
            $row = $candidate;
            break;
        }
    }
}

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'There is no such starter content.']);
    exit;
}

echo json_encode(['title' => starterItemName($pack, $row), 'pack' => starterContentPacks()[$pack]['label'], 'html' => starterItemPreview($pack, $row)]);
