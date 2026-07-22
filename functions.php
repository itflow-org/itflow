<?php

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

// functions.php is now a loader. Helper functions live in topical files
// under functions/ - see each file's header comment for scope.

require_once __DIR__ . '/functions/security.php';
require_once __DIR__ . '/functions/sanitize.php';
require_once __DIR__ . '/functions/format.php';
require_once __DIR__ . '/functions/request.php';
require_once __DIR__ . '/functions/files.php';
require_once __DIR__ . '/functions/domain.php';
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/logging.php';
require_once __DIR__ . '/functions/app.php';
require_once __DIR__ . '/functions/db.php';
