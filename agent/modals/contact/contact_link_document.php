<?php

/*
 * Kept so existing links keep working. Every pair is linked in
 * agent/modals/link/link.php, here opened on its Documents tab.
 */

$_GET['from'] = 'contact';
$_GET['id'] = intval($_GET['id'] ?? 0);
$_GET['to'] = 'document';

require __DIR__ . '/../link/link.php';
