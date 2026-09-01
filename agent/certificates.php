<?php

// Default Column Sortby Filter
$sort = "certificate_name";
$order = "ASC";

// If client_id is in URI then show client Side Bar and client header
if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_query = "AND certificate_client_id = $client_id";
    $client_url = "client_id=$client_id&";
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "certificate_archived_at IS NOT NULL";
    } else {
        $archived = 0;
        $archive_query = "certificate_archived_at IS NULL";
    }
} else {
    require_once "includes/inc_client_overview_all.php";
    $client_query = '';
    $client_url = '';
    // Overide Filter Header Archived
    if (isset($_GET['archived']) && $_GET['archived'] == 1) {
        $archived = 1;
        $archive_query = "(client_archived_at IS NOT NULL OR certificate_archived_at IS NOT NULL)";
    } else {
        $archived = 0;
        $archive_query = "(client_archived_at IS NULL AND certificate_archived_at IS NULL)";
    }
}

// Perms
enforceUserPermission('module_support');

// Expiring In Filter
if (isset($_GET['expire_days']) && !empty($_GET['expire_days'])) {
    if ($_GET['expire_days'] == "expired") {
        $expire_days = "expired";
        $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire != '0000-00-00' AND certificate_expire < CURDATE())";
    } else {
        $expire_days = intval($_GET['expire_days']);
        $expire_query = "AND (certificate_expire IS NOT NULL AND certificate_expire != '0000-00-00' AND certificate_expire BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $expire_days DAY))";
    }
} else {
    // Default - any
    $expire_days = '';
    $expire_query = '';
}

if (!$client_url) {
    // Client Filter
    if (isset($_GET['client']) & !empty($_GET['client'])) {
        $client_query = 'AND (certificate_client_id = ' . intval($_GET['client']) . ')';
        $client = intval($_GET['client']);
    } else {
        // Default - any
        $client_query = '';
        $client = '';
    }
}

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS certificate_archived_at, certificate_created_at, certificate_description,
    certificate_domain, certificate_expire, certificate_id, certificate_issued_by,
    certificate_name, client_id, client_name FROM certificates
    LEFT JOIN clients ON client_id = certificate_client_id
    WHERE $archive_query
    AND (certificate_name LIKE '%$q%' OR certificate_domain LIKE '%$q%' OR certificate_description LIKE '%$q%' OR certificate_issued_by LIKE '%$q%' OR client_name LIKE '%$q%')
    " . clientScopeSql('certificate_client_id') . "
    $client_query
    $expire_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-lock me-2"></i>Certificates</h3>
        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/certificate/certificate_add.php?<?= $client_url ?>"><i class="fas fa-plus me-2"></i>New Certificate</button>
                <?php if ($num_rows[0] > 0) { ?>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark ajax-modal" href="#"\
                            data-modal-url="<?= buildExportModalUrl('modals/certificate/certificate_export.php', ['client_id', 'client', 'expire_days', 'archived', 'q']) ?>">
                            <i class="fa fa-fw fa-download me-2"></i>Export
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($client_url) { ?>
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <?php } ?>
            <input type="hidden" name="archived" value="<?= $archived ?>">
            <div class="row g-2 align-items-center">

                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Certificates">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <?php if (!$client_url) { ?>
                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-select select2" name="client" onchange="this.form.submit()">
                            <option value="" <?php if ($client == "") { echo "selected"; } ?>>- All Clients -</option>

                            <?php
                            $sql_clients_filter = mysqli_query($mysqli, "
                                SELECT DISTINCT client_id, client_name
                                FROM clients
                                JOIN certificates ON certificate_client_id = client_id
                                WHERE $archive_query
                                " . clientScopeSql('clients.client_id') . "
                                ORDER BY client_name ASC
                            ");
                            while ($row = mysqli_fetch_assoc($sql_clients_filter)) {
                                $client_id = intval($row['client_id']);
                                $client_name = escapeHtml($row['client_name']);
                            ?>
                                <option <?php if ($client == $client_id) { echo "selected"; } ?> value="<?= $client_id ?>"><?= $client_name ?></option>
                            <?php
                            }
                            ?>

                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="col-md-2">
                    <div class="input-group">
                        <select class="form-select select2" name="expire_days" onchange="this.form.submit()">
                            <option value="" <?php if ($expire_days == "") { echo "selected"; } ?>>- Expiring In -</option>
                            <option value="expired" <?php if ($expire_days === "expired") { echo "selected"; } ?>>Expired</option>
                            <option value="7" <?php if ($expire_days === 7) { echo "selected"; } ?>>7 Days</option>
                            <option value="30" <?php if ($expire_days === 30) { echo "selected"; } ?>>30 Days</option>
                            <option value="45" <?php if ($expire_days === 45) { echo "selected"; } ?>>45 Days</option>
                            <option value="60" <?php if ($expire_days === 60) { echo "selected"; } ?>>60 Days</option>
                            <option value="90" <?php if ($expire_days === 90) { echo "selected"; } ?>>90 Days</option>
                        </select>
                    </div>
                </div>

                <?php if ($client_url) { // filler ?>
                <div class="col-md-2"></div>
                <?php } ?>

                <div class="col-md-4">
                    <div class="btn-group float-end">
                        <a href="?<?= $client_url ?>archived=<?php if($archived == 1){ echo 0; } else { echo 1; } ?>"
                            class="btn btn-<?php if($archived == 1){ echo"primary"; } else { echo "default"; } ?>">
                            <i class="fa fa-fw fa-archive me-2"></i>Archived
                        </a>
                        <div class="dropdown ms-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item"
                                        type="submit" form="bulkActions" name="bulk_refresh_certificates">
                                    <i class="fas fa-fw fa-sync-alt me-2"></i>Refresh Certificates
                                </button>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_certificates">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
    <div class="table-responsive">

        <form id="bulkActions" action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <td class="checkbox-column border-end">
                        <div class="form-check">
                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                        </div>
                    </td>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=certificate_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'certificate_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=certificate_domain&order=<?= $disp ?>">
                            Domain <?php if ($sort == 'certificate_domain') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=certificate_issued_by&order=<?= $disp ?>">
                            Issued By <?php if ($sort == 'certificate_issued_by') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=certificate_expire&order=<?= $disp ?>">
                            Expire <?php if ($sort == 'certificate_expire') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php if (!$client_url) { ?>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=client_name&order=<?= $disp ?>">
                            Client <?php if ($sort == 'client_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <?php } ?>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $client_id = intval($row['client_id']);
                    $client_name = escapeHtml($row['client_name']);
                    $certificate_id = intval($row['certificate_id']);
                    $certificate_name = escapeHtml($row['certificate_name']);
                    $certificate_description = escapeHtml($row['certificate_description']);
                    $certificate_domain = escapeHtml($row['certificate_domain']);
                    $certificate_issued_by = escapeHtml($row['certificate_issued_by']);
                    $certificate_expire = escapeHtml($row['certificate_expire']);
                    $certificate_created_at = escapeHtml($row['certificate_created_at']);
                    $certificate_archived_at = escapeHtml($row['certificate_archived_at']);

                    $certificate_expire_ago = timeAgo($certificate_expire);
                    // Convert the expiry date to a timestamp
                    $certificate_expire_timestamp = strtotime($row['certificate_expire']);
                    $current_timestamp = time(); // Get current timestamp

                    // Calculate the difference in days
                    $days_until_expiry = ($certificate_expire_timestamp - $current_timestamp) / (60 * 60 * 24);

                    // Determine the class based on the number of days until expiry
                    if ($days_until_expiry <= 0) {
                        $tr_class = "table-secondary";
                    } elseif ($days_until_expiry <= 1) {
                        $tr_class = "table-danger";
                    } elseif ($days_until_expiry <= 7) {
                        $tr_class = "table-warning";
                    } else {
                        $tr_class = '';
                    }

                    ?>
                    <tr class="<?= $tr_class ?>">
                        <td class="checkbox-column bg-light border-end">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="certificate_ids[]" value="<?= $certificate_id ?>">
                                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                            </div>
                        </td>
                        <td>
                            <a class="text-dark ajax-modal" href="#"
                                data-modal-url="modals/certificate/certificate_edit.php?<?= $client_url ?>&id=<?= $certificate_id ?>">
                                <div class="d-flex">
                                    <i class="fa fa-fw fa-2x fa-lock me-3"></i>
                                    <div class="flex-grow-1">
                                        <div><?= $certificate_name ?></div>
                                        <div><small class="text-secondary"><?= $certificate_description ?></small></div>
                                    </div>
                                </div>
                            </a>
                        </td>
                        <td><?= $certificate_domain ?></td>

                        <td><?= $certificate_issued_by ?></td>

                        <td>
                            <div><?= $certificate_expire ?: '-' ?></div>
                            <?php if (!empty($certificate_expire)) { ?>
                                <div><small><?= $certificate_expire_ago ?></small></div>
                            <?php } ?>
                        </td>
                        <?php if (!$client_url) { ?>
                        <td><a href="certificates.php?client_id=<?= $client_id ?>"><?= $client_name ?></a></td>
                        <?php } ?>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#"
                                        data-modal-url="modals/certificate/certificate_edit.php?<?= $client_url ?>&id=<?= $certificate_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                     <a class="dropdown-item" href="post.php?refresh_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-sync-alt me-2"></i>Refresh Certificate
                                    </a>
                                    <?php if ($session_user_role == 3) { ?>
                                        <?php if ($certificate_archived_at) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-info confirm-link" href="post.php?restore_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-redo me-2"></i>Restore
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash me-2"></i>Delete
                                        </a>
                                        <?php } else { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_certificate=<?= $certificate_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-archive me-2"></i>Archive
                                        </a>
                                        <?php } ?>

                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <?php
                }
                ?>

                </tbody>
            </table>

        </form>
    </div>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php require_once "../includes/footer.php";
