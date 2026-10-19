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
require_once __DIR__ . '/functions/certificate.php';
require_once __DIR__ . '/functions/network.php';
require_once __DIR__ . '/functions/user.php';
require_once __DIR__ . '/functions/roles.php';
require_once __DIR__ . '/functions/api_keys.php';
require_once __DIR__ . '/functions/asset_interface.php';
require_once __DIR__ . '/functions/rack.php';
require_once __DIR__ . '/functions/asset.php';
require_once __DIR__ . '/functions/contact.php';
require_once __DIR__ . '/functions/software.php';
require_once __DIR__ . '/functions/software_template.php';
require_once __DIR__ . '/functions/location.php';
require_once __DIR__ . '/functions/vendor.php';
require_once __DIR__ . '/functions/credential.php';
require_once __DIR__ . '/functions/links.php';
require_once __DIR__ . '/functions/auth.php';
require_once __DIR__ . '/functions/logging.php';
require_once __DIR__ . '/functions/audit_log.php';
require_once __DIR__ . '/functions/sign_in.php';
require_once __DIR__ . '/functions/app.php';
require_once __DIR__ . '/functions/email.php';
require_once __DIR__ . '/functions/email_preview.php';
require_once __DIR__ . '/functions/payments.php';
require_once __DIR__ . '/functions/sla.php';
require_once __DIR__ . '/functions/ai.php';
require_once __DIR__ . '/functions/export.php';
require_once __DIR__ . '/functions/calendar.php';
require_once __DIR__ . '/functions/backup.php';
require_once __DIR__ . '/functions/search.php';
require_once __DIR__ . '/functions/notifications.php';
require_once __DIR__ . '/functions/dashboard.php';
require_once __DIR__ . '/functions/reports.php';
require_once __DIR__ . '/functions/client_overview.php';
require_once __DIR__ . '/functions/tags.php';
require_once __DIR__ . '/functions/categories.php';
require_once __DIR__ . '/functions/ticket_fields.php';
require_once __DIR__ . '/functions/ticket.php';
require_once __DIR__ . '/functions/ticket_template.php';
require_once __DIR__ . '/functions/ticket_template_transfer.php';
require_once __DIR__ . '/functions/canned_response.php';
require_once __DIR__ . '/functions/canned_response_transfer.php';
require_once __DIR__ . '/functions/document_template.php';
require_once __DIR__ . '/functions/document_template_transfer.php';
require_once __DIR__ . '/functions/software_template_transfer.php';
require_once __DIR__ . '/functions/project.php';
require_once __DIR__ . '/functions/service.php';
require_once __DIR__ . '/functions/client_export.php';
require_once __DIR__ . '/functions/share.php';
require_once __DIR__ . '/functions/billing.php';
require_once __DIR__ . '/functions/products.php';
require_once __DIR__ . '/functions/billing_email.php';
require_once __DIR__ . '/functions/billing_pay.php';
require_once __DIR__ . '/functions/tax.php';
require_once __DIR__ . '/functions/expenses.php';
require_once __DIR__ . '/functions/expense_import.php';
require_once __DIR__ . '/functions/trips.php';
require_once __DIR__ . '/functions/income.php';
require_once __DIR__ . '/functions/income_import.php';
require_once __DIR__ . '/functions/accounts.php';
require_once __DIR__ . '/functions/reconcile.php';
require_once __DIR__ . '/functions/portal.php';
