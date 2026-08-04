<?php

// Default Column Sortby Filter
$sort = "category_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";


if (isset($_GET['category'])) {
    $category = escapeSql($_GET['category']);
} else {
    $category = "Expense";
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM categories
    WHERE category_name LIKE '%$q%'
    AND category_type = '$category'
    AND category_$archive_query
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);
$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

// Category types shown in the left nav
$category_types = [
    'Expense'           => ['label' => 'Expense',           'icon' => 'fa-shopping-cart'],
    'Income'            => ['label' => 'Income',            'icon' => 'fa-hand-holding-usd'],
    'Referral'          => ['label' => 'Referral',          'icon' => 'fa-share-alt'],
    'Ticket'            => ['label' => 'Ticket',            'icon' => 'fa-life-ring'],
    'network_interface' => ['label' => 'Network Interface', 'icon' => 'fa-ethernet'],
    'asset_status'      => ['label' => 'Asset Status',      'icon' => 'fa-heartbeat'],
    'software_type'     => ['label' => 'Software Type',     'icon' => 'fa-cube'],
    'rack_type'         => ['label' => 'Rack Type',         'icon' => 'fa-server'],
    'contact_note_type' => ['label' => 'Contact Note Type', 'icon' => 'fa-address-book'],
    'asset_note_type'   => ['label' => 'Asset Note Type',   'icon' => 'fa-desktop'],
];

// Label for the selected type, falling back for anything not in the map
$category_label = $category_types[$category]['label'] ?? ucwords(str_replace('_', ' ', $category));

// Row count per type for the nav badges, respecting the archived view
$category_type_counts = [];
$sql_category_type_counts = mysqli_query(
    $mysqli,
    "SELECT category_type, COUNT(category_id) AS category_type_count FROM categories
    WHERE category_$archive_query
    GROUP BY category_type"
);
while ($row = mysqli_fetch_assoc($sql_category_type_counts)) {
    $category_type_counts[$row['category_type']] = intval($row['category_type_count']);
}

// Archived nav item toggles the view while holding the selected type/search
$archive_toggle_query = $_GET;
unset($archive_toggle_query['page']);
$archive_toggle_query['category'] = $category;
if ($archived) {
    unset($archive_toggle_query['archived']);
} else {
    $archive_toggle_query['archived'] = 1;
}
$archive_toggle_url = '?' . http_build_query($archive_toggle_query);

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-list-ul mr-2"></i>
            <?= escapeHtml($category_label) ?> Categories
        </h3>
        <?php
            if (!isset($_GET['archived'])) {
        ?>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/category/category_add.php?category=<?= escapeHtml($category) ?>"><i
                    class="fas fa-plus mr-2"></i>New <?= escapeHtml($category_label) ?> Category</button>
        </div>
        <?php
            }
        ?>
    </div>
    <div class="card-body">
        <div class="row">

            <!-- Category types -->
            <div class="col-md-3 border-right mb-3">
                <ul class="nav nav-pills flex-column bg-light">
                    <?php foreach ($category_types as $category_type => $category_type_details) { ?>
                    <li class="nav-item">
                        <a class="nav-link<?php if ($category == $category_type) {
                            echo ' active';
                        } ?>" href="?category=<?= urlencode($category_type) ?>">
                            <i class="fa fa-fw <?= $category_type_details['icon'] ?> mr-2"></i><?= escapeHtml($category_type_details['label']) ?>
                            <span class="badge badge-pill badge-dark float-right mt-1"><?= $category_type_counts[$category_type] ?? 0 ?></span>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>

            <!-- Categories -->
            <div class="col-md-9">
                <form autocomplete="off">
                    <input type="hidden" name="category" value="<?= escapeHtml($category) ?>">
                    <?php if ($archived) { ?>
                    <input type="hidden" name="archived" value="1">
                    <?php } ?>
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <div class="input-group">
                                <input type="search" class="form-control" name="q"
                                    value="<?php if (isset($q)) {
                                        echo stripslashes(escapeHtml($q));
                                    } ?>"
                                    placeholder="Search <?= escapeHtml($category_label) ?> Categories ">
                                <div class="input-group-append">
                                    <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-2">
                            <a href="<?= $archive_toggle_url ?>"
                                class="btn float-right <?php if ($archived) {
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
                                    <a class="text-dark" href="?<?= $url_query_strings_sort ?>&sort=category_name&order=<?= $disp ?>">
                                        Name <?php if ($sort == 'category_name') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>Color</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                            while ($row = mysqli_fetch_assoc($sql)) {
                                $category_id = intval($row['category_id']);
                                $category_name = escapeHtml($row['category_name']);
                                $category_description = escapeHtml($row['category_description']);
                                $category_color = escapeHtml($row['category_color']);

                                ?>
                                <tr>
                                    <td>
                                        <a class="text-dark ajax-modal" href="#"
                                            data-modal-url="modals/category/category_edit.php?id=<?= $category_id ?>">
                                            <?= $category_name ?>
                                            <div><small class="text-secondary"><?= $category_description ?></small></div>
                                        </a>
                                    </td>
                                    <td><i class="fa fa-3x fa-circle" style="color:<?= $category_color ?>;"></i></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <?php
                                                if ($archived) {
                                                    ?>
                                                    <a class="dropdown-item text-info confirm-link"
                                                        href="post.php?restore_category=<?= $category_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-redo mr-2"></i>Restore
                                                    </a>
                                                    <a class="dropdown-item text-danger confirm-link"
                                                        href="post.php?delete_category=<?= $category_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                    <?php
                                                } else {
                                                    ?>
                                                    <a class="dropdown-item ajax-modal" href="#"
                                                        data-modal-url="modals/category/category_edit.php?id=<?= $category_id ?>">
                                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                                    </a>
                                                    <a class="dropdown-item text-danger confirm-link"
                                                        href="post.php?archive_category=<?= $category_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                                        <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                    </a>
                                                    <?php
                                                }
                                                ?>
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
