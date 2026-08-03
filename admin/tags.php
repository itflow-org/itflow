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

// Tag types shown in the left nav
$tag_types = [
    1 => ['label' => 'Client',     'icon' => 'fa-users'],
    2 => ['label' => 'Location',   'icon' => 'fa-map-marker-alt'],
    3 => ['label' => 'Contact',    'icon' => 'fa-address-book'],
    4 => ['label' => 'Credential', 'icon' => 'fa-key'],
    5 => ['label' => 'Asset',      'icon' => 'fa-desktop'],
];

// Label for the selected type
$tag_type_display = $tag_types[$type_filter]['label'] ?? 'Unknown';

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tags
    WHERE tag_name LIKE '%$q%'
    AND tag_type = $type_filter
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Row count per type for the nav badges
$tag_type_counts = [];
$sql_tag_type_counts = mysqli_query($mysqli, "SELECT tag_type, COUNT(tag_id) AS tag_type_count FROM tags GROUP BY tag_type");
while ($row = mysqli_fetch_assoc($sql_tag_type_counts)) {
    $tag_type_counts[intval($row['tag_type'])] = intval($row['tag_type_count']);
}

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

                <!-- Tag types -->
                <div class="col-md-3 border-right mb-3">
                    <ul class="nav nav-pills flex-column bg-light">
                        <?php foreach ($tag_types as $tag_type => $tag_type_details) { ?>
                        <li class="nav-item">
                            <a class="nav-link<?php if ($type_filter == $tag_type) {
                                echo ' active';
                            } ?>" href="?type=<?= $tag_type ?>">
                                <i class="fa fa-fw <?= $tag_type_details['icon'] ?> mr-2"></i><?= escapeHtml($tag_type_details['label']) ?>
                                <span class="badge badge-pill badge-dark float-right mt-1"><?= $tag_type_counts[$tag_type] ?? 0 ?></span>
                            </a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>

                <!-- Tags -->
                <div class="col-md-9">
                    <form autocomplete="off">
                        <input type="hidden" name="type" value="<?= $type_filter ?>">
                        <?php if ($archived) { ?>
                        <input type="hidden" name="archived" value="1">
                        <?php } ?>
                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <div class="input-group">
                                    <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search <?= $tag_type_display ?> Tags">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <a href="?<?= $url_query_strings_sort ?>&archived=1"
                                    class="btn float-right <?php if (isset($_GET['archived'])) {
                                        echo 'btn-primary';
                                    } else {
                                        echo 'btn-default';
                                    } ?>"><i
                                        class="fas fa-fw fa-archive mr-2"></i>Archived</a>
                            </div>
                        </div>
                    </form>
                    <hr>
                    <div class="table-responsive-sm">
                        <table class="table table-striped table-borderless table-hover">
                            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                            <tr>
                                <th>
                                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=tag_name&order=<?= $disp ?>">
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
                                            <span class='badge text-light p-2 mr-1' style="background-color: <?= $tag_color ?>"><i class="fa fa-fw fa-<?= $tag_icon ?> mr-2"></i><?= $tag_name ?></span>
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
                                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_tag=<?= $tag_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
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
        </div>
    </div>

<?php

require_once "../includes/footer.php";
