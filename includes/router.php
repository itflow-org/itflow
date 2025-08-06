<?php

// URI Router
// Currently unused, but the idea is to dynamically prepend ../../ to asset paths (like includes, libraries, etc.)
// based on the current directory depth. This allows us to support deeply nested folder structures.

$depth = substr_count(trim($_SERVER['REQUEST_URI'], '/'), '/');
$path_prefix = str_repeat('../', $depth);
