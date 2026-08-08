<?php

// Role check failed wording
DEFINE("WORDING_ROLECHECK_FAILED", "You are not permitted to do that!");

// Ceiling on what the mail queue will attach to a single message. Uploads are
// allowed up to 500 MB, which no mail server will accept - anything over this is
// left on the ticket for the recipient to download instead of being emailed.
DEFINE("MAX_EMAIL_ATTACHMENT_BYTES", 10 * 1024 * 1024);

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
require_once __DIR__ . '/functions/payments.php';
require_once __DIR__ . '/functions/sla.php';
require_once __DIR__ . '/functions/ai.php';
require_once __DIR__ . '/functions/export.php';
require_once __DIR__ . '/functions/calendar.php';
require_once __DIR__ . '/functions/backup.php';
