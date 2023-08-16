<?php

// Default Column Sortby Filter
$sort = "tag_name";
$order = "ASC";

require_once("inc_all_settings.php");

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM tags
    WHERE tag_name LIKE '%$q%'
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-tags mr-2"></i>Tags</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTagModal"><i class="fas fa-plus mr-2"></i>New Tag</button>
            </div>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-sm-4 mb-2">
                    <form autocomplete="off">
                        <div class="input-group">
                            <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Tags">
                            <div class="input-group-append">
                                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-sm-8">
                </div>
            </div>

            <hr>
            <div class="table-responsive-sm">
                <table class="table table-striped table-borderless table-hover">
                    <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                    <tr>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tag_name&order=<?php echo $disp; ?>">Name</a></th>
                        <th><a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=tag_type&order=<?php echo $disp; ?>">Type</a></th>
                        <th>Color</th>
                        <th class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php

                    while ($row = mysqli_fetch_array($sql)) {
                        $tag_id = intval($row['tag_id']);
                        $tag_name = nullable_htmlentities($row['tag_name']);
                        $tag_type = intval($row['tag_type']);
                        $tag_color = nullable_htmlentities($row['tag_color']);
                        $tag_icon = nullable_htmlentities($row['tag_icon']);

                        ?>
                        <tr>
                            <td><?php echo "<i class='fas fa-fw fa-$tag_icon mr-2'></i>"; ?><a class="text-dark" href="#" data-toggle="modal" data-target="#editTagModal<?php echo $tag_id; ?>"><?php echo "$tag_name"; ?></a></td>
                            <td><?php echo $tag_type; ?></td>
                            <td><i class="fa fa-3x fa-circle" style="color:<?php echo $tag_color; ?>;"></i></td>
                            <td>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editTagModal<?php echo $tag_id; ?>">
                                            <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold" href="post.php?delete_tag=<?php echo $tag_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php

                        require("settings_tag_edit_modal.php");

                    }

                    ?>

                    </tbody>
                </table>
            </div>
            <?php require_once("pagination.php"); ?>
        </div>
    </div>

<?php
require_once("settings_tag_add_modal.php");
require_once("footer.php");
