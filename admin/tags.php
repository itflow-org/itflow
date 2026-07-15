<?php

// Default Column Sortby Filter
$sort = "tag_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

if (isset($_GET['type'])) {
    $type_filter = intval($_GET['type']);
} else {
    $type_filter = 1;
}

if ($type_filter == 1) {
    $tag_type_display = "Client";
} elseif ( $type_filter == 2) {
    $tag_type_display = "Location";
} elseif ( $type_filter == 3) {
    $tag_type_display = "Contact";
} elseif ( $type_filter == 4) {
    $tag_type_display = "Credential";
 } elseif ( $type_filter == 5) {
    $tag_type_display = "Asset";
} else {
    $tag_type_display = "Unknown";
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tags
    WHERE tag_name LIKE '%$q%'
    AND tag_type = $type_filter
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-tags mr-2"></i><?= $tag_type_display ?> Tags</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/tag/tag_add.php?type=<?= $type_filter ?>"><i class="fas fa-plus mr-2"></i>New <?= $tag_type_display ?> Tag</button>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-sm-4 mb-2">
                    <form autocomplete="off">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= $tag_type_display ?> Tags">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group float-right">
                        <a href="?type=1"
                            class="btn <?php if ($type_filter == 1) {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Client</a>
                        <a href="?type=2"
                            class="btn <?php if ($type_filter == 2) {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Location</a>
                        <a href="?type=3"
                            class="btn <?php if ($type_filter == 3) {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>">Contact</a>
                        <a href="?type=4"
                           class="btn <?php if ($type_filter == 4) {
                               echo 'btn-primary';
                           } else {
                               echo 'btn-default';
                           } ?>">Credential</a>
                        <a href="?type=5"
                           class="btn <?php if ($type_filter == 5) {
                               echo 'btn-primary';
                           } else {
                               echo 'btn-default';
                           } ?>">Asset</a>
                        <a href="?<?= $url_query_strings_sort ?>&archived=1"
                            class="btn <?php if (isset($_GET['archived'])) {
                                echo 'btn-primary';
                            } else {
                                echo 'btn-default';
                            } ?>"><i
                                class="fas fa-fw fa-archive mr-2"></i>Archived</a>
                    </div>
                </div>
            </div>

            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th>
                            <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tag_name&order=<?php echo $disp; ?>">
                                Name <?php if ($sort == 'tag_name') { echo $order_icon; } ?>
                            </a>
                        </th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_assoc($sql)) {
                        $tag_id = intval($row['tag_id']);
                        $tag_name = escapeHtml($row['tag_name']);
                        $tag_color = escapeHtml($row['tag_color']);
                        $tag_icon = escapeHtml($row['tag_icon']);

                        ?>
                        <tr>
                            <td>
                                <a class="ajax-modal" href="#"
                                    data-modal-url="modals/tag/tag_edit.php?id=<?= $tag_id ?>">
                                    <span class='badge text-light p-2 mr-1' style="background-color: <?php echo $tag_color; ?>"><i class="fa fa-fw fa-<?php echo $tag_icon; ?> mr-2"></i><?php echo $tag_name; ?></span>
                                </a>
                            </td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item ajax-modal" href="#"
                                            data-modal-url="modals/tag/tag_edit.php?id=<?= $tag_id ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_tag=<?php echo $tag_id; ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once "../includes/filter_footer.php"; ?>
        </div>
    </div>

<?php

require_once "../includes/footer.php";
