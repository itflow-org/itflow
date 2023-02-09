<?php
require_once("inc_all_client.php");

if (isset($_GET['q'])) {
    $q = strip_tags(mysqli_real_escape_string($mysqli, $_GET['q']));
    //Phone Numbers
    $phone_query = preg_replace("/[^0-9]/", '', $q);
    if (empty($phone_query)) {
        $phone_query = $q;
    }
} else {
    $q = "";
    $phone_query = "";
}

// Sort
$sb = "item_created_at";

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM shared_items 
    WHERE item_client_id = $client_id  
    AND item_active = '1'
    AND item_views != item_view_limit
    AND item_expire_at > NOW()  
    AND (item_note LIKE '%$q%') ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fa fa-fw fa-share"></i> Shared Items (Links)</h3>
    </div>
    <div class="card-body">
        <form autocomplete="off">
            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
            <div class="row">

                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Shared Items">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>

            </div>
        </form>
        <hr>
        <div class="table-responsive">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>Item Name</th>
                    <th>Item Type</th>
                    <th>Share Note</th>
                    <th>Views</th>
                    <th>Expires</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $item_id = $row['item_id'];
                    $item_active = htmlentities($row['item_active']);
                    $item_key = htmlentities($row['item_key']);
                    $item_type = htmlentities($row['item_type']);
                    $item_related_id = $row['item_related_id'];
                    $item_note = htmlentities($row['item_note']);
                    $item_views = htmlentities($row['item_views']);
                    $item_view_limit = htmlentities($row['item_view_limit']);
                    $item_created_at = $row['item_created_at'];
                    $item_expire_at = $row['item_expire_at'];

                    if ($item_type == 'Login') {
                        $share_item_sql = mysqli_query($mysqli, "SELECT login_name FROM logins WHERE login_id = '$item_related_id' AND login_client_id = '$client_id'");
                        $share_item = mysqli_fetch_array($share_item_sql);
                        $item_name = htmlentities($share_item['login_name']);
                    } elseif ($item_type == 'Document') {
                        $share_item_sql = mysqli_query($mysqli, "SELECT document_name FROM documents WHERE document_id = '$item_related_id' AND document_client_id = '$client_id'");
                        $share_item = mysqli_fetch_array($share_item_sql);
                        $item_name = htmlentities($share_item['document_name']);
                    } elseif ($item_type == 'File') {
                        $share_item_sql = mysqli_query($mysqli, "SELECT file_name FROM files WHERE file_id = '$item_related_id' AND file_client_id = '$client_id'");
                        $share_item = mysqli_fetch_array($share_item_sql);
                        $item_name = htmlentities($share_item['file_name']);
                    }


                    ?>
                    <tr>
                        <td><?php echo $item_name; ?></td>
                        <td><?php echo $item_type ?></td>
                        <td><?php echo $item_note ?></td>
                        <td><?php echo "$item_views / $item_view_limit" ?></td>
                        <td><?php echo $item_expire_at ?></td>
                        <td>
                            <?php if ($session_user_role == 3) { ?>
                                <div class="dropdown dropleft text-center">
                                    <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item text-danger" href="post.php?deactivate_shared_item=<?php echo $item_id; ?>">Deactivate</a>
                                    </div>
                                </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <?php
                }
                ?>

                </tbody>
            </table>
        </div>
        <?php require_once("pagination.php"); ?>
    </div>
</div>

<?php
require_once("footer.php");
