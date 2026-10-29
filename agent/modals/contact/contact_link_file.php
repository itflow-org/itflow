<?php

/*
 * Kept so existing links keep working. Every pair is linked in
 * agent/modals/link/link.php, here opened on its Files tab.
 */

$_GET['from'] = 'contact';
$_GET['id'] = intval($_GET['id'] ?? 0);
$_GET['to'] = 'file';

require __DIR__ . '/../link/link.php';
