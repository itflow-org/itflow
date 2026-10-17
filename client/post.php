<?php
/*
 * Client Portal
 * Process GET/POST requests
 */

require_once '../config.php';
require_once '../includes/load_global_settings.php';
require_once '../functions.php';
require_once 'includes/check_login.php';
require_once 'functions.php';

if (isset($_POST['add_ticket'])) {

    validateCSRFToken();

    // A subject and something in the details - the editor checks this too, but
    // a page that never loaded it would send them blank
    $raw_details = (string) ($_POST['details'] ?? '');
    if (trim((string) ($_POST['subject'] ?? '')) === '' || portalHtmlIsEmpty($raw_details)) {
        flashAlert("Give the ticket a subject and tell us what is happening", 'error');
        redirect('ticket_add.php');
    }

    $subject = escapeSql(mb_substr(trim($_POST['subject']), 0, 500));
    $details = mysqli_real_escape_string($mysqli, $raw_details);
    $category = intval($_POST['category'] ?? 0);
    $asset = intval($_POST['asset'] ?? 0);

    // Only a ticket category that is in use
    if ($category && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM categories WHERE category_id = $category AND category_type = 'Ticket' AND category_archived_at IS NULL"))) {
        $category = 0;
    }

    // Only one of their company's devices - their own, or any of the company's
    // for a primary or technical contact, as the form offers
    if ($asset && !mysqli_num_rows(mysqli_query($mysqli, "SELECT 1 FROM assets WHERE asset_id = $asset AND asset_client_id = $session_client_id AND asset_archived_at IS NULL"
        . (portalSeesAllTickets() ? '' : " AND asset_contact_id = $session_contact_id")))) {
        $asset = 0;
    }

    // Get settings from load_global_settings.php
    $config_ticket_prefix = escapeSql($config_ticket_prefix);
    $config_ticket_from_name = escapeSql($config_ticket_from_name);
    $config_ticket_from_email = escapeSql($config_ticket_from_email);
    $config_base_url = escapeSql($config_base_url);
    $config_ticket_new_ticket_notification_email = filter_var($config_ticket_new_ticket_notification_email, FILTER_VALIDATE_EMAIL);

    //Generate a unique URL key for clients to access
    $url_key = randomString(32);

    // Ensure priority is one of the four allowed values (as can be user defined)
    if ($_POST['priority'] !== "Low" && $_POST['priority'] !== "Medium" && $_POST['priority'] !== "High" && $_POST['priority'] !== "Urgent") {
        $priority = "Medium";
    } else {
        $priority = escapeSql($_POST['priority']);
    }

    // Atomically increment and get the new ticket number
    mysqli_query($mysqli, "
        UPDATE settings
        SET
            config_ticket_next_number = LAST_INSERT_ID(config_ticket_next_number),
            config_ticket_next_number = config_ticket_next_number + 1
        WHERE company_id = 1
    ");

    $ticket_number = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO tickets SET ticket_prefix = '$config_ticket_prefix', ticket_number = $ticket_number, ticket_source = 'Portal', ticket_category = $category, ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_status = 1, ticket_billable = $config_ticket_default_billable, ticket_created_by = $session_user_id, ticket_contact_id = $session_contact_id, ticket_asset_id = $asset, ticket_url_key = '$url_key', ticket_client_id = $session_client_id");
    $ticket_id = mysqli_insert_id($mysqli);
    applyTicketSla($ticket_id);

    // Screenshots and files sent with it belong to the ticket itself
    saveTicketAttachments($ticket_id, null, 'file');

    // Notify agent DL of the new ticket, if populated with a valid email
    if ($config_ticket_new_ticket_notification_email) {

        $client_name = escapeSql($session_client_name);

        $email_subject = "ITFlow - New Ticket - $client_name: $subject";
        $email_body = "Hello, <br><br>This is a notification that a new ticket has been raised in ITFlow. <br>Client: $client_name<br>Priority: $priority<br>Link: https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id&client_id=$session_client_id <br><br><b>$subject</b><br>$details";

        // Queue Mail
        $data = [
            [
                'from' => $config_ticket_from_email,
                'from_name' => $config_ticket_from_name,
                'recipient' => $config_ticket_new_ticket_notification_email,
                'recipient_name' => $config_ticket_from_name,
                'subject' => $email_subject,
                'body' => $email_body,
            ]
        ];
        addToMailQueue($data);
        }

    // Custom action/notif handler
    triggerCustomAction('ticket_create', $ticket_id);

    logAudit("Ticket", "Create", "$session_contact_name created ticket $config_ticket_prefix$ticket_number - $subject from the client portal", $session_client_id, $ticket_id);

    redirect("ticket.php?id=" . $ticket_id);

}

if (isset($_POST['add_ticket_comment'])) {

    validateCSRFToken();

    $ticket_id = intval($_POST['ticket_id']);
    $raw_comment = (string) ($_POST['comment'] ?? '');
    $comment = mysqli_real_escape_string($mysqli, $raw_comment);

    // Nothing but empty paragraphs from the editor is no reply - a picture on
    // its own is
    if (portalHtmlIsEmpty($raw_comment)) {
        flashAlert("Write a reply first", 'error');
        redirect();
    }

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Add the comment
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$comment', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        $ticket_reply_id = mysqli_insert_id($mysqli);

        // Update Ticket Last Response Field & set ticket to open as client has replied
        $original_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_status FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
        $original_ticket_status = intval($original_row['ticket_status'] ?? 0);

        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2 WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");
        syncTicketSlaClock($ticket_id);

        // Only record the reopen when the ticket was not already open
        if ($original_ticket_status !== 2) {
            logTicketHistory($ticket_id, "$session_contact_name replied from the client portal, reopening the ticket");
        }


        // Get ticket details &  Notify the assigned tech (if any)
        $ticket_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name, ticket_assigned_to, ticket_number, ticket_subject FROM tickets LEFT JOIN clients ON ticket_client_id = client_id WHERE ticket_id = $ticket_id LIMIT 1"));

        $ticket_number = intval($ticket_details['ticket_number']);
        $ticket_assigned_to = intval($ticket_details['ticket_assigned_to']);
        $ticket_subject = escapeSql($ticket_details['ticket_subject']);
        $client_name = escapeSql($ticket_details['client_name']);

        if ($ticket_details && $ticket_assigned_to !== 0) {

            // Get tech details
            $tech_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT user_email, user_name FROM users WHERE user_id = $ticket_assigned_to LIMIT 1"));
            $tech_email = escapeSql($tech_details['user_email']);
            $tech_name = escapeSql($tech_details['user_name']);

            $subject = "$config_app_name Ticket updated - [$config_ticket_prefix$ticket_number] $ticket_subject";
            $body    = "Hello $tech_name,<br><br>A new reply has been added to the below ticket, check ITFlow for full details.<br><br>Client: $client_name<br>Ticket: $config_ticket_prefix$ticket_number<br>Subject: $ticket_subject<br><br>https://$config_base_url/agent/ticket.php?ticket_id=$ticket_id&client_id=$session_client_id";

            $data = [
                [
                    'from' => $config_ticket_from_email,
                    'from_name' => $config_ticket_from_name,
                    'recipient' => $tech_email,
                    'recipient_name' => $tech_name,
                    'subject' => $subject,
                    'body' => $body
                ]
            ];

            addToMailQueue($data);

        }

        // Store any attached files against this reply
        saveTicketAttachments($ticket_id, $ticket_reply_id, 'file');

        // Custom action/notif handler
        triggerCustomAction('ticket_reply_client', $ticket_id);

        // Redirect back to original page
        redirect();

    } else {
        // The client does not have access to this ticket
        redirect("post.php?logout");
    }
}



if (isset($_POST['set_contact_phone'])) {

    validateCSRFToken();

    /*
     * SCOPING: as with the PIN above, the row updated is the logged-in
     * contact's own, from the session. No contact_id parameter exists on this
     * handler, so there is nothing to point at somebody else's record with.
     *
     * Sanitising exactly as agent/post/contact_model.php does - digits only for
     * every phone field - so a number typed in the portal is stored in the same
     * shape as one typed by an agent and formatPhoneNumber() renders both the
     * same way. Anything else would make the portal the odd one out.
     */
    $phone_country_code = preg_replace("/[^0-9]/", '', $_POST['phone_country_code'] ?? '');
    $phone = preg_replace("/[^0-9]/", '', $_POST['phone'] ?? '');
    $extension = preg_replace("/[^0-9]/", '', $_POST['extension'] ?? '');
    $mobile_country_code = preg_replace("/[^0-9]/", '', $_POST['mobile_country_code'] ?? '');
    $mobile = preg_replace("/[^0-9]/", '', $_POST['mobile'] ?? '');

    // Columns are varchar(200), country codes varchar(10)
    $phone_country_code = substr($phone_country_code, 0, 10);
    $mobile_country_code = substr($mobile_country_code, 0, 10);
    $phone = substr($phone, 0, 200);
    $extension = substr($extension, 0, 200);
    $mobile = substr($mobile, 0, 200);

    // A country code on its own is not a number - drop it rather than store a
    // dangling code the formatter would try to render
    if (empty($phone)) {
        $phone_country_code = '';
        $extension = '';
    }
    if (empty($mobile)) {
        $mobile_country_code = '';
    }

    mysqli_query($mysqli, "UPDATE contacts SET
        contact_phone_country_code = '$phone_country_code',
        contact_phone = '$phone',
        contact_extension = '$extension',
        contact_mobile_country_code = '$mobile_country_code',
        contact_mobile = '$mobile'
        WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id");

    logAudit("Contact", "Edit", "Client contact $session_contact_name updated their phone numbers in the client portal", $session_client_id, $session_contact_id);

    flashAlert("Phone numbers updated");

    redirect('profile.php');

}

if (isset($_POST['set_contact_pin'])) {

    validateCSRFToken();

    /*
     * SCOPING: the row updated is the logged-in contact's own, taken from the
     * session. There is no contact_id parameter on this handler by design, so
     * there is nothing for a contact to point at somebody else's record with.
     *
     * No capability gate: the PIN belongs to the contact, not to a portal
     * section, so every signed-in contact manages their own - same as the
     * password change above.
     */
    // The PIN is what we read back to verify a caller, so changing it is
    // re-authenticated for password logins. SSO contacts are exempt: they have
    // no local password to check, and the identity provider already did this.
    if (!portalReauthenticate($_POST['current_password'] ?? '')) {
        flashAlert("That password was not right - your PIN has not been changed", 'error');
        redirect('profile.php');
    }

    // contact_pin is varchar(255) - trim to fit rather than let an over-long
    // value error out under strict mode.
    //
    // escapeSql() runs strip_tags() before escaping, so the length has to be
    // re-checked AFTER it: a PIN of "<1234>" passed a check on the raw input,
    // came out of strip_tags() as an empty string, and the UPDATE below then
    // silently WIPED the contact's PIN while flashing "Phone PIN updated".
    $pin = escapeSql(substr(trim($_POST['pin'] ?? ''), 0, 255));

    if (strlen($pin) < 4) {
        flashAlert("Your PIN needs to be at least 4 characters, and cannot contain < or >", 'error');
        redirect('profile.php');
    }

    mysqli_query($mysqli, "UPDATE contacts SET contact_pin = '$pin' WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id");

    // The PIN itself never goes in the log - it is a verification secret, and
    // an audit trail an agent can read would defeat the point of having one
    logAudit("Contact", "Edit", "Client contact $session_contact_name set their phone PIN in the client portal", $session_client_id, $session_contact_id);

    flashAlert("Phone PIN updated");

    redirect('profile.php');

}

if (isset($_GET['approve_ticket_task'])) {

    validateCSRFToken();

    $task_id = intval($_GET['approve_ticket_task']);
    $approval_id = intval($_GET['approval_id']);
    $url_key = escapeSql($_GET['approval_url_key']);

    $approval_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT approval_created_by, approval_required_user_id, approval_scope, approval_type, task_name,
        task_ticket_id FROM task_approvals LEFT JOIN tasks on task_id = approval_task_id WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_url_key = '$url_key' AND approval_status = 'pending' AND approval_scope = 'client'"));

    $task_name = escapeSql($approval_row['task_name']);
    $scope = escapeHtml($approval_row['approval_scope']);
    $type = escapeHtml($approval_row['approval_type']);
    $required_user = intval($approval_row['approval_required_user_id']);
    $created_by = intval($approval_row['approval_created_by']);
    $ticket_id = intval($approval_row['task_ticket_id']);

    if (!$approval_row) {
        flashAlert("Cannot find/approve that task", 'warning');
        redirect();
        exit;
    }

    // Approve
    mysqli_query($mysqli, "UPDATE task_approvals SET approval_status = 'approved', approval_approved_by = $session_user_id WHERE approval_id = $approval_id AND approval_task_id = $task_id AND approval_url_key = '$url_key' AND approval_status = 'pending' AND approval_scope = 'client'");


    // Notify tech
    mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$session_contact_email approved ticket task $task_name', notification_action = 'ticket.php?ticket_id=$ticket_id&client_id=$session_client_id', notification_client_id = $session_client_id, notification_user_id = $created_by");
    // TODO: Email agent

    // Logging
    logAudit("Task", "Edit", "Contact $session_contact_email approved task $task_name (approval $approval_id)", $session_client_id, $task_id);

    flashAlert("Task Approved");
    redirect();

}

if (isset($_POST['add_ticket_feedback'])) {

    validateCSRFToken();

    $ticket_id = intval($_POST['ticket_id']);
    // Only the two ratings the portal offers - the reports count Good against Bad
    $feedback = $_POST['add_ticket_feedback'] === 'Bad' ? 'Bad' : 'Good';

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Closed")) {

        // Add feedback
        mysqli_query($mysqli, "UPDATE tickets SET ticket_feedback = '$feedback' WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id LIMIT 1");

        // Notify on bad feedback
        if ($feedback == "Bad") {
            $ticket_details = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_number FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));
            $ticket_number = intval($ticket_details['ticket_number']);
            appNotify("Feedback", "$session_contact_name rated ticket $config_ticket_prefix$ticket_number as bad (ID: $ticket_id)", "/agent/ticket.php?ticket_id=$ticket_id&client_id=$session_client_id", $session_client_id, $ticket_id);
        }

        // Custom action/notif handler
        triggerCustomAction('ticket_feedback', $ticket_id);

        // Redirect
        redirect();
    } else {
        // The client does not have access to this ticket
        redirect("post.php?logout");
    }

}

if (isset($_GET['resolve_ticket'])) {

    validateCSRFToken();

    $ticket_id = intval($_GET['resolve_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_number, ticket_prefix FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Resolve the ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 4, ticket_resolved_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");
        setTicketResolutionSlaMet($ticket_id);
        syncTicketSlaClock($ticket_id);
        logTicketHistory($ticket_id, "$session_contact_name resolved the ticket from the client portal");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket resolved by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAudit("Ticket", "Edit", "$session_contact_name marked ticket $ticket_prefix$ticket_number as resolved in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        triggerCustomAction('ticket_resolve', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }

}

if (isset($_GET['reopen_ticket'])) {

    validateCSRFToken();

    $ticket_id = intval($_GET['reopen_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_number, ticket_prefix FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Re-open ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 2, ticket_resolved_at = NULL WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");
        resetTicketResolutionSla($ticket_id);
        syncTicketSlaClock($ticket_id);
        logTicketHistory($ticket_id, "$session_contact_name reopened the ticket from the client portal");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket reopened by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAudit("Ticket", "Edit", "$session_contact_name reopend ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        triggerCustomAction('ticket_update', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }

}

if (isset($_GET['close_ticket'])) {

    validateCSRFToken();

    $ticket_id = intval($_GET['close_ticket']);

    // Get ticket details for logging
    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT ticket_number, ticket_prefix FROM tickets WHERE ticket_id = $ticket_id LIMIT 1"));

    $ticket_prefix = escapeSql($row['ticket_prefix']);
    $ticket_number = intval($row['ticket_number']);

    // Verify the contact has access to the provided ticket ID
    if (verifyContactTicketAccess($ticket_id, "Open")) {

        // Fully close ticket
        mysqli_query($mysqli, "UPDATE tickets SET ticket_status = 5, ticket_closed_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $session_client_id");
        syncTicketSlaClock($ticket_id);
        logTicketHistory($ticket_id, "$session_contact_name closed the ticket from the client portal");

        // Add reply
        mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = 'Ticket closed by $session_contact_name.', ticket_reply_type = 'Client', ticket_reply_by = $session_contact_id, ticket_reply_ticket_id = $ticket_id");

        logAudit("Ticket", "Edit", "$session_contact_name closed ticket $ticket_prefix$ticket_number in the client portal", $session_client_id, $ticket_id);

        // Custom action/notif handler
        triggerCustomAction('ticket_close', $ticket_id);

        redirect("ticket.php?id=" . $ticket_id);

    } else {
        // The client does not have access to this ticket - send them home
        redirect("index.php");
    }
}


if (isset($_GET['export_statement_pdf'])) {

    validateCSRFToken();

    portalBillingGate();

    /*
     * SCOPING: the client is taken from the session, never from the request.
     * There is no client_id parameter on this handler by design - a contact
     * cannot ask for another company's statement because there is nothing to
     * ask with.
     *
     * The same statement the agents email (functions/billing_email.php): the
     * open invoices, or with paid=1 everything from the last twelve months.
     */
    $with_paid = !empty($_GET['paid']);
    $statement = billingStatement($session_client_id, $with_paid ? date('Y-m-d', strtotime('-12 months')) : '', '', $with_paid);

    if (!$statement['invoices']) {
        flashAlert("There is nothing to put on a statement", 'error');
        redirect("statement.php");
    }

    billingPdfSend(billingStatementPdf($statement), true);

}

if (isset($_GET['logout'])) {

    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);

    session_unset();
    session_destroy();

    redirect('/login.php');

}

if (isset($_POST['edit_profile'])) {

    validateCSRFToken();

    $new_password = $_POST['new_password'];

    // Without this a hijacked session could set a new password without knowing
    // the old one, locking the real contact out of their own portal.
    if (!empty($new_password) && !portalReauthenticate($_POST['current_password'] ?? '')) {
        flashAlert("That password was not right - your password has not been changed", 'error');
        redirect('profile.php');
    }

    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        mysqli_query($mysqli, "UPDATE users SET user_password = '$password_hash' WHERE user_id = $session_user_id");

        // Logging
        logAudit("Contact", "Edit", "Client contact $session_contact_name edited their profile/password in the client portal", $session_client_id, $session_contact_id);
    }

    redirect('index.php');

}

if (isset($_POST['portal_contact_save'])) {

    validateCSRFToken();

    /*
     * Add a person, or change one - the primary contact only, and never their
     * own record (Your account is for that) or another primary contact's. A
     * contact with a portal login has it follow their name and email, and the
     * email must not sign in to any other account.
     */
    enforceContactCan('contacts_manage');

    $contact_id = intval($_POST['contact_id'] ?? 0);
    $existing = null;
    if ($contact_id) {
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_primary, contact_user_id, user_id FROM contacts
            LEFT JOIN users ON user_id = contact_user_id
            WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contact_archived_at IS NULL LIMIT 1"));
        if (!$existing || intval($existing['contact_primary']) || $contact_id === $session_contact_id) {
            flashAlert("That contact can't be changed here", 'error');
            redirect('contacts.php');
        }
    }
    $back = $contact_id ? "contact.php?id=$contact_id" : 'contact.php?new=1';

    $digits = function ($field, $length = 200) {
        return substr(preg_replace('/[^0-9]/', '', (string) ($_POST[$field] ?? '')), 0, $length);
    };
    $in = [
        'contact_id' => $contact_id,
        'contact_name' => trim(mb_substr((string) ($_POST['contact_name'] ?? ''), 0, 200)),
        'contact_title' => trim(mb_substr((string) ($_POST['contact_title'] ?? ''), 0, 200)),
        'contact_email' => trim(mb_substr((string) ($_POST['contact_email'] ?? ''), 0, 200)),
        'contact_department' => trim(mb_substr((string) ($_POST['contact_department'] ?? ''), 0, 200)),
        'contact_phone' => $digits('contact_phone'),
        'contact_phone_country_code' => $digits('contact_phone_country_code', 10),
        'contact_extension' => $digits('contact_extension', 20),
        'contact_mobile' => $digits('contact_mobile'),
        'contact_mobile_country_code' => $digits('contact_mobile_country_code', 10),
        'contact_billing' => !empty($_POST['contact_billing']) ? 1 : 0,
        'contact_technical' => !empty($_POST['contact_technical']) ? 1 : 0,
        'invite' => !$contact_id && !empty($_POST['invite']) ? 1 : 0,
        'method' => (string) ($_POST['method'] ?? 'local'),
    ];
    // A country code or extension on its own is not a number
    if ($in['contact_phone'] === '') {
        $in['contact_phone_country_code'] = $in['contact_extension'] = '';
    }
    if ($in['contact_mobile'] === '') {
        $in['contact_mobile_country_code'] = '';
    }

    $error = '';
    if ($in['contact_name'] === '') {
        $error = 'A contact needs a name';
    } elseif (!filter_var($in['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $error = ($in['contact_email'] === '' ? 'Nothing' : escapeHtml($in['contact_email'])) . ' is not an email address';
    } elseif (($in['invite'] || intval($existing['user_id'] ?? 0)) && userCheckIdentity(['name' => $in['contact_name'], 'email' => $in['contact_email']], intval($existing['user_id'] ?? 0), $identity_error) === null) {
        $error = rtrim($identity_error, '.');
    }
    if ($error !== '') {
        $_SESSION['portal_contact_form'] = $in;
        flashAlert($error, 'error');
        redirect($back);
    }

    $set = [];
    foreach (['contact_name', 'contact_title', 'contact_email', 'contact_department', 'contact_phone', 'contact_phone_country_code', 'contact_extension', 'contact_mobile', 'contact_mobile_country_code'] as $field) {
        $set[] = "$field = '" . escapeSql($in[$field]) . "'";
    }
    $set[] = "contact_billing = {$in['contact_billing']}";
    $set[] = "contact_technical = {$in['contact_technical']}";
    $set = implode(', ', $set);
    $name_sql = escapeSql($in['contact_name']);

    if ($contact_id) {
        mysqli_query($mysqli, "UPDATE contacts SET $set WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contact_archived_at IS NULL AND contact_primary = 0");
        if (intval($existing['user_id'])) {
            mysqli_query($mysqli, "UPDATE users SET user_name = '$name_sql', user_email = '" . escapeSql($in['contact_email']) . "' WHERE user_id = " . intval($existing['user_id']) . " AND user_type = 2");
        }
        logAudit("Contact", "Edit", "Client contact $session_contact_name changed contact $name_sql in the client portal", $session_client_id, $contact_id);
        triggerCustomAction('contact_update', $contact_id);
        flashAlert("Saved <strong>" . escapeHtml($in['contact_name']) . "</strong>");
        redirect("contact.php?id=$contact_id");
    }

    mysqli_query($mysqli, "INSERT INTO contacts SET $set, contact_client_id = $session_client_id, contact_user_id = 0");
    $contact_id = intval(mysqli_insert_id($mysqli));
    logAudit("Contact", "Create", "Client contact $session_contact_name added contact $name_sql in the client portal", $session_client_id, $contact_id);
    appNotify("Contact", "$session_contact_name added $name_sql as a contact for $session_client_name in the client portal", "/agent/contact.php?client_id=$session_client_id&contact_id=$contact_id", $session_client_id, $contact_id);
    triggerCustomAction('contact_create', $contact_id);

    $message = "Added <strong>" . escapeHtml($in['contact_name']) . "</strong>";
    if ($in['invite']) {
        $invite = portalContactInvite($contact_id, $in['method'], (string) ($contact['contact_name'] ?? ''));
        if (!$invite['ok']) {
            $message .= ' - but their portal access could not be set up: ' . escapeHtml($invite['error']);
        } else {
            logAudit("Contact", "Edit", "Client contact $session_contact_name gave $name_sql portal access in the client portal", $session_client_id, $contact_id);
            $message .= $invite['emailed'] ? ' and emailed them an invite to the portal' : ' and gave them portal access - email is not set up here, so ask us to send them how to sign in';
        }
    }
    flashAlert($message);
    redirect("contact.php?id=$contact_id");

}

if (isset($_POST['portal_contact_access']) || isset($_POST['portal_contact_left'])) {

    validateCSRFToken();

    // A contact's portal access - give it with an invite, send that again, or turn it off;
    // and someone who has left: access off, then a ticket for the rest. The primary contact only.
    enforceContactCan('contacts_manage');

    $contact_id = intval($_POST['contact_id'] ?? 0);
    $target = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_primary FROM contacts
        WHERE contact_id = $contact_id AND contact_client_id = $session_client_id AND contact_archived_at IS NULL LIMIT 1"));
    if (!$target || intval($target['contact_primary']) || $contact_id === $session_contact_id) {
        flashAlert("That contact's access can't be changed here", 'error');
        redirect('contacts.php');
    }
    $name = (string) $target['contact_name'];
    $name_sql = escapeSql($name);
    $agent_link = "/agent/contact.php?client_id=$session_client_id&contact_id=$contact_id";

    if (isset($_POST['portal_contact_left'])) {
        portalContactAccessOff($contact_id);
        logAudit("Contact", "Edit", "Client contact $session_contact_name said $name_sql has left and turned off their portal access", $session_client_id, $contact_id);
        appNotify("Contact", "$session_contact_name says $name_sql has left $session_client_name - their portal access is off", $agent_link, $session_client_id, $contact_id);
        flashAlert(escapeHtml($name) . "'s portal access is off. Tell us below what else to close down - their email, accounts and devices - and their last day.");
        redirect('ticket_add.php?' . http_build_query(['subject' => "Offboarding: $name has left"]));
    }

    if ($_POST['portal_contact_access'] === 'off') {
        portalContactAccessOff($contact_id);
        logAudit("Contact", "Edit", "Client contact $session_contact_name turned off portal access for $name_sql", $session_client_id, $contact_id);
        appNotify("Contact", "$session_contact_name turned off client portal access for $name_sql", $agent_link, $session_client_id, $contact_id);
        flashAlert(escapeHtml($name) . "'s portal access is off");
        redirect("contact.php?id=$contact_id");
    }

    $invite = portalContactInvite($contact_id, (string) ($_POST['method'] ?? 'local'), (string) ($contact['contact_name'] ?? ''));
    if (!$invite['ok']) {
        flashAlert(escapeHtml($invite['error']), 'error');
        redirect("contact.php?id=$contact_id");
    }
    logAudit("Contact", "Edit", "Client contact $session_contact_name gave $name_sql portal access and sent an invite", $session_client_id, $contact_id);
    appNotify("Contact", "$session_contact_name gave $name_sql client portal access", $agent_link, $session_client_id, $contact_id);
    flashAlert($invite['emailed'] ? "Invite emailed to <strong>" . escapeHtml($target['contact_email']) . "</strong>" : escapeHtml($name) . " can sign in now - email is not set up here, so ask us to send them how");
    redirect("contact.php?id=$contact_id");

}

if (isset($_POST['portal_quote_answer'])) {

    validateCSRFToken();

    portalBillingGate();

    // Accept or decline a quote, signed with a name and with a note for us - billingQuoteAnswer() in functions/billing.php,
    // which the quote's guest link uses too
    $quote_id = intval($_POST['quote_id'] ?? 0);
    $accept = ($_POST['answer'] ?? '') === 'accept';
    $name = trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 200));
    $note = trim(mb_substr((string) ($_POST['note'] ?? ''), 0, 2000));

    $quote = billingRow('quote', $quote_id, false);
    if (!$quote || intval($quote['client_id']) !== $session_client_id || $quote['quote_status'] === 'Draft') {
        flashAlert("That quote isn't here", 'error');
        redirect('quotes.php');
    }
    if ($name === '' || ($accept && empty($_POST['authorised']))) {
        flashAlert($name === '' ? 'Put your name to your answer' : 'Tick to confirm you can accept the quote', 'error');
        redirect("quote.php?id=$quote_id");
    }

    $answer = billingQuoteAnswer($quote_id, $accept, ['name' => $name, 'note' => $note]);
    if (!$answer['ok']) {
        flashAlert($answer['error'], 'error');
        redirect("quote.php?id=$quote_id");
    }

    $number = escapeHtml(billingNumber('quote', $quote));
    flashAlert($accept ? "Thank you - quote <strong>$number</strong> is accepted and our team has been told" : "Quote <strong>$number</strong> declined - thank you for letting us know");

    redirect("quote.php?id=$quote_id");

}

if (isset($_POST['portal_quote_file'])) {

    validateCSRFToken();

    portalBillingGate();

    // A signed copy or purchase order for an accepted quote - billingQuoteAddFiles() in functions/billing.php
    $quote_id = intval($_POST['quote_id'] ?? 0);
    $quote = billingRow('quote', $quote_id, false);
    if (!$quote || intval($quote['client_id']) !== $session_client_id || $quote['quote_status'] !== 'Accepted') {
        flashAlert("Files can only be sent with an accepted quote", 'error');
        redirect($quote ? "quote.php?id=$quote_id" : 'quotes.php');
    }

    $upload = billingQuoteAddFiles($quote_id, $_FILES['file'] ?? [], ['name' => (string) ($contact['contact_name'] ?? '')]);
    if ($upload['added'] && !$upload['failed']) {
        flashAlert('Sent - thank you. ' . escapeHtml(implode(', ', $upload['added'])) . ' is with us now');
    } elseif ($upload['added']) {
        flashAlert('Sent ' . escapeHtml(implode(', ', $upload['added'])) . ' - but ' . $upload['failed'] . ' could not be taken. Only PDFs can be sent', 'error');
    } else {
        flashAlert('That file could not be taken - only PDFs can be sent', 'error');
    }

    redirect("quote.php?id=$quote_id");

}

if (isset($_POST['portal_pay'])) {

    validateCSRFToken();

    portalBillingGate();

    /*
     * Pay one or more invoices by card - functions/billing_pay.php. The choice
     * is checked again here: the invoices must be this client's and still
     * owed, in one currency and under the card limit. A saved card is charged
     * there and then; a new card goes on to the checkout page (pay.php).
     */
    $invoice_ids = array_map('intval', (array) ($_POST['invoice_ids'] ?? []));
    $method = (string) ($_POST['method'] ?? 'new');
    $back = 'unpaid_invoices.php' . ($invoice_ids ? '?' . http_build_query(['select' => implode(',', $invoice_ids)]) : '');

    if (!billingStripeProvider()) {
        flashAlert('Card payments are not set up', 'error');
        redirect('invoices.php');
    }

    $selection = billingPaySelection($session_client_id, $invoice_ids, $error);
    if (!$selection) {
        flashAlert(escapeHtml($error), 'error');
        redirect($back);
    }

    if ($method === 'new') {
        $_SESSION['portal_checkout'] = ['ids' => array_keys($selection['cents']), 'pi' => $_SESSION['portal_checkout']['pi'] ?? ''];
        redirect('pay.php');
    }

    // A saved card, charged now - the form's token keeps a double click to one charge
    $token = (string) ($_POST['pay_token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        flashAlert('That page was out of date - check the invoices and try again', 'error');
        redirect($back);
    }
    $contact_name = (string) ($contact['contact_name'] ?? '');
    $charge = billingStripeChargeSaved($selection, $session_client_id, intval($method), $token, ['name' => $contact_name]);
    if (!$charge['ok']) {
        flashAlert(escapeHtml($charge['error']), 'error');
        redirect($back);
    }

    $booked = billingStripeBook($charge['pi'], $session_client_id, ['name' => $contact_name, 'how' => 'Saved card', 'via' => 'in the client portal']);
    if (!$booked['ok']) {
        $pi_id = escapeSql((string) $charge['pi']->id);
        logApp("Stripe", "error", "Saved card payment $pi_id for client $session_client_id went through but could not be booked: " . $booked['error']);
        appNotify("Invoice Payment", "A card payment of " . escapeSql(billingMoney($selection['total'], $selection['currency'])) . " from $session_client_name went through in the client portal but could not be recorded - $pi_id", "/agent/invoices.php?client_id=$session_client_id", $session_client_id);
        flashAlert('Your card was charged, but we could not record the payment here - we have been told and will put it right', 'error');
        redirect('invoices.php');
    }

    flashAlert('Thank you - your payment of <strong>' . escapeHtml(billingMoney($selection['total'], $selection['currency'])) . '</strong> went through');
    redirect('receipt.php?id=' . $booked['payment_id']);

}

if (isset($_GET['portal_pay_return'])) {

    portalBillingGate();

    /*
     * Back from Stripe after paying on the checkout page (pay.php) - with the
     * payment's id and client secret. The secret must match the payment, and
     * the payment must be this client's: then it is booked, once
     * (billingStripeBook() in functions/billing_pay.php).
     */
    $pi_id = (string) ($_GET['payment_intent'] ?? '');
    $secret = (string) ($_GET['payment_intent_client_secret'] ?? '');
    $stripe = billingStripeClient();
    if (!$stripe || !preg_match('/^pi_[A-Za-z0-9_]+$/', $pi_id)) {
        flashAlert('That payment could not be found', 'error');
        redirect('invoices.php');
    }

    try {
        $pi = $stripe->paymentIntents->retrieve($pi_id);
    } catch (Throwable $e) {
        logApp("Stripe", "error", "Could not retrieve $pi_id on return to the client portal: " . $e->getMessage());
        flashAlert('We could not check that payment just now - if your card was charged, it will show here shortly', 'error');
        redirect('invoices.php');
    }

    if (!hash_equals((string) $pi->client_secret, $secret) || intval($pi->metadata['itflow_client_id'] ?? 0) !== $session_client_id) {
        flashAlert('That payment could not be found', 'error');
        redirect('invoices.php');
    }

    if ($pi->status === 'processing') {
        flashAlert('Your payment is processing - the invoices show as paid once it clears');
        redirect('invoices.php');
    }
    if ($pi->status !== 'succeeded') {
        flashAlert('That payment did not go through - nothing was charged. Try again, or use another card', 'error');
        redirect('pay.php');
    }

    $booked = billingStripeBook($pi, $session_client_id, ['name' => (string) ($contact['contact_name'] ?? ''), 'how' => 'Online card', 'via' => 'in the client portal']);
    if (!$booked['ok']) {
        $pi_id_sql = escapeSql($pi_id);
        logApp("Stripe", "error", "Card payment $pi_id for client $session_client_id went through but could not be booked: " . $booked['error']);
        appNotify("Invoice Payment", "A card payment from $session_client_name went through in the client portal but could not be recorded - $pi_id_sql", "/agent/invoices.php?client_id=$session_client_id", $session_client_id);
        flashAlert('Your card was charged, but we could not record the payment here - we have been told and will put it right', 'error');
        redirect('invoices.php');
    }

    unset($_SESSION['portal_checkout']);
    if (!$booked['already']) {
        flashAlert('Thank you - your payment of <strong>' . escapeHtml(billingMoney(intval($pi->amount_received) / 100, strtoupper((string) $pi->currency))) . '</strong> went through');
    }
    redirect('receipt.php?id=' . $booked['payment_id']);

}

if (isset($_POST['create_stripe_customer'])) {

    validateCSRFToken();

    enforceContactCan('accounting');

    // Get Stripe provider
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT payment_provider_id, payment_provider_private_key FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        AND payment_provider_active = 1
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_assoc($stripe_provider_result);
    if (!$stripe_provider) {
        flashAlert("Stripe provider is not configured in the system.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = escapeHtml($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flashAlert("Stripe credentials missing. Please contact support.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Check if client already has a Stripe customer
    $existing_customer = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT payment_provider_client
        FROM client_payment_provider
        WHERE client_id = $session_client_id
        AND payment_provider_id = $stripe_provider_id
        LIMIT 1
    "));

    if (!$existing_customer) {
        try {
            // Initialize Stripe
            require_once '../includes/stripe_init.php';
            $stripe = new \Stripe\StripeClient($stripe_secret_key);

            // Create new customer in Stripe
            $customer = $stripe->customers->create([
                'name' => $session_client_name,
                'email' => $session_contact_email,
                'metadata' => [
                    'itflow_client_id' => $session_client_id,
                    'consent_by' => $session_contact_name
                ]
            ]);

            $stripe_customer_id = escapeSql($customer->id);

            // Insert customer into client_payment_provider
            mysqli_query($mysqli, "
                INSERT INTO client_payment_provider
                SET client_id = $session_client_id,
                    payment_provider_id = $stripe_provider_id,
                    payment_provider_client = '$stripe_customer_id',
                    client_payment_provider_created_at = NOW()
            ");

            logAudit("Stripe", "Create", "$session_contact_name created Stripe customer for $session_client_name as $stripe_customer_id and authorized future automatic payments", $session_client_id, $session_client_id);

            flashAlert("Thank you - now add your card.");

            redirect('saved_payment_methods.php?add=1');

        } catch (Exception $e) {
            $error = $e->getMessage();

            error_log("Stripe error while creating customer for $session_client_name: $error");

            logApp("Stripe", "error", "Failed to create Stripe customer for $session_client_name: $error");

            flashAlert("An error occurred while creating your Stripe customer. Please try again.", 'danger');

        }

    } else {
        // Already set up - on to adding the card
        redirect('saved_payment_methods.php?add=1');
    }

    redirect('saved_payment_methods.php');
}

if (isset($_GET['create_stripe_checkout'])) {

    //validateCSRFToken();

    // This page is called by autopay_setup_stripe.js, returns a Checkout Session client_secret

    enforceContactCan('accounting');

    // Fetch Stripe provider info
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT payment_provider_id, payment_provider_private_key FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        AND payment_provider_active = 1
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_assoc($stripe_provider_result);
    if (!$stripe_provider) {
        http_response_code(400);
        echo json_encode(['error' => 'Stripe provider not configured']);
        exit();
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = escapeHtml($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        http_response_code(400);
        echo json_encode(['error' => 'Stripe secret key missing']);
        exit();
    }

    // Get client currency
    $client_currency_result = mysqli_query($mysqli, "
        SELECT client_currency_code
        FROM clients
        WHERE client_id = $session_client_id
        LIMIT 1
    ");
    $client_currency_row = mysqli_fetch_assoc($client_currency_result);
    $client_currency = $client_currency_row['client_currency_code'] ?? 'usd';

    // Client's existing Stripe customer (so the setup attaches to it and
    // Checkout uses the customer's email instead of prompting for one)
    $client_provider = mysqli_fetch_assoc(mysqli_query($mysqli, "
        SELECT payment_provider_client
        FROM client_payment_provider
        WHERE client_id = $session_client_id
        AND payment_provider_id = $stripe_provider_id
        LIMIT 1
    "));
    $stripe_customer_id = $client_provider ? escapeSql($client_provider['payment_provider_client']) : null;

    // Return URL when checkout finishes
    $return_url = "https://$config_base_url/client/post.php?stripe_save_card&session_id={CHECKOUT_SESSION_ID}";

    try {
        require_once '../includes/stripe_init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Create checkout session
        $session_params = [
            'currency'   => $client_currency,
            'mode'       => 'setup',
            'ui_mode'    => 'embedded_page',
            'return_url' => $return_url,
        ];
        if ($stripe_customer_id) {
            $session_params['customer'] = $stripe_customer_id;
        }
        $checkout_session = $stripe->checkout->sessions->create($session_params);

        echo json_encode(['clientSecret' => $checkout_session->client_secret]);

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe error creating checkout session: $error");
        logApp("Stripe", "error", "Exception creating checkout session: $error");
        http_response_code(500);
        echo json_encode(['error' => 'Stripe Checkout session failed']);
    }

    exit;
}

if (isset($_GET['stripe_save_card'])) {

    // validateCSRFToken(); Broken with Stripe Save Card JQ 2026-5-4

    enforceContactCan('accounting');

    // Get Stripe provider
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT payment_provider_id, payment_provider_private_key FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        AND payment_provider_active = 1
        LIMIT 1
    ");

    $stripe_provider = mysqli_fetch_assoc($stripe_provider_result);
    if (!$stripe_provider) {
        flashAlert("Stripe provider not configured.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = escapeHtml($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flashAlert("Stripe credentials missing.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Get client's Stripe customer ID
    $client_provider_query = mysqli_query($mysqli, "
        SELECT payment_provider_client
        FROM client_payment_provider
        WHERE client_id = $session_client_id
        AND payment_provider_id = $stripe_provider_id
        LIMIT 1
    ");
    $client_provider = mysqli_fetch_assoc($client_provider_query);
    $stripe_customer_id = escapeSql($client_provider['payment_provider_client'] ?? '');

    if (empty($stripe_customer_id)) {
        flashAlert("Stripe customer ID not found for client.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Get session ID from URL
    $checkout_session_id = escapeSql($_GET['session_id']);

    try {
        require_once '../includes/stripe_init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Retrieve checkout session & setup intent
        $checkout_session = $stripe->checkout->sessions->retrieve($checkout_session_id, []);
        $setup_intent_id = $checkout_session->setup_intent;
        $setup_intent = $stripe->setupIntents->retrieve($setup_intent_id, []);
        $payment_method_id = escapeSql($setup_intent->payment_method);

        // Attach the payment method to the Stripe customer
        $stripe->paymentMethods->attach($payment_method_id, ['customer' => $stripe_customer_id]);

        // Retrieve PM details for logging and UI
        $payment_method_details = $stripe->paymentMethods->retrieve($payment_method_id, []);
        $card_brand = escapeSql($payment_method_details->card->brand);
        $last4 = escapeSql($payment_method_details->card->last4);
        $exp_month = escapeSql($payment_method_details->card->exp_month);
        $exp_year = escapeSql($payment_method_details->card->exp_year);

        $saved_payment_description = "$card_brand - $last4 | Exp $exp_month/$exp_year";

        // Insert into client_saved_payment_methods
        mysqli_query($mysqli, "
            INSERT INTO client_saved_payment_methods
            SET
                saved_payment_provider_method = '$payment_method_id',
                saved_payment_description = '$saved_payment_description',
                saved_payment_client_id = $session_client_id,
                saved_payment_provider_id = $stripe_provider_id,
                saved_payment_created_at = NOW()
        ");

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Stripe error while saving payment method: $error");
        logApp("Stripe", "error", "Exception saving payment method: $error");

        flashAlert("An error occurred while saving your payment method.", 'danger');
        redirect("saved_payment_methods.php");
    }

    // Email Confirmation
    $sql_settings = mysqli_query($mysqli, "
        SELECT company_name, company_phone, company_phone_country_code, config_invoice_from_email,
            config_invoice_from_name, config_smtp_host FROM companies, settings
        WHERE companies.company_id = settings.company_id
        AND companies.company_id = 1
    ");
    $row = mysqli_fetch_assoc($sql_settings);

    $company_name = escapeSql($row['company_name']);
    $company_phone = escapeSql(formatPhoneNumber($row['company_phone'], $row['company_phone_country_code']));
    $config_invoice_from_email = escapeSql($row['config_invoice_from_email']);
    $config_invoice_from_name = escapeSql($row['config_invoice_from_name']);

    if (!empty($row['config_smtp_host'])) {
        $subject = "Payment method saved";
        $body = "Hello $session_contact_name<br><br>
        Were writing to confirm that your payment details have been securely stored with Stripe our trusted payment processor.<br><br>
        You authorized us to automatically bill your card ($saved_payment_description) for future invoices.<br><br>
        You may update or remove your payment method at any time via the client portal.<br><br>
        Thank you for your business!<br><br>
        --<br>$company_name - Billing Department<br>$config_invoice_from_email<br>$company_phone";

        $data = [[
            'from' => $config_invoice_from_email,
            'from_name' => $config_invoice_from_name,
            'recipient' => $session_contact_email,
            'recipient_name' => $session_contact_name,
            'subject' => $subject,
            'body' => $body
        ]];

        $mail = addToMailQueue($data);
    }

    logAudit("Stripe", "Update", "$session_contact_name saved payment method ($saved_payment_description) (PM: $payment_method_id)", $session_client_id);

    flashAlert("Payment method saved – thank you.");
    redirect("saved_payment_methods.php");
}

if (isset($_GET['delete_saved_payment'])) {

    validateCSRFToken();

    enforceContactCan('accounting');

    $saved_payment_id = intval($_GET['delete_saved_payment']);

    // Get Stripe provider info
    $stripe_provider_result = mysqli_query($mysqli, "
        SELECT payment_provider_id, payment_provider_private_key FROM payment_providers
        WHERE payment_provider_name = 'Stripe'
        AND payment_provider_active = 1
        LIMIT 1
    ");
    $stripe_provider = mysqli_fetch_assoc($stripe_provider_result);

    if (!$stripe_provider) {
        flashAlert("Stripe provider is not configured.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $stripe_provider_id = intval($stripe_provider['payment_provider_id']);
    $stripe_secret_key = escapeHtml($stripe_provider['payment_provider_private_key']);

    if (empty($stripe_secret_key)) {
        flashAlert("Stripe credentials are missing.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $saved_payment_result = mysqli_query($mysqli, "
        SELECT saved_payment_id, saved_payment_description, saved_payment_provider_method
        FROM client_saved_payment_methods
        WHERE saved_payment_id = $saved_payment_id
        AND saved_payment_client_id = $session_client_id
        AND saved_payment_provider_id = $stripe_provider_id
        LIMIT 1
    ");

    $saved_payment = mysqli_fetch_assoc($saved_payment_result);

    if (!$saved_payment) {
        flashAlert("Payment method not found or does not belong to you.", 'danger');
        redirect("saved_payment_methods.php");
    }

    $payment_method_id = escapeSql($saved_payment['saved_payment_provider_method']);

    $saved_payment_id = intval($saved_payment['saved_payment_id']);
    $saved_payment_description = escapeHtml($saved_payment['saved_payment_description']);

    try {
        // Initialize Stripe
        require_once '../includes/stripe_init.php';
        $stripe = new \Stripe\StripeClient($stripe_secret_key);

        // Detach the payment method from Stripe
        $stripe->paymentMethods->detach($payment_method_id, []);

    } catch (Exception $e) {
        $error = $e->getMessage();

        error_log("Stripe error while removing payment method $payment_method_id: $error");

        logApp("Stripe", "error", "Exception removing payment method $payment_method_id: $error");

        flashAlert("An error occurred while removing your payment method.", 'danger');

        redirect("saved_payment_methods.php");

    }

    // Remove saved payment method from local DB
    mysqli_query($mysqli, "
        DELETE FROM client_saved_payment_methods
        WHERE saved_payment_id = $saved_payment_id
    ");

    // Remove any auto-pay records using this payment method
    $recurring_invoices = mysqli_query($mysqli, "
        SELECT recurring_invoice_id
        FROM recurring_invoices
        WHERE recurring_invoice_client_id = $session_client_id
    ");

    while ($row = mysqli_fetch_assoc($recurring_invoices)) {
        $recurring_invoice_id = intval($row['recurring_invoice_id']);

        mysqli_query($mysqli, "
            DELETE FROM recurring_payments
            WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id
            AND recurring_payment_saved_payment_id = $saved_payment_id
        ");
    }

    logAudit("Stripe", "Update", "$session_contact_name deleted Stripe payment method $saved_payment_description (PM: $payment_method_id)", $session_client_id);

    flashAlert("Payment method $saved_payment_description removed.");

    redirect("saved_payment_methods.php");
}

if (isset($_POST['set_recurring_payment'])) {

    validateCSRFToken();

    enforceContactCan('accounting');

    $recurring_invoice_id = intval($_POST['recurring_invoice_id']);
    $saved_payment_id = intval($_POST['saved_payment_id']);

    // Get Recurring Invoice Info - scoped to the logged-in client, so this is also the ownership check
    $sql = mysqli_query($mysqli, "SELECT recurring_invoice_amount, recurring_invoice_currency_code, recurring_invoice_number,
        recurring_invoice_prefix FROM recurring_invoices WHERE recurring_invoice_id = $recurring_invoice_id AND recurring_invoice_client_id = $session_client_id");
    $row = mysqli_fetch_assoc($sql);

    if (!$row) {
        flashAlert("Recurring invoice not found.", 'danger');
        redirect("recurring_invoices.php");
    }

    $recurring_invoice_prefix = escapeSql($row['recurring_invoice_prefix']);
    $recurring_invoice_number = intval($row['recurring_invoice_number']);
    $recurring_invoice_currency_code = escapeSql($row['recurring_invoice_currency_code']);
    $recurring_invoice_amount = floatval($row['recurring_invoice_amount']);

    if ($saved_payment_id) {

        // Get Payment provider and method
        $sql = mysqli_query($mysqli, "
            SELECT payment_provider_account, payment_provider_id, payment_provider_name,
                saved_payment_description FROM payment_providers
            LEFT JOIN client_saved_payment_methods ON saved_payment_provider_id = payment_provider_id
            WHERE saved_payment_id = $saved_payment_id
            AND saved_payment_client_id = $session_client_id
            AND payment_provider_active = 1
        ");

        $row = mysqli_fetch_assoc($sql);

        if (!$row) {
            flashAlert("Payment method not found or does not belong to you.", 'danger');
            redirect("recurring_invoices.php");
        }

        $provider_id = intval($row['payment_provider_id']);
        $provider_name = escapeSql($row['payment_provider_name']);
        $account_id = intval($row['payment_provider_account']);
        $saved_payment_description = escapeSql($row['saved_payment_description']);

        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");
        mysqli_query($mysqli,"INSERT INTO recurring_payments SET recurring_payment_currency_code = '$recurring_invoice_currency_code', recurring_payment_account_id = $account_id, recurring_payment_method = 'Credit Card', recurring_payment_recurring_invoice_id = $recurring_invoice_id, recurring_payment_saved_payment_id = $saved_payment_id");
        // Get Payment ID for reference
        $recurring_payment_id = mysqli_insert_id($mysqli);

        logAudit("Recurring Invoice", "Auto Payment", "$session_contact_name created Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $session_client_id, $recurring_invoice_id);

        flashAlert("Automatic Payment $saved_payment_description enabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    } else {
        // Delete
        mysqli_query($mysqli, "DELETE FROM recurring_payments WHERE recurring_payment_recurring_invoice_id = $recurring_invoice_id");

        logAudit("Recurring Invoice", "Auto Payment", "$session_contact_name removed Auto Pay for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number in the amount of " . numfmt_format_currency($currency_format, $recurring_invoice_amount, $recurring_invoice_currency_code), $session_client_id, $recurring_invoice_id);

        flashAlert("Automatic Payment Disabled for Recurring Invoice $recurring_invoice_prefix$recurring_invoice_number");
    }

    redirect();

}

if (isset($_POST['client_add_document'])) {

    validateCSRFToken();

    // Primary and technical contacts, with IT documentation on
    portalItGate();

    $raw_content = (string) ($_POST['document_content'] ?? '');
    $name = trim(mb_substr((string) ($_POST['document_name'] ?? ''), 0, 200));
    $description = trim(mb_substr((string) ($_POST['document_description'] ?? ''), 0, 255));
    if ($name === '' || portalHtmlIsEmpty($raw_content)) {
        flashAlert('Give the document a name and write something in it', 'error');
        redirect('document_add.php');
    }

    $document_name = escapeSql($name);
    $document_description = escapeSql($description);
    $document_content_raw = escapeSql(documentContentRaw($name, $raw_content));

    // Shared with the client, and the portal user as its author - the agent pages show the author from users
    mysqli_query($mysqli, "INSERT INTO documents SET
        document_name = '$document_name',
        document_description = '$document_description',
        document_content = '',
        document_content_raw = '$document_content_raw',
        document_client_visible = 1,
        document_client_id = $session_client_id,
        document_created_by = $session_user_id");

    $document_id = mysqli_insert_id($mysqli);

    // Pasted pictures are saved as files rather than kept in the HTML
    $processed_content = mysqli_escape_string($mysqli, saveBase64Images($raw_content, $_SERVER['DOCUMENT_ROOT'] . "/uploads/documents/", "uploads/documents/", $document_id));
    mysqli_query($mysqli, "UPDATE documents SET document_content = '$processed_content' WHERE document_id = $document_id");

    logAudit("Document", "Create", "Client contact $session_contact_name created document $document_name in the client portal", $session_client_id, $document_id);
    appNotify("Document", "$session_contact_name added the document $document_name in the client portal", "/agent/document.php?document_id=$document_id", $session_client_id, $document_id);

    flashAlert("Document <strong>" . escapeHtml($name) . "</strong> saved");

    redirect("document.php?id=$document_id");

}

if (isset($_POST['client_upload_document'])) {

    validateCSRFToken();

    // Primary and technical contacts, with IT documentation on
    portalItGate();

    $name = trim(mb_substr((string) ($_POST['document_name'] ?? ''), 0, 200));
    $description = trim(mb_substr((string) ($_POST['document_description'] ?? ''), 0, 255));
    if ($name === '' || empty($_FILES['document_file']) || intval($_FILES['document_file']['error']) !== 0) {
        flashAlert('Give the document a name and choose a file', 'error');
        redirect('document_add.php?upload=1');
    }

    $file_reference_name = checkFileUpload($_FILES['document_file'], ['pdf', 'doc', 'docx', 'txt', 'md', 'odt', 'rtf']);
    if (!$file_reference_name) {
        flashAlert('That file could not be taken - upload a PDF, Word, OpenDocument, RTF or text file', 'error');
        redirect('document_add.php?upload=1');
    }

    $client_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/clients/$session_client_id";
    if (!is_dir($client_dir)) {
        mkdir($client_dir, 0755, true);
    }
    if (!move_uploaded_file($_FILES['document_file']['tmp_name'], "$client_dir/$file_reference_name")) {
        flashAlert('Something went wrong uploading the file - please try again', 'error');
        redirect('document_add.php?upload=1');
    }

    $original = (string) $_FILES['document_file']['name'];
    $ext_parts = explode('.', $original);
    $file_name = escapeSql($original);
    $file_extension = escapeSql(strtolower(end($ext_parts)));
    $file_mime_type = escapeSql($_FILES['document_file']['type']);
    $file_size = intval($_FILES['document_file']['size']);
    $document_name = escapeSql($name);
    $document_description = escapeSql($description);
    // What the document says is the file - its name and description, escaped for the page
    $document_content = mysqli_real_escape_string($mysqli, '<p>Uploaded file: <strong>' . escapeHtml($original) . '</strong></p>' . ($description !== '' ? '<p>' . escapeHtml($description) . '</p>' : ''));
    $document_content_raw = escapeSql("$name $original $description");

    mysqli_query($mysqli, "INSERT INTO documents SET
        document_name = '$document_name',
        document_description = '$document_description',
        document_content = '$document_content',
        document_content_raw = '$document_content_raw',
        document_client_visible = 1,
        document_client_id = $session_client_id,
        document_created_by = $session_user_id");
    $document_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO files SET
        file_reference_name = '" . escapeSql($file_reference_name) . "',
        file_name = '$file_name',
        file_description = 'Attached to document: $document_name',
        file_ext = '$file_extension',
        file_mime_type = '$file_mime_type',
        file_size = $file_size,
        file_created_by = $session_user_id,
        file_client_id = $session_client_id");
    $file_id = mysqli_insert_id($mysqli);

    mysqli_query($mysqli, "INSERT INTO document_files SET document_id = $document_id, file_id = $file_id");

    logAudit("Document", "Upload", "Client contact $session_contact_name uploaded document $document_name with file $file_name in the client portal", $session_client_id, $document_id);
    appNotify("Document", "$session_contact_name uploaded the document $document_name in the client portal", "/agent/document.php?document_id=$document_id", $session_client_id, $document_id);

    flashAlert("Document <strong>" . escapeHtml($name) . "</strong> uploaded");

    redirect("document.php?id=$document_id");
}
