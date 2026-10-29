<?php
/*
 * Client Portal
 * The company's tickets now live on tickets.php, under Everyone - an old link
 * here lands there, on the tab its status picked.
 */

$tab = ['Open' => '', 'Closed' => 'closed'][$_GET['status'] ?? 'Open'] ?? 'all';

header('Location: tickets.php' . ($tab !== '' ? '?tab=' . $tab : ''));
exit;
