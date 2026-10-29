<?php

/*
 * Links made before 2.9.0 point here, with their key (and, for a login, ek)
 * in the query string. They open in the shared link page like any other -
 * js/guest_share.js reads the key from the query string when there is no #.
 */

require __DIR__ . '/share.php';
