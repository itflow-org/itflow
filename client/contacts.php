<?php
/*
* Client Portal
* Contact management for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

enforceContactCan('contacts');

$contacts_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_email, contact_primary, contact_technical, contact_billing FROM contacts WHERE contact_client_id = $session_client_id AND contacts.contact_archived_at IS NULL ORDER BY contact_created_at");
?>

    <div class="row">
        <div class="col">
            <h3>Contacts</h3>
        </div>
        <div class="col offset-6">
            <a href="contact_add.php" class="btn btn-primary" role="button"><i class="fas fa-plus me-2"></i>New Contact</a>
        </div>
    </div>

    <div class="row">

        <div class="col-md-10">

            <?php if (mysqli_num_rows($contacts_sql) == 0) { ?>
                <?= portalEmptyState('There are no contacts on this account yet.') ?>
            <?php } else { ?>
            <table class="table table-bordered border border-dark">
                <thead class="table-dark">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Roles</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_assoc($contacts_sql)) {
                    $contact_id = intval($row['contact_id']);
                    $contact_name = escapeHtml($row['contact_name']);
                    $contact_email = escapeHtml($row['contact_email']);
                    $contact_primary = intval($row['contact_primary']);
                    $contact_technical = intval($row['contact_technical']);
                    $contact_billing = intval($row['contact_billing']);

                    $contact_roles_display = '-';
                    if ($contact_primary) {
                        $contact_roles_display = 'Primary contact';
                    } else if ($contact_technical && $contact_billing) {
                        $contact_roles_display = 'Technical & Billing';
                    } else if ($contact_technical) {
                        $contact_roles_display = 'Technical';
                    } else if ($contact_billing) {
                        $contact_roles_display = 'Billing';
                    }

                    ?>

                    <tr>
                        <td><a href="contact_edit.php?id=<?= $contact_id ?>"><?= $contact_name ?></a></td>
                        <td><?= $contact_email ?></td>
                        <td><?= $contact_roles_display ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>
            <?php } ?>

        </div>

    </div>

<?php
require_once "includes/footer.php";
