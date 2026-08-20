<?php

// Default Column Sortby Filter
$sort = "api_key_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS api_key_created_at, api_key_expire, api_key_id, api_key_name, api_key_secret, user_name FROM api_keys
    LEFT JOIN users on api_key_user_id = user_id
    WHERE (api_key_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-key me-2"></i>API Keys</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/api/api_key_add.php"><i class="fas fa-plus me-2"></i>New API Key</button>
        </div>
    </div>

    <div class="card-header py-3">

        <form autocomplete="off">
            <div class="row g-2 align-items-center">

                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search keys">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="btn-group float-end">
                        <div class="dropdown ms-2" id="bulkActionButton" hidden>
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-fw fa-layer-group me-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item text-danger text-bold"
                                        type="submit" form="bulkActions" name="bulk_delete_api_keys">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </form>
    </div>

    <div class="table-responsive-sm">

        <form id="bulkActions" action="post.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <table class="table table-striped table-borderless table-hover mb-0">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <td class="pe-0">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" onclick="checkAll(this)">
                        </div>
                    </td>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=api_key_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'api_key_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=api_key_user_id&order=<?= $disp ?>">
                            User <?php if ($sort == 'api_key_user_id') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=api_key_secret&order=<?= $disp ?>">
                            Secret <?php if ($sort == 'api_key_secret') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=api_key_created_at&order=<?= $disp ?>">
                            Created <?php if ($sort == 'api_key_created_at') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=api_key_expire&order=<?= $disp ?>">
                            Expires <?php if ($sort == 'api_key_expire') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_assoc($sql)) {
                    $api_key_id = intval($row['api_key_id']);
                    $api_key_name = escapeHtml($row['api_key_name']);
                    $api_key_secret = escapeHtml("************" . substr($row['api_key_secret'], -4));
                    $api_key_created_at = escapeHtml($row['api_key_created_at']);
                    $api_key_expire = escapeHtml($row['api_key_expire']);
                    if ($api_key_expire < date("Y-m-d H:i:s")) {
                        $api_key_expire = $api_key_expire . " (Expired)";
                    }

                    $api_key_user = !empty($row['user_name']) ? escapeHtml($row['user_name']) : "<i>None</i>";

                    ?>
                    <tr>
                        <td class="pe-0">
                            <div class="form-check">
                                <input class="form-check-input bulk-select" type="checkbox" name="api_key_ids[]" value="<?= $api_key_id ?>">
                            </div>
                        </td>
                        <td class="text-bold"><?= $api_key_name ?></td>
                        <td><?= $api_key_user ?></td>
                        <td><?= $api_key_secret ?></td>
                        <td><?= $api_key_created_at ?></td>
                        <td><?= $api_key_expire ?></td>
                        <td>
                            <div class="dropdown dropstart text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/api/api_key_edit.php?id=<?= $api_key_id ?>">
                                        <i class="fas fa-fw fa-edit me-2"></i>Edit
                                    </a>
                                    <?php if ($api_key_expire > date("Y-m-d H:i:s")) { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?revoke_api_key=<?= $api_key_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-times me-2"></i>Revoke
                                        </a>
                                    <?php } ?>
                                    <?php if ($api_key_expire < date("Y-m-d H:i:s")) { ?>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_api_key=<?= $api_key_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-times me-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>

                <?php } ?>


                </tbody>
            </table>

        </form>

    </div>
    <?php require_once "../includes/filter_footer.php"; ?>
</div>

<script src="../js/bulk_actions.js"></script>

<?php
require_once "../includes/footer.php";
