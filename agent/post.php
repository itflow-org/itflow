<?php
/*
 * ITFlow - Agent GET/POST request handler
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../includes/check_login.php";

// Only allow running post files via inclusion (prevents people/bots poking them directly)
define('FROM_POST_HANDLER', true);

// Load all agent module POST logic.
// TODO: selectively load per-module like admin does, keyed off request path (not referer)
// *_model.php is a RESERVED suffix: those files are not handlers, they are inline
// field-parsing fragments that read $_POST at include time, so the glob must not
// pull them in. A handler named *_model.php is silently never loaded - name entity
// handlers around it (admin/post/ai_models.php, not ai_model.php).
foreach (glob(__DIR__ . "/post/*.php") as $user_module) {
    if (!str_ends_with($user_module, '_model.php')) {
        require_once $user_module;
    }
}

// Logout is shared between portals
require_once __DIR__ . "/../post/logout.php";
require_once __DIR__ . "/../post/misc.php";

// Every handler above exits or redirects, so getting here means no handler claimed
// the request - a blank page and no trace of why. Log it; the usual cause is a
// handler file that never loaded.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unhandled = implode(', ', array_slice(array_keys($_POST), 0, 10));
    logApp('Request', 'warning', "Unhandled POST to agent/post.php - no handler matched. Fields: $unhandled");
}
