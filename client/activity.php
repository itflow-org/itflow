<?php
/*
 * Client Portal
 * Everything this contact has done in the portal, and every sign-in
 */

// Read by client/includes/header.php and footer.php - see the note there.
// Must be set before inc_all.php, which pulls the header in.
$portal_load_datatables = true;

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

/*
 * No capability gate: this is the contact's own record of their own activity,
 * not a section of the portal. Scoped on log_user_id - the portal user this
 * contact signs in as - so an agent working this client never appears here,
 * and on log_client_id as a second fence.
 *
 * DataTables searches and paginates in the browser, so the whole set is sent at
 * once rather than a page at a time. The cap is there so that a contact with
 * years of history cannot turn this page into a several-megabyte document; it
 * is generous enough that nobody normal will meet it, and the note below says
 * so plainly when they do.
 */
$activity_limit = 1000;

$log_scope = "log_user_id = $session_user_id AND log_client_id = $session_client_id";

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS total FROM logs WHERE $log_scope"));
$total_records = intval($row['total']);

$sql_activity = mysqli_query(
    $mysqli,
    "SELECT log_action, log_created_at, log_description, log_ip, log_type FROM logs
    WHERE $log_scope
    ORDER BY log_id DESC
    LIMIT $activity_limit"
);

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Your activity</h3>
    <a class="btn btn-secondary" href="profile.php"><i class="fa fa-fw fa-user me-2"></i>Back to profile</a>
</div>
<hr>

<div class="row">
    <div class="col-md-12">

        <?php if ($total_records == 0) { ?>

            <?= portalEmptyState('Nothing has been recorded on your account yet.') ?>

        <?php } else { ?>

            <table class="table table-striped table-bordered border border-dark dataTables" style="width:100%">
                <thead class="table-dark">
                <tr>
                    <?php /* Ordering off on this column deliberately. The cell reads
                             "Today at 4:12 PM", and sorting that as text puts October
                             before September and Today next to Tomorrow. The rows
                             arrive newest-first from SQL and the initialiser keeps
                             that order, so the useful sort is the one already applied. */ ?>
                    <th data-dt-order="disable">When</th>
                    <th>Type</th>
                    <th>What happened</th>
                    <th>From</th>
                </tr>
                </thead>
                <tbody>

                <?php

                while ($row = mysqli_fetch_assoc($sql_activity)) {
                    $log_type = escapeHtml($row['log_type']);
                    $log_action = escapeHtml($row['log_action']);
                    $log_description = escapeHtml($row['log_description']);
                    $log_ip = escapeHtml($row['log_ip']);

                    // Sign-ins are the rows people scan this page for, so they
                    // get the accent rather than sitting in the same grey as
                    // every password change
                    if ($row['log_type'] === 'Client Login') {
                        $log_badge_color = 'primary';
                        $log_label = 'Sign-in';
                    } else {
                        $log_badge_color = 'secondary';
                        $log_label = "$log_type $log_action";
                    }

                    ?>

                    <tr>
                        <td class="text-nowrap"><?= portalDateTime($row['log_created_at']) ?></td>
                        <td><span class="p-2 badge text-bg-<?= $log_badge_color ?>"><?= $log_label ?></span></td>
                        <td><?= $log_description ?></td>
                        <td class="font-monospace"><?= $log_ip ?></td>
                    </tr>

                    <?php

                }

                ?>

                </tbody>
            </table>

            <?php if ($total_records > $activity_limit) { ?>
                <small class="text-muted">
                    Showing your most recent <?= $activity_limit ?> records of <?= $total_records ?>.
                    Raise a ticket if you need to go back further.
                </small>
            <?php } ?>

        <?php } ?>

    </div>
</div>


<?php
require_once "includes/footer.php";
