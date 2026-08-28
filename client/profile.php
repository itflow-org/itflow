<?php
/*
 * Client Portal
 * User profile
 */

// Read by client/includes/header.php and footer.php - see the note there.
// Must be set before inc_all.php, which pulls the header in.
$portal_load_phone_inputs = true;

header("Content-Security-Policy: default-src 'self'");

require_once 'includes/inc_all.php';

/*
 * check_login.php runs these through escapeSql(), which is for writing to the
 * database rather than printing. It leaves a backslash in front of any quote in
 * the value - O'Brien renders as O\'Brien - which is why the name was already
 * wrapped in stripslashes() here. Email, PIN and company were printed raw, and
 * the company name had no escaping of any kind. Normalise all four in one place
 * and escape at the point of output.
 */
$contact_name = escapeHtml(stripslashes($session_contact_name));
$contact_email = escapeHtml(stripslashes($session_contact_email));
$contact_pin = escapeHtml(stripslashes($session_contact_pin));
$client_name = escapeHtml(stripslashes($session_client_name));

/*
 * check_login.php loads the handful of contact columns every portal page needs.
 * The rest are only wanted here, so they are fetched on this page rather than
 * added to a query that runs on every request. Scoped to the session contact
 * and their client - nothing here reads an id from the request.
 */
$row = mysqli_fetch_assoc(mysqli_query(
    $mysqli,
    "SELECT contact_department, contact_extension, contact_location_id, contact_mobile,
        contact_mobile_country_code, contact_phone, contact_phone_country_code
    FROM contacts
    WHERE contact_id = $session_contact_id AND contact_client_id = $session_client_id
    LIMIT 1"
));

$contact_title = escapeHtml(stripslashes($session_contact_title));
$contact_department = escapeHtml($row['contact_department']);
$contact_phone_country_code = escapeHtml($row['contact_phone_country_code']);
$contact_extension = escapeHtml($row['contact_extension']);
$contact_mobile_country_code = escapeHtml($row['contact_mobile_country_code']);
$contact_location_id = intval($row['contact_location_id']);

/*
 * Two renderings of the same digits. The tables show them formatted for
 * reading; the modal inputs get formatPhoneNumber(..., false), which is the
 * form-input variant - intl-tel-input runs in separateDialCode mode, so the
 * visible input holds the national number only and the dial code lives in the
 * hidden field beside it.
 */
$contact_phone = escapeHtml(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code']));
$contact_mobile = escapeHtml(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code']));
$contact_phone_input = escapeHtml(formatPhoneNumber($row['contact_phone'], $row['contact_phone_country_code'], false));
$contact_mobile_input = escapeHtml(formatPhoneNumber($row['contact_mobile'], $row['contact_mobile_country_code'], false));

/*
 * The location is joined on client too, so a contact whose location_id points
 * at another client's row - stale data, a moved contact - gets nothing rather
 * than another company's address.
 */
$contact_location = '';
$contact_location_address = '';
if (!empty($contact_location_id)) {
    $location = mysqli_fetch_assoc(mysqli_query(
        $mysqli,
        "SELECT location_address, location_city, location_country, location_name, location_state, location_zip
        FROM locations
        WHERE location_id = $contact_location_id
        AND location_client_id = $session_client_id
        AND location_archived_at IS NULL
        LIMIT 1"
    ));
    if ($location) {
        $contact_location = escapeHtml($location['location_name']);
        $contact_location_address = nl2br(escapeHtml(formatAddress(
            $location['location_address'],
            $location['location_city'],
            $location['location_state'],
            $location['location_zip'],
            $location['location_country']
        )));
    }
}

$login_method = $_SESSION['login_method'] ?? 'local';

if ($login_method === 'local') {
    $login_method_display = 'Password';
} elseif ($login_method === 'azure') {
    $login_method_display = 'Microsoft account';
} else {
    $login_method_display = escapeHtml(ucfirst($login_method));
}

/*
 * These three decide which sections of the portal a contact can reach - the
 * same flags contactCan() switches on - so the answer to "why can't I see
 * invoices" is on this page rather than only in the agent's copy of the record.
 *
 * All three are booleans. The billing row used to be written as
 * ($session_contact_is_billing_contact == $session_contact_id), comparing a
 * bool to a contact id. PHP casts the id to bool for that comparison, so it
 * gave the right answer for every real contact and would only have misreported
 * at id 0 - working by luck rather than by meaning.
 */
$contact_roles = [
    ['Primary', (bool) $session_contact_primary, 'Everything in the portal'],
    ['Billing', (bool) $session_contact_is_billing_contact, 'Invoices, quotes and payments'],
    ['Technical', (bool) $session_contact_is_technical_contact, 'Assets, documents, domains and contacts'],
];

/*
 * Recent activity, both scoped on log_user_id - the portal user this contact
 * signs in as. logAudit() fills that from the $session_user_id global, and
 * login.php deliberately sets it before logging a client login ("Option B" in
 * that file), so both the sign-ins and the actions carry it. An agent working
 * on this client writes their own user id, so their work never appears here.
 *
 * Successful sign-ins only. Failed attempts are logged without a reliable user
 * id - login.php cannot always resolve one from a bad email - so they cannot
 * honestly be attributed to this contact.
 */
$sql_logins = mysqli_query(
    $mysqli,
    "SELECT log_created_at, log_ip FROM logs
    WHERE log_type = 'Client Login'
    AND log_action = 'Success'
    AND log_user_id = $session_user_id
    AND log_client_id = $session_client_id
    ORDER BY log_id DESC
    LIMIT 5"
);

$sql_actions = mysqli_query(
    $mysqli,
    "SELECT log_action, log_created_at, log_description, log_type FROM logs
    WHERE log_user_id = $session_user_id
    AND log_client_id = $session_client_id
    AND log_type != 'Client Login'
    ORDER BY log_id DESC
    LIMIT 5"
);

?>

<h3>Profile</h3>
<hr>

<div class="row">

    <div class="col-lg-7 mb-4">

        <table class="table table-bordered border border-dark">
            <thead class="table-dark">
            <tr>
                <th colspan="2">Your details</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th class="w-25">Name</th>
                <td><?= $contact_name ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?= $contact_email ?></td>
            </tr>
            <?php if (!empty($contact_title)) { ?>
                <tr>
                    <th>Title</th>
                    <td><?= $contact_title ?></td>
                </tr>
            <?php } ?>
            <?php if (!empty($contact_department)) { ?>
                <tr>
                    <th>Department</th>
                    <td><?= $contact_department ?></td>
                </tr>
            <?php } ?>
            <tr>
                <th>Company</th>
                <td><?= $client_name ?></td>
            </tr>
            <?php if (!empty($contact_location)) { ?>
                <tr>
                    <th>Location</th>
                    <td>
                        <?= $contact_location ?>
                        <?php if (!empty($contact_location_address)) { ?>
                            <br><small class="text-muted"><?= $contact_location_address ?></small>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <th>Phone</th>
                <td>
                    <?php if (empty($contact_phone)) { ?>
                        <span class="text-muted">Not set</span>
                    <?php } else { ?>
                        <?= $contact_phone ?>
                        <?php if (!empty($contact_extension)) { ?>
                            <span class="text-muted">ext. <?= $contact_extension ?></span>
                        <?php } ?>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end"
                        data-bs-toggle="modal" data-bs-target="#phoneModal">Edit</button>
                </td>
            </tr>
            <tr>
                <th>Mobile</th>
                <td>
                    <?php if (empty($contact_mobile)) { ?>
                        <span class="text-muted">Not set</span>
                    <?php } else { ?>
                        <?= $contact_mobile ?>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end"
                        data-bs-toggle="modal" data-bs-target="#phoneModal">Edit</button>
                </td>
            </tr>
            </tbody>
        </table>

        <small class="text-muted">
            To change your name, email, title, department or location, raise a ticket and we will
            update them for you.
        </small>

    </div>

    <div class="col-lg-5 mb-4">

        <table class="table table-bordered border border-dark">
            <thead class="table-dark">
            <tr>
                <th colspan="2">Portal access</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($contact_roles as $contact_role) { ?>
                <tr>
                    <th class="w-50">
                        <?= $contact_role[0] ?> contact
                        <br><small class="text-muted fw-normal"><?= $contact_role[2] ?></small>
                    </th>
                    <td class="align-middle">
                        <?php if ($contact_role[1]) { ?>
                            <span class="p-2 badge text-bg-success">Yes</span>
                        <?php } else { ?>
                            <span class="p-2 badge text-bg-secondary">No</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <th>Sign in with</th>
                <td class="align-middle">
                    <?= $login_method_display ?>
                    <?php if ($login_method === 'local') { ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary float-end"
                            data-bs-toggle="modal" data-bs-target="#passwordModal">Change</button>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <th>
                    Phone PIN
                    <br><small class="text-muted fw-normal">Confirms it is you when you call</small>
                </th>
                <td class="align-middle">
                    <?php if (empty($contact_pin)) { ?>
                        <span class="text-muted">Not set</span>
                    <?php } else { ?>
                        <span class="font-monospace"><?= $contact_pin ?></span>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end"
                        data-bs-toggle="modal" data-bs-target="#pinModal">
                        <?= empty($contact_pin) ? 'Set' : 'Change' ?>
                    </button>
                </td>
            </tr>
            </tbody>
        </table>

    </div>

</div>

<div class="row">

    <div class="col-lg-5 mb-4">

        <table class="table table-bordered border border-dark">
            <thead class="table-dark">
            <tr>
                <th colspan="2">Recent sign-ins</th>
            </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($sql_logins) == 0) { ?>
                <tr>
                    <td colspan="2" class="text-muted">No sign-ins recorded yet.</td>
                </tr>
            <?php } else { ?>
                <?php while ($row = mysqli_fetch_assoc($sql_logins)) { ?>
                    <tr>
                        <td><?= portalDateTime($row['log_created_at']) ?></td>
                        <td class="font-monospace"><?= escapeHtml($row['log_ip']) ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
            </tbody>
        </table>

        <small class="text-muted">
            Somewhere here you do not recognise? Raise a ticket and change your password.
        </small>

    </div>

    <div class="col-lg-7 mb-4">

        <table class="table table-bordered border border-dark">
            <thead class="table-dark">
            <tr>
                <th colspan="2">Recent activity</th>
            </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($sql_actions) == 0) { ?>
                <tr>
                    <td colspan="2" class="text-muted">Nothing recorded yet.</td>
                </tr>
            <?php } else { ?>
                <?php while ($row = mysqli_fetch_assoc($sql_actions)) { ?>
                    <tr>
                        <td class="text-nowrap"><?= portalDateTime($row['log_created_at']) ?></td>
                        <td><?= escapeHtml($row['log_description']) ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
            </tbody>
        </table>

        <a href="activity.php"><i class="fa fa-fw fa-list me-2"></i>View all your activity</a>

    </div>

</div>

<p class="text-muted">
    <small>Portal user ID <?= intval($_SESSION['user_id']) ?> &mdash; quote this if we ask for it.</small>
</p>


<div class="modal fade" id="phoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white"><i class="fa fa-fw fa-phone me-2"></i>Your phone numbers</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <p class="text-muted">
                        Keeping these current means we can reach you about a ticket without hunting
                        for a number. Clear a field to remove it.
                    </p>

                    <label for="contactPhone">Phone / <span class="text-secondary">Extension</span></label>
                    <div class="row g-2">
                        <div class="col-8 mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                <input type="hidden" name="phone_country_code" value="<?= $contact_phone_country_code ?>">
                                <input type="tel" class="form-control" id="contactPhone" name="phone"
                                    placeholder="Phone Number" maxlength="200" value="<?= $contact_phone_input ?>"
                                    data-itflow-phone="phone_country_code">
                            </div>
                        </div>
                        <div class="col-4 mb-3">
                            <input type="text" class="form-control" id="contactExtension" name="extension"
                                inputmode="numeric" maxlength="200" placeholder="ext."
                                value="<?= $contact_extension ?>">
                        </div>
                    </div>

                    <label for="contactMobile">Mobile</label>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-mobile-alt"></i></span>
                            <input type="hidden" name="mobile_country_code" value="<?= $contact_mobile_country_code ?>">
                            <input type="tel" class="form-control" id="contactMobile" name="mobile"
                                placeholder="Mobile Number" maxlength="200" value="<?= $contact_mobile_input ?>"
                                data-itflow-phone="mobile_country_code">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="set_contact_phone" class="btn btn-primary text-bold">
                        <i class="fas fa-check me-2"></i>Save
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-fw fa-key me-2"></i><?= empty($contact_pin) ? 'Set a phone PIN' : 'Change your phone PIN' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <p class="text-muted">
                        We ask for this to confirm it is really you when you call. Pick something you
                        will remember but that is not easy to guess.
                    </p>

                    <div class="mb-3">
                        <label for="contactPin"><?= empty($contact_pin) ? 'New PIN' : 'Replace with' ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            <input type="text" class="form-control" id="contactPin" name="pin"
                                minlength="4" maxlength="255" placeholder="At least 4 characters"
                                autocomplete="off" required>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="set_contact_pin" class="btn btn-primary text-bold">
                        <i class="fas fa-check me-2"></i><?= empty($contact_pin) ? 'Save PIN' : 'Update PIN' ?>
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($login_method === 'local') { ?>
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="modal-header bg-dark">
                    <h5 class="modal-title text-white"><i class="fa fa-fw fa-lock me-2"></i>Change your password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="mb-3">
                        <label for="newPassword">New password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" id="newPassword" name="new_password"
                                minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_profile" class="btn btn-primary text-bold">
                        <i class="fas fa-check me-2"></i>Save password
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php
require_once 'includes/footer.php';
