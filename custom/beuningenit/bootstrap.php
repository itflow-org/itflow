<?php

define('BIT_CUSTOM', true);

$bit_base = __DIR__;

$bit_files = [
    $bit_base . '/handlers/ticket_items.php',
    $bit_base . '/ui/ticket_items_card.php',
    $bit_base . '/ui/tickets_bulk.php'
];

foreach ($bit_files as $bit_file) {
    if (file_exists($bit_file)) {
        require_once $bit_file;
    }
}
