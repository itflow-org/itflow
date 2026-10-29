<?php
/*
 * Client Portal
 * Your account - who the contact is to us, their phone numbers, the emails
 * they get from us, and how they sign in: their password, two-step sign-in
 * with an authenticator app, the browsers it remembers, and their phone PIN.
 * Their recent sign-ins, with the rest on activity.php.
 *
 * ?mfa=setup opens setting up two-step sign-in. When an admin requires it
 * (Admin > Client Portal) a contact signing in with a password is kept here
 * until it is on (includes/check_login.php).
 *
 * SCOPING: everything is the session contact's own - no id is read from the
 * request. The forms post to post.php.
 */

// intl-tel-input for the phone numbers - see includes/header.php
$portal_load_phone_inputs = true;

header("Content-Security-Policy: default-src 'self'");

require_once 'includes/inc_all.php';

$me = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT contact_name, contact_title, contact_department, contact_email, contact_extension, contact_location_id,
        contact_mobile, contact_mobile_country_code, contact_phone, contact_phone_country_code, contact_pin, contact_billing, contact_technical, contact_primary,
        contact_ticket_emails, contact_company_ticket_emails,
        location_name, location_address, location_city, location_state, location_zip, location_country
    FROM contacts
    LEFT JOIN locations ON location_id = contact_location_id AND location_client_id = $session_client_id AND location_archived_at IS NULL
    WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id
    LIMIT 1"));

$password_account = $session_auth_method === 'local';
$microsoft_account = $session_auth_method === 'azure';
$mfa_required = intval($config_client_portal_require_mfa) === 1;
$mfa_setup = $password_account && !$session_mfa_on && (($_GET['mfa'] ?? '') === 'setup' || $portal_mfa_setup_needed) && !portalViewingAs();
$remembered = $session_mfa_on ? portalRememberedBrowsers($session_user_id) : 0;
$sees_company = $session_contact_primary || $session_contact_is_technical_contact; // they see every ticket at their company

// Setting up two-step sign-in: one secret, kept until a code from it is checked
$mfa_secret = '';
$mfa_qr = '';
if ($mfa_setup && !portalViewingAs()) {
    if (empty($_SESSION['portal_mfa_secret'])) {
        $_SESSION['portal_mfa_secret'] = generateTotpSecret();
    }
    $mfa_secret = $_SESSION['portal_mfa_secret'];
    $mfa_qr = portalMfaQrSvg($mfa_secret, $me['contact_email'] ?? '');
}

$roles = portalContactRoles($me ?: []);
$location = '';
if (!empty($me['location_name'])) {
    $location = escapeHtml($me['location_name']);
    $address = formatAddress($me['location_address'], $me['location_city'], $me['location_state'], $me['location_zip'], $me['location_country']);
    if (trim(strip_tags((string) $address)) !== '') {
        $location .= '<div class="small text-body-secondary">' . nl2br(escapeHtml($address)) . '</div>';
    }
}

$facts = [
    'Name' => escapeHtml($me['contact_name'] ?? ''),
    'Email' => escapeHtml($me['contact_email'] ?? '') . ($password_account || $microsoft_account ? '<div class="small text-body-secondary">You sign in with it</div>' : ''),
];
if (trim((string) ($me['contact_title'] ?? '')) !== '') {
    $facts['Job title'] = escapeHtml($me['contact_title']);
}
if (trim((string) ($me['contact_department'] ?? '')) !== '') {
    $facts['Department'] = escapeHtml($me['contact_department']);
}
$facts['Company'] = escapeHtml($session_client_name);
if ($location !== '') {
    $facts['Location'] = $location;
}
$facts['Roles'] = $roles
    ? '<span class="itflow-portal-roles">' . implode('', array_map(function ($role) { return '<span>' . escapeHtml($role) . '</span>'; }, $roles)) . '</span>'
    : '<span class="text-body-secondary">None - you see your own tickets</span>';

$sql_logins = mysqli_query($mysqli, "SELECT log_created_at, log_ip, log_user_agent FROM logs
    WHERE log_type = 'Client Login' AND log_action = 'Success' AND log_user_id = $session_user_id AND log_client_id = $session_client_id AND $session_user_id > 0
    ORDER BY log_id DESC LIMIT 5");

$phone_input = ($me['contact_phone'] ?? '') !== '' ? formatPhoneNumber($me['contact_phone'], $me['contact_phone_country_code'], false) : '';
$mobile_input = ($me['contact_mobile'] ?? '') !== '' ? formatPhoneNumber($me['contact_mobile'], $me['contact_mobile_country_code'], false) : '';
$ticket_pref = isset(PORTAL_TICKET_EMAILS[$me['contact_ticket_emails'] ?? '']) ? $me['contact_ticket_emails'] : 'all';
$company_pref = isset(PORTAL_COMPANY_TICKET_EMAILS[$me['contact_company_ticket_emails'] ?? '']) ? $me['contact_company_ticket_emails'] : 'none';
$csrf = escapeHtml($_SESSION['csrf_token']);
?>

<div class="itflow-portal-page-head">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <?= portalAvatarHtml((string) ($me['contact_name'] ?? ''), $session_contact_photo ? "/uploads/clients/$session_client_id/$session_contact_photo" : '', 'lg') ?>
        <div class="min-w-0">
            <h1>Your account</h1>
            <p>Your details, the emails you get from us and how you sign in</p>
        </div>
    </div>
</div>

<?php if ($portal_mfa_setup_needed) { ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="fas fa-shield-alt mt-1"></i>
        <div><strong><?= escapeHtml($session_company_name) ?> asks everyone to use two-step sign-in.</strong> Set it up below to carry on - it takes a minute with an authenticator app on your phone.</div>
    </div>
<?php } ?>

<div class="row g-4">
    <div class="col-lg-7">

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-id-card"></i>Your details</h2>
                <a href="<?= escapeHtml(portalAskUrl('Please update my details')) ?>">Ask us to change them</a>
            </div>
            <div class="itflow-portal-panel-body">
                <dl class="itflow-portal-facts mb-0">
                    <?php foreach ($facts as $label => $value) { ?>
                        <div><dt><?= $label ?></dt><dd><?= $value ?></dd></div>
                    <?php } ?>
                </dl>
            </div>
        </section>

        <form class="itflow-portal-panel mb-4" action="post.php" method="post" autocomplete="off" data-portal-once>
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-phone"></i>Your phone numbers</h2>
            </div>
            <div class="itflow-portal-panel-body">
                <p class="small text-body-secondary">So we can reach you about a ticket without hunting for a number. Clear one to remove it.</p>
                <div class="row">
                    <div class="col-sm-8 mb-3">
                        <label class="form-label" for="contactPhone">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                            <input type="hidden" name="phone_country_code" value="<?= escapeHtml($me['contact_phone_country_code'] ?? '') ?>">
                            <input type="tel" class="form-control" id="contactPhone" name="phone" maxlength="200" value="<?= escapeHtml($phone_input) ?>" data-itflow-phone="phone_country_code">
                        </div>
                    </div>
                    <div class="col-sm-4 mb-3">
                        <label class="form-label" for="contactExtension">Extension</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                            <input type="text" class="form-control" id="contactExtension" name="extension" inputmode="numeric" maxlength="20" value="<?= escapeHtml($me['contact_extension'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="contactMobile">Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                        <input type="hidden" name="mobile_country_code" value="<?= escapeHtml($me['contact_mobile_country_code'] ?? '') ?>">
                        <input type="tel" class="form-control" id="contactMobile" name="mobile" maxlength="200" value="<?= escapeHtml($mobile_input) ?>" data-itflow-phone="mobile_country_code">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" name="set_contact_phone"><i class="fas fa-check me-2"></i>Save numbers</button>
            </div>
        </form>

        <form class="itflow-portal-panel mb-4" action="post.php" method="post" autocomplete="off" data-portal-once id="emails">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-envelope"></i>Emails from us</h2>
            </div>
            <div class="itflow-portal-panel-body">
                <fieldset class="mb-3">
                    <legend class="form-label fs-6 fw-semibold">About your tickets</legend>
                    <?php foreach (PORTAL_TICKET_EMAILS as $key => $option) { ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="ticket_emails" id="ticketEmails-<?= $key ?>" value="<?= $key ?>"<?= $ticket_pref === $key ? ' checked' : '' ?>>
                            <label class="form-check-label" for="ticketEmails-<?= $key ?>"><?= escapeHtml($option['label']) ?> <span class="d-block small text-body-secondary"><?= escapeHtml($option['text']) ?></span></label>
                        </div>
                    <?php } ?>
                </fieldset>
                <?php if ($sees_company) { ?>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6 fw-semibold">About other tickets at <?= escapeHtml($session_client_name) ?></legend>
                        <?php foreach (PORTAL_COMPANY_TICKET_EMAILS as $key => $option) { ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="company_ticket_emails" id="companyEmails-<?= $key ?>" value="<?= $key ?>"<?= $company_pref === $key ? ' checked' : '' ?>>
                                <label class="form-check-label" for="companyEmails-<?= $key ?>"><?= escapeHtml($option['label']) ?> <span class="d-block small text-body-secondary"><?= escapeHtml($option['text']) ?></span></label>
                            </div>
                        <?php } ?>
                    </fieldset>
                <?php } ?>
                <?php if ($session_contact_is_billing_contact || $session_contact_primary) { ?>
                    <p class="small text-body-secondary">Invoices, quotes, receipts and statements go to <?= escapeHtml($session_client_name) ?>'s billing contacts - ask us if they should go somewhere else.</p>
                <?php } ?>
                <button type="submit" class="btn btn-primary" name="portal_email_prefs"><i class="fas fa-check me-2"></i>Save</button>
            </div>
        </form>

    </div>
    <div class="col-lg-5">

        <section class="itflow-portal-panel mb-4" id="security">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-shield-alt"></i>Signing in</h2>
            </div>

            <?php if ($microsoft_account) { ?>
                <div class="itflow-portal-panel-body">
                    <p class="mb-0">You sign in with your Microsoft 365 account - Microsoft looks after your password and its own sign-in checks.</p>
                </div>
            <?php } elseif (!$password_account) { ?>
                <div class="itflow-portal-panel-body">
                    <p class="mb-0 text-body-secondary">There is no client portal sign-in for this contact.</p>
                </div>
            <?php } else { ?>

                <ul class="itflow-portal-list">
                    <li class="itflow-portal-ticket">
                        <?= portalTile('fa-key') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block">Password</span>
                            <span class="itflow-portal-ticket-meta"><span>At least <?= USER_PASSWORD_MIN ?> characters</span></span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#passwordForm" aria-expanded="false" aria-controls="passwordForm">Change</button>
                    </li>
                </ul>
                <form class="collapse itflow-portal-panel-body border-top" id="passwordForm" action="post.php" method="post" autocomplete="off" data-portal-once>
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="text" class="d-none" name="username" value="<?= escapeHtml($me['contact_email'] ?? '') ?>" autocomplete="username" aria-hidden="true" tabindex="-1">
                    <div class="mb-3">
                        <label class="form-label" for="currentPassword">Your current password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" autocomplete="current-password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newPassword">New password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" id="newPassword" name="new_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmPassword">The same again</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-check-double"></i></span>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" minlength="<?= USER_PASSWORD_MIN ?>" autocomplete="new-password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" name="portal_password_change"><i class="fas fa-check me-2"></i>Change password</button>
                </form>

                <ul class="itflow-portal-list border-top">
                    <li class="itflow-portal-ticket">
                        <?= portalTile('fa-mobile-alt') ?>
                        <span class="itflow-portal-ticket-main">
                            <span class="itflow-portal-ticket-subject d-block">Two-step sign-in
                                <span class="itflow-portal-state <?= $session_mfa_on ? 'itflow-portal-state-resolved' : 'itflow-portal-state-hold' ?> ms-1"><i class="fas fa-fw fa-<?= $session_mfa_on ? 'check' : 'minus' ?>"></i><?= $session_mfa_on ? 'On' : 'Off' ?></span>
                            </span>
                            <span class="itflow-portal-ticket-meta"><span><?= $session_mfa_on ? 'Signing in asks for a code from your authenticator app' : 'A code from an app on your phone, as well as your password' ?></span></span>
                        </span>
                        <?php if (!$session_mfa_on && !$mfa_setup) { ?>
                            <a class="btn btn-sm btn-primary text-nowrap" href="profile.php?mfa=setup#security">Turn on</a>
                        <?php } elseif ($session_mfa_on && !$mfa_required) { ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-bs-toggle="collapse" data-bs-target="#mfaOffForm" aria-expanded="false" aria-controls="mfaOffForm">Turn off</button>
                        <?php } ?>
                    </li>
                </ul>

                <?php if ($mfa_setup) { ?>
                    <form class="itflow-portal-panel-body border-top" action="post.php" method="post" autocomplete="off" data-portal-once>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <ol class="small ps-3 mb-3">
                            <li>Open an authenticator app on your phone - Microsoft or Google Authenticator, 1Password, Authy, Bitwarden - and add an account.</li>
                            <li>Scan this code<?= $mfa_qr ? '' : ' - or, as it can\'t be shown here, type the key in' ?>:</li>
                        </ol>
                        <div class="text-center mb-3">
                            <?php if ($mfa_qr) { ?>
                                <div class="itflow-mfa-qr-box"><?= $mfa_qr ?></div>
                            <?php } ?>
                            <div class="small text-body-secondary mt-2">Can't scan it? Type this key in:</div>
                            <div class="font-monospace fw-semibold user-select-all"><?= escapeHtml(trim(chunk_split($mfa_secret, 4, ' '))) ?></div>
                        </div>
                        <ol class="small ps-3 mb-2" start="3">
                            <li>Enter the 6-digit code it shows:</li>
                        </ol>
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="fas fa-fw fa-mobile-alt"></i></span>
                            <input type="text" class="form-control form-control-lg font-monospace text-center itflow-mfa-code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" name="verify_code" placeholder="000000" autocomplete="one-time-code" aria-label="6-digit code" required autofocus>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary" name="portal_mfa_enable"><i class="fas fa-check me-2"></i>Turn on</button>
                            <?php if (!$portal_mfa_setup_needed) { ?>
                                <a class="btn btn-light" href="profile.php">Not now</a>
                            <?php } ?>
                        </div>
                    </form>
                <?php } ?>

                <?php if ($session_mfa_on && !$mfa_required) { ?>
                    <form class="collapse itflow-portal-panel-body border-top" id="mfaOffForm" action="post.php" method="post" autocomplete="off" data-portal-once>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <p class="small text-body-secondary">Signing in will ask for your password alone.</p>
                        <div class="mb-3">
                            <label class="form-label" for="mfaOffPassword">Your current password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                <input type="password" class="form-control" id="mfaOffPassword" name="current_password" autocomplete="current-password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-danger" name="portal_mfa_disable"><i class="fas fa-times me-2"></i>Turn off two-step sign-in</button>
                    </form>
                <?php } elseif ($session_mfa_on) { ?>
                    <div class="itflow-portal-panel-body border-top small text-body-secondary py-2"><?= escapeHtml($session_company_name) ?> asks everyone to use it, so it stays on. Lost your phone? Ask us to reset it.</div>
                <?php } ?>

                <?php if ($session_mfa_on && $remembered) { ?>
                    <form class="itflow-portal-panel-body border-top d-flex align-items-center justify-content-between gap-2" action="post.php" method="post" data-portal-once>
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <span class="small text-body-secondary"><?= $remembered ?> browser<?= $remembered === 1 ? ' skips' : 's skip' ?> the code for <?= intval($config_login_remember_me_expire) ?> day<?= intval($config_login_remember_me_expire) === 1 ? '' : 's' ?> after signing in</span>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap" name="portal_forget_browsers">Forget them</button>
                    </form>
                <?php } ?>

            <?php } ?>
        </section>

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-hashtag"></i>Phone PIN</h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#pinForm" aria-expanded="false" aria-controls="pinForm"><?= empty($me['contact_pin']) ? 'Set' : 'Change' ?></button>
            </div>
            <div class="itflow-portal-panel-body">
                <p class="small text-body-secondary mb-0">We ask for it to make sure it is really you when you call.
                    <?= empty($me['contact_pin']) ? 'You have not set one.' : 'Yours is <span class="font-monospace fw-semibold">' . escapeHtml($me['contact_pin']) . '</span>.' ?></p>
            </div>
            <form class="collapse itflow-portal-panel-body border-top" id="pinForm" action="post.php" method="post" autocomplete="off" data-portal-once>
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?php if ($password_account) { ?>
                    <div class="mb-3">
                        <label class="form-label" for="pinCurrentPassword">Your current password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" id="pinCurrentPassword" name="current_password" autocomplete="current-password" required>
                        </div>
                    </div>
                <?php } ?>
                <div class="mb-3">
                    <label class="form-label" for="contactPin"><?= empty($me['contact_pin']) ? 'New PIN' : 'Replace it with' ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hashtag"></i></span>
                        <input type="text" class="form-control" id="contactPin" name="pin" minlength="4" maxlength="255" placeholder="At least 4 characters" autocomplete="off" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" name="set_contact_pin"><i class="fas fa-check me-2"></i>Save PIN</button>
            </form>
        </section>

        <section class="itflow-portal-panel mb-4">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-history"></i>Recent sign-ins</h2>
                <a href="activity.php">All your activity</a>
            </div>
            <?php if (!mysqli_num_rows($sql_logins)) { ?>
                <div class="itflow-portal-panel-body small text-body-secondary">No sign-ins yet.</div>
            <?php } else { ?>
                <ul class="itflow-portal-list">
                    <?php while ($row = mysqli_fetch_assoc($sql_logins)) { ?>
                        <li class="itflow-portal-ticket">
                            <span class="itflow-portal-ticket-main">
                                <span class="d-block"><?= portalDateTime($row['log_created_at']) ?></span>
                                <span class="itflow-portal-ticket-meta"><span><?= userDeviceHtml($row['log_user_agent']) ?></span><span class="font-monospace"><?= escapeHtml($row['log_ip']) ?></span></span>
                            </span>
                        </li>
                    <?php } ?>
                </ul>
                <div class="itflow-portal-panel-body border-top small text-body-secondary py-2">Somewhere you don't recognise? Change your password and let us know.</div>
            <?php } ?>
        </section>

    </div>
</div>

<?php
require_once 'includes/footer.php';
