<?php

/*
 * Kept so existing links keep working. Every pair is linked in
 * agent/modals/link/link.php, here opened on its Vendors tab.
 */

$_GET['from'] = 'document';
$_GET['id'] = intval($_GET['document_id'] ?? 0);
$_GET['to'] = 'vendor';

require __DIR__ . '/../link/link.php';
