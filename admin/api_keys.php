<?php

// Default Column Sortby Filter
$sort = "api_key_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM api_keys
    LEFT JOIN clients on api_keys.api_key_client_id = clients.client_id
    WHERE (api_key_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-key mr-2"></i>API Keys</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addApiKeyModal"><i class="fas fa-plus mr-2"></i>Create</button>
            </div>
        </div>

        <div class="card-body">

            <form autocomplete="off">
                <div class="row">

                    <div class="col-md-4">
                        <div class="input-group mb-3 mb-md-0">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search keys">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="btn-group float-right">
                            <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                    <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item text-danger text-bold"
                                            type="submit" form="bulkActions" name="bulk_delete_api_keys">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Revoke
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </form>
            <hr>

            <div class="table-responsive-sm">

                <form id="bulkActions" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                    <table class="table table-striped table-borderless table-hover">
                        <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                        <tr>
                            <td class="pr-0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" onclick="checkAll(this)">
                                </div>
                            </td>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=api_key_name&order=<?php echo $disp; ?>">
                                    Name <?php if ($sort == 'api_key_name') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=api_key_client_id&order=<?php echo $disp; ?>">
                                    Client <?php if ($sort == 'api_key_client_id') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=api_key_secret&order=<?php echo $disp; ?>">
                                    Secret <?php if ($sort == 'api_key_secret') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=api_key_created_at&order=<?php echo $disp; ?>">
                                    Created <?php if ($sort == 'api_key_created_at') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=api_key_expire&order=<?php echo $disp; ?>">
                                    Expires <?php if ($sort == 'api_key_expire') { echo $order_icon; } ?>
                                </a>
                            </th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        while ($row = mysqli_fetch_array($sql)) {
                            $api_key_id = intval($row['api_key_id']);
                            $api_key_name = nullable_htmlentities($row['api_key_name']);
                            $api_key_secret = nullable_htmlentities("************" . substr($row['api_key_secret'], -4));
                            $api_key_created_at = nullable_htmlentities($row['api_key_created_at']);
                            $api_key_expire = nullable_htmlentities($row['api_key_expire']);
                            if ($api_key_expire < date("Y-m-d H:i:s")) {
                                $api_key_expire = $api_key_expire . " (Expired)";
                            }

                            if ($row['api_key_client_id'] == 0) {
                                $api_key_client = "<i>All Clients</i>";
                            } else {
                                $api_key_client = nullable_htmlentities($row['client_name']);
                            }

                            ?>
                            <tr>
                                <td class="pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input bulk-select" type="checkbox" name="api_key_ids[]" value="<?php echo $api_key_id ?>">
                                    </div>
                                </td>

                                <td class="text-bold"><?php echo $api_key_name; ?></td>

                                <td><?php echo $api_key_client; ?></td>

                                <td><?php echo $api_key_secret; ?></td>

                                <td><?php echo $api_key_created_at; ?></td>

                                <td><?php echo $api_key_expire; ?></td>

                                <td>
                                    <div class="dropdown dropleft text-center">
                                        <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_api_key=<?php echo $api_key_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">
                                                <i class="fas fa-fw fa-times mr-2"></i>Revoke
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                        <?php } ?>


                        </tbody>
                    </table>

                </form>

            </div>
            <?php require_once "../includes/filter_footer.php";
 ?>
        </div>
    </div>

    <script src="../js/bulk_actions.js"></script>

<?php
require_once "modals/api/api_key_add.php";

require_once "../includes/footer.php";

