<?php

// Default Column Sortby Filter
$sort = "document_name";
$order = "ASC";

require_once "inc_all_client.php";

// Perms
enforceUserPermission('module_support');

// Folder
if (!empty($_GET['folder_id'])) {
    $folder = intval($_GET['folder_id']);
} else {
    $folder = 0;
}

// Search query SQL snippet
if (!empty($q)) {
    $query_snippet = "AND (MATCH(document_content_raw) AGAINST ('$q') OR document_name LIKE '%$q%')";
} else {
    $query_snippet = ""; // empty
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// Folder ID
$get_folder_id = 0;
if (!empty($_GET['folder_id'])) {
    $get_folder_id = intval($_GET['folder_id']);
}

// Set Folder Location Var used when creating folders
$folder_location = 0;

if ($get_folder_id == 0 && $_GET["q"]) {
    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE document_client_id = $client_id
        AND document_template = 0
        
        AND document_archived_at IS NULL
        $query_snippet
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );
}else{
    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM documents
        LEFT JOIN users ON document_created_by = user_id
        WHERE document_client_id = $client_id
        AND document_template = 0
        AND document_folder_id = $folder
        AND document_archived_at IS NULL
        $query_snippet
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );
}

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2">
                <i class="fa fa-fw fa-folder mr-2"></i>Documents
            </h3>
            <div class="card-tools">

                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentModal">
                        <i class="fas fa-plus mr-2"></i>Create
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#createFolderModal">
                            <i class="fa fa-fw fa-folder-plus mr-2"></i>Folder
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addDocumentFromTemplateModal">From Template</a>
                    </div>
                </div>

            </div>

        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 border-right mb-3">
                    <h4>Folders</h4>
                    <hr>
                    <ul class="nav nav-pills flex-column bg-light">
                        <li class="nav-item">
                            <div class="row">
                                <div class="col-10">
                                    
                                    <?php
                                    // Get a count of documents that have no folder
                                    $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_folder_id = 0 AND document_client_id = $client_id AND document_archived_at IS NULL"));
                                    $num_documents = intval($row['num']);
                                    ?>
                                    <a class="nav-link <?php if ($get_folder_id == 0) { echo "active"; } ?>" href="?client_id=<?php echo $client_id; ?>&folder_id=0">/ <?php if ($num_documents > 0) { echo "<span class='badge badge-pill badge-dark float-right mt-1'>$num_documents</span>"; } ?></a>
                                </div>
                                <div class="col-2">
                                </div>
                            </div>
                        </li>
                        <?php
                        $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_location = $folder_location AND folder_client_id = $client_id ORDER BY folder_name ASC");
                        while ($row = mysqli_fetch_array($sql_folders)) {
                            $folder_id = intval($row['folder_id']);
                            $folder_name = nullable_htmlentities($row['folder_name']);

                            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_folder_id = $folder_id AND document_archived_at IS NULL"));
                            $num_documents = intval($row['num']);

                            ?>

                            <li class="nav-item">
                                <div class="row">
                                    <div class="col-10">
                                        <a class="nav-link <?php if ($get_folder_id == $folder_id) { echo "active"; } ?> " href="?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $folder_id; ?>">
                                            <?php
                                            if ($get_folder_id == $folder_id) { ?>
                                                <i class="fas fa-fw fa-folder-open"></i>
                                            <?php } else { ?>
                                                <i class="fas fa-fw fa-folder"></i>
                                            <?php } ?>

                                            <?php echo $folder_name; ?> <?php if ($num_documents > 0) { echo "<span class='badge badge-pill badge-dark float-right mt-1'>$num_documents</span>"; } ?>
                                        </a>
                                    </div>
                                    <div class="col-2">
                                        <div class="dropdown">
                                            <button class="btn btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#renameFolderModal<?php echo $folder_id; ?>">
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Rename
                                                </a>
                                                <?php if ($session_user_role == 3 && $num_documents == 0) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_folder=<?php echo $folder_id; ?>">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>

                            <?php
                            require "folder_rename_modal.php";


                        }
                        ?>
                    </ul>
                    <?php require_once "folder_create_modal.php";
 ?>
                </div>

                <div class="col-md-9">
                    <form autocomplete="off">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <input type="hidden" name="folder_id" value="<?php echo $get_folder_id; ?>">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group mb-3 mb-md-0">
                                    <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Documents">
                                    <div class="input-group-append">
                                        <button class="btn btn-dark"><i class="fa fa-search"></i></button>
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
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkMoveDocumentModal">
                                                <i class="fas fa-fw fa-exchange-alt mr-2"></i>Move
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <hr>
    
                    <form id="bulkActions" action="post.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                        <div class="table-responsive-sm">
                            <table class="table table-border">
                                <thead class="thead-light <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                                <tr>
                                    <td class="bg-light pr-0">
                                        <div class="form-check">
                                            <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                        </div>
                                    </td>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_name&order=<?php echo $disp; ?>">
                                            Name <?php if ($sort == 'document_name') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_created_at&order=<?php echo $disp; ?>">
                                            Created <?php if ($sort == 'document_created_at') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=document_updated_at&order=<?php echo $disp; ?>">
                                            Last Update <?php if ($sort == 'document_updated_at') { echo $order_icon; } ?>
                                        </a>
                                    </th>
                                    <th></th>
                                    <th class="text-center">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php

                                while ($row = mysqli_fetch_array($sql)) {
                                    $document_id = intval($row['document_id']);
                                    $document_name = nullable_htmlentities($row['document_name']);
                                    $document_description = nullable_htmlentities($row['document_description']);
                                    $document_content = nullable_htmlentities($row['document_content']);
                                    $document_created_by_name = nullable_htmlentities($row['user_name']);
                                    $document_created_at = date("m/d/Y",strtotime($row['document_created_at']));
                                    $document_updated_at = date("m/d/Y",strtotime($row['document_updated_at']));
                                    $document_folder_id = intval($row['document_folder_id']);

                                    // Check if shared
                                    $sql_shared = mysqli_query(
                                        $mysqli,
                                        "SELECT * FROM shared_items
                                        WHERE item_client_id = $client_id
                                        AND item_active = 1
                                        AND item_views != item_view_limit
                                        AND item_expire_at > NOW()
                                        AND item_type = 'Document'
                                        AND item_related_id = $document_id
                                        LIMIT 1"
                                    );
                                    $row = mysqli_fetch_array($sql_shared);
                                    $item_id = intval($row['item_id']);
                                    $item_active = nullable_htmlentities($row['item_active']);
                                    $item_key = nullable_htmlentities($row['item_key']);
                                    $item_type = nullable_htmlentities($row['item_type']);
                                    $item_related_id = intval($row['item_related_id']);
                                    $item_note = nullable_htmlentities($row['item_note']);
                                    $item_views = nullable_htmlentities($row['item_views']);
                                    $item_view_limit = nullable_htmlentities($row['item_view_limit']);
                                    $item_created_at = nullable_htmlentities($row['item_created_at']);
                                    $item_expire_at = nullable_htmlentities($row['item_expire_at']);
                                    $item_expire_at_human = timeAgo($row['item_expire_at']);

                                    ?>

                                    <tr>
                                        <td class="bg-light pr-0">
                                            <div class="form-check">
                                                <input class="form-check-input bulk-select" type="checkbox" name="document_ids[]" value="<?php echo $document_id ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <a href="client_document_details.php?client_id=<?php echo $client_id; ?>&document_id=<?php echo $document_id; ?>"><i class="fas fa-fw fa-file-alt"></i> <?php echo $document_name; ?></a>
                                            <div class="text-secondary mt-1"><?php echo $document_description; ?>
                                        </td>
                                        <td>
                                            <?php echo $document_created_at; ?>
                                            <div class="text-secondary mt-1"><?php echo $document_created_by_name; ?>
                                        </td>
                                        <td><?php echo $document_updated_at; ?></td>
                                        <td>
                                            <?php if($item_id) { ?>
                                            <div title="Expires <?php echo $item_expire_at_human; ?>">
                                                <i class="fas fa-fw fa-link"></i> Shared
                                            </div>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <div class="dropdown dropleft text-center">
                                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Document', $document_id"; ?>)">
                                                        <i class="fas fa-fw fa-share mr-2"></i>Share
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#renameDocumentModal<?php echo $document_id; ?>">
                                                        <i class="fas fa-fw fa-pencil-alt mr-2"></i>Rename
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#moveDocumentModal<?php echo $document_id; ?>">
                                                        <i class="fas fa-fw fa-exchange-alt mr-2"></i>Move
                                                    </a>
                                                    <?php if ($session_user_role == 3) { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_document=<?php echo $document_id; ?>">
                                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_document=<?php echo $document_id; ?>">
                                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                <?php

                                require "client_document_move_modal.php";

                                require "client_document_rename_modal.php";


                                }

                                ?>

                                </tbody>
                            </table>
                            <br>
                        </div>
                        <?php require_once "client_document_bulk_move_modal.php"; ?>
                    </form>
                    <?php require_once "pagination.php";
 ?>
                </div>
            </div>
        </div>
    </div>

<script src="js/bulk_actions.js"></script>

<?php
require_once "share_modal.php";

require_once "client_document_add_modal.php";

require_once "client_document_add_from_template_modal.php";

require_once "footer.php";
