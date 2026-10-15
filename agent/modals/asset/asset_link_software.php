<?php

/*
 * Kept so existing links keep working. Every pair is linked in
 * agent/modals/link/link.php, here opened on its Licenses tab - every license
 * the client has, with how many seats each has left.
 */

$_GET['from'] = 'asset';
$_GET['id'] = intval($_GET['id'] ?? 0);
$_GET['to'] = 'software';

require __DIR__ . '/../link/link.php';
