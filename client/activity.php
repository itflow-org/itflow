<?php
/*
 * Client Portal
 * Everything this contact has done in the portal, and every sign-in
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

/*
 * No capability gate: this is the contact's own record of their own activity,
 * not a section of the portal. Scoped on log_user_id - the portal user this
 * contact signs in as - so an agent working this client never appears here,
 * and on log_client_id as a second fence.
 */
$page = intval($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}

$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

$log_scope = "log_user_id = $session_user_id AND log_client_id = $session_client_id";

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(log_id) AS total FROM logs WHERE $log_scope"));
$total_records = intval($row['total']);
$total_pages = (int) ceil($total_records / $records_per_page);

// A page number past the end would show nothing at all with no way back
if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $records_per_page;
}

$sql_activity = mysqli_query(
    $mysqli,
    "SELECT log_action, log_created_at, log_description, log_ip, log_type FROM logs
    WHERE $log_scope
    ORDER BY log_id DESC
    LIMIT $records_per_page OFFSET $offset"
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

            <table class="table table-bordered border border-dark">
                <thead class="table-dark">
                <tr>
                    <th>When</th>
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

            <?php if ($total_pages > 1) { ?>
                <div class="row align-items-center">
                    <div class="col-sm">
                        <p class="text-muted mb-0">
                            Page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                            &mdash; <strong><?= $total_records ?></strong> records
                        </p>
                    </div>
                    <div class="col-sm">
                        <ul class="pagination justify-content-sm-end mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                            </li>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php } ?>

        <?php } ?>

    </div>
</div>


<?php
require_once "includes/footer.php";
