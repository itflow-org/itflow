<?php

// Default Column Sortby Filter
$sort = "file_name";
$order = "ASC";

require_once "includes/inc_all_client.php";


// Folder
if (!empty($_GET['folder_id'])) {
    $folder_id = intval($_GET['folder_id']);
} else {
    $folder_id = 0;
}

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// Folder ID
$get_folder_id = 0;
if (!empty($_GET['folder_id'])) {
    $get_folder_id = intval($_GET['folder_id']);
}

// View Mode -- 0 List, 1 Thumbnail
if (!empty($_GET['view'])) {
    $view = intval($_GET['view']);
} else {
    $view = 0;
}

if ($view == 1) {
    $query_images = "AND (file_ext LIKE 'JPG' OR file_ext LIKE 'jpg' OR file_ext LIKE 'JPEG' OR file_ext LIKE 'jpeg' OR file_ext LIKE 'png' OR file_ext LIKE 'PNG' OR file_ext LIKE 'webp' OR file_ext LIKE 'WEBP')";
} else {
    $query_images = '';
}

// Set Folder Location Var used when creating folders
$folder_location = 1;

if ($get_folder_id == 0 && isset($_GET["q"])) {
    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM files
        LEFT JOIN users ON file_created_by = user_id
        WHERE file_client_id = $client_id
        AND file_archived_at IS NULL
        AND (file_name LIKE '%$q%' OR file_ext LIKE '%$q%' OR file_description LIKE '%$q%')
        $query_images
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );
}else{
    $sql = mysqli_query(
        $mysqli,
        "SELECT SQL_CALC_FOUND_ROWS * FROM files
        LEFT JOIN users ON file_created_by = user_id
        WHERE file_client_id = $client_id
        AND file_folder_id = $folder_id
        AND file_archived_at IS NULL
        AND (file_name LIKE '%$q%' OR file_ext LIKE '%$q%' OR file_description LIKE '%$q%')
        $query_images
        ORDER BY $sort $order LIMIT $record_from, $record_to"
    );
}

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$num_of_files = mysqli_num_rows($sql);

// Breadcrumbs
// Build the full folder path
$folder_id = $get_folder_id;
$folder_path = array();

while ($folder_id > 0) {
    $sql_folder = mysqli_query($mysqli, "SELECT folder_name, parent_folder FROM folders WHERE folder_id = $folder_id");
    if ($row_folder = mysqli_fetch_assoc($sql_folder)) {
        $folder_name = nullable_htmlentities($row_folder['folder_name']);
        $parent_folder = intval($row_folder['parent_folder']);

        // Prepend the folder to the beginning of the array
        array_unshift($folder_path, array('folder_id' => $folder_id, 'folder_name' => $folder_name));

        // Move up to the parent folder
        $folder_id = $parent_folder;
    } else {
        // If the folder is not found, break the loop
        break;
    }
}

?>

<div class="card card-dark">

    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-fw fa-paperclip mr-2"></i>Files</h3>

        <div class="card-tools">
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadFilesModal">
                    <i class="fas fa-fw fa-cloud-upload-alt mr-2"></i>Upload File
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                <div class="dropdown-menu">
                    <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#createFolderModal">
                        <i class="fa fa-fw fa-folder-plus mr-2"></i>New Folder
                    </a>
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
                        <a class="nav-link <?php if ($get_folder_id == 0) { echo "active"; } ?>" href="?client_id=<?php echo $client_id; ?>&folder_id=0">/</a>
                    </li>
                    <?php
                    // Function to check if a folder is an ancestor of the current folder
                    function is_ancestor_folder($folder_id, $current_folder_id, $client_id) {
                        global $mysqli;

                        // Base case: if current_folder_id is 0 or equal to folder_id
                        if ($current_folder_id == 0) {
                            return false;
                        }
                        if ($current_folder_id == $folder_id) {
                            return true;
                        }

                        // Get the parent folder of the current folder
                        $result = mysqli_query($mysqli, "SELECT parent_folder FROM folders WHERE folder_id = $current_folder_id AND folder_client_id = $client_id");
                        if ($row = mysqli_fetch_assoc($result)) {
                            $parent_folder_id = intval($row['parent_folder']);
                            // Recursive call to check the parent folder
                            return is_ancestor_folder($folder_id, $parent_folder_id, $client_id);
                        } else {
                            // Folder not found
                            return false;
                        }
                    }

                    // Recursive function to display folders and subfolders
                    function display_folders($parent_folder_id, $client_id, $indent = 0) {
                        global $mysqli, $get_folder_id, $session_user_role;

                        $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE parent_folder = $parent_folder_id AND folder_location = 1 AND folder_client_id = $client_id ORDER BY folder_name ASC");
                        while ($row = mysqli_fetch_array($sql_folders)) {
                            $folder_id = intval($row['folder_id']);
                            $folder_name = nullable_htmlentities($row['folder_name']);

                            // Get the number of files in the folder
                            $row2 = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('file_id') AS num FROM files WHERE file_folder_id = $folder_id AND file_archived_at IS NULL"));
                            $num_files = intval($row2['num']);

                            // Get the number of subfolders
                            $subfolder_result = mysqli_query($mysqli, "SELECT COUNT(*) AS count FROM folders WHERE parent_folder = $folder_id AND folder_client_id = $client_id");
                            $subfolder_count = intval(mysqli_fetch_assoc($subfolder_result)['count']);

                            echo '<li class="nav-item">';
                            echo '<div class="row">';
                            echo '<div class="col-10">';
                            echo '<a class="nav-link ';
                            if ($get_folder_id == $folder_id) { echo "active"; }
                            echo '" href="?client_id=' . $client_id . '&folder_id=' . $folder_id . '">';

                            // Indentation for subfolders
                            echo str_repeat('&nbsp;', $indent * 4);

                            // Determine if the folder is open
                            if ($get_folder_id == $folder_id || is_ancestor_folder($folder_id, $get_folder_id, $client_id)) {
                                echo '<i class="fas fa-fw fa-folder-open"></i>';
                            } else {
                                echo '<i class="fas fa-fw fa-folder"></i>';
                            }

                            echo ' ' . $folder_name;

                            if ($num_files > 0) {
                                echo "<span class='badge badge-pill badge-dark float-right mt-1'>$num_files</span>";
                            }

                            echo '</a>';
                            echo '</div>';
                            echo '<div class="col-2">';
                            ?>
                            <div class="dropdown">
                                <button class="btn btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#"
                                        data-toggle="ajax-modal"
                                        data-ajax-url="ajax/ajax_folder_rename.php"
                                        data-ajax-id="<?php echo $folder_id; ?>"
                                        >
                                        <i class="fas fa-fw fa-edit mr-2"></i>Rename
                                    </a>
                                    <?php
                                    // Only show delete option if user is admin, folder has no files, and no subfolders
                                    if ($session_user_role == 3 && $num_files == 0 && $subfolder_count == 0) { ?>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_folder=<?php echo $folder_id; ?>">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </a>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php
                            echo '</div>';
                            echo '</div>';

                            if ($subfolder_count > 0) {
                                // Display subfolders
                                echo '<ul class="nav nav-pills flex-column bg-light">';
                                display_folders($folder_id, $client_id, $indent + 1);
                                echo '</ul>';
                            }

                            echo '</li>';
                        }
                    }

                    // Start displaying folders from the root (parent_folder = 0)
                    display_folders(0, $client_id);
                    ?>
                </ul>
                <?php require_once "modals/folder_create_modal.php"; ?>
            </div>


            <div class="col-md-9">

                <form autocomplete="off">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <input type="hidden" name="view" value="<?php echo $view; ?>">
                    <input type="hidden" name="folder_id" value="<?php echo $get_folder_id; ?>">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="input-group mb-3 mb-md-0">
                                <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search for files in <?php if($get_folder_id == 0) { echo "all folders"; } else { echo "current folder"; } ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="btn-group float-right">
                                <a href="?<?php echo $url_query_strings_sort; ?>&view=0" class="btn <?php if($view == 0){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>"><i class="fas fa-list-ul"></i></a>
                                <a href="?<?php echo $url_query_strings_sort; ?>&view=1" class="btn <?php if($view == 1){ echo "btn-primary"; } else { echo "btn-outline-secondary"; } ?>"><i class="fas fa-th-large"></i></a>

                                <div class="dropdown ml-2" id="bulkActionButton" hidden>
                                    <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-fw fa-layer-group mr-2"></i>Bulk Action (<span id="selectedCount">0</span>)
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#bulkMoveFilesModal">
                                            <i class="fas fa-fw fa-exchange-alt mr-2"></i>Move
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <button class="dropdown-item text-danger text-bold"
                                                type="submit" form="bulkActions" name="bulk_delete_files">
                                            <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </form>

                <nav class="mt-3">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="?client_id=<?php echo $client_id; ?>&folder_id=0">
                                <i class="fas fa-fw fa-folder mr-2"></i>Root
                            </a>
                        </li>
                        <?php
                        // Output breadcrumb items for each folder in the path
                        foreach ($folder_path as $folder) {
                            $bread_crumb_folder_id = $folder['folder_id']; // Already Sanitized before it was pushed into array
                            $bread_crumb_folder_name = $folder['folder_name']; // Already Sanitized before it was pushed into array

                            ?>
                            <li class="breadcrumb-item">
                                <a href="?client_id=<?php echo $client_id; ?>&folder_id=<?php echo $bread_crumb_folder_id; ?>">
                                    <i class="fas fa-fw fa-folder-open mr-2"></i><?php echo $bread_crumb_folder_name; ?>
                                </a>
                            </li>
                            <?php
                        }
                        ?>
                    </ol>
                </nav>

                <hr>

                <?php
                
                if($view == 1){

                ?>

                <div class="row">

                    <?php
                    $files = [];
                    while ($row = mysqli_fetch_array($sql)) {
                        $file_id = intval($row['file_id']);
                        $file_name = nullable_htmlentities($row['file_name']);
                        $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                        $file_ext = nullable_htmlentities($row['file_ext']);
                        $file_size = intval($row['file_size']);
                        $file_size_KB = number_format($file_size / 1024);
                        $file_mime_type = nullable_htmlentities($row['file_mime_type']);
                        $file_uploaded_by = nullable_htmlentities($row['user_name']);

                        // Store file data into an array for JS
                        $files[] = [
                            'id' => $file_id,
                            'name' => $file_name,
                            'preview' => "uploads/clients/$client_id/$file_reference_name"
                        ];

                        ?>

                        <div class="col-xl-2 col-lg-2 col-md-6 col-sm-6 mb-3 text-center">
                        
                            <a href="#" onclick="openModal(<?php echo count($files)-1; ?>)"><!-- passing the index -->
                                <img class="img-thumbnail" src="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" alt="<?php echo $file_reference_name ?>">
                            </a>
                            
                            <div>

                                <div class="dropdown float-right">
                                    <button class="btn btn-link btn-sm" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" download="<?php echo $file_name; ?>">
                                            <i class="fas fa-fw fa-cloud-download-alt mr-2"></i>Download
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'File', $file_id"; ?>)">
                                            <i class="fas fa-fw fa-share mr-2"></i>Share
                                        </a>
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_file_rename.php"
                                            data-ajax-id="<?php echo $file_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-edit mr-2"></i>Rename
                                        </a>
                                        <a class="dropdown-item" href="#"
                                            data-toggle="ajax-modal"
                                            data-ajax-url="ajax/ajax_file_move.php"
                                            data-ajax-id="<?php echo $file_id; ?>"
                                            >
                                            <i class="fas fa-fw fa-exchange-alt mr-2"></i>Move
                                        </a>
                                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#linkAssetToFileModal<?php echo $file_id; ?>">
                                            <i class="fas fa-fw fa-desktop mr-2"></i>Asset
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger confirm-link" href="post.php?archive_file=<?php echo $file_id; ?>">
                                            <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                        </a>
                                        <?php if ($session_user_role == 3) { ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger text-bold" href="#" data-toggle="modal" data-target="#deleteFileModal" onclick="populateFileDeleteModal(<?php echo "$file_id , '$file_name'" ?>)">
                                                <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                            </a>
                                        <?php } ?>
                                    </div>
                                </div>
                                <small class="text-secondary"><?php echo $file_name; ?></small>
                            </div>
                        </div>

                        <?php
                        require "modals/client_file_view_modal.php";

                    }
                    ?>
                    <script>
                        // Pass PHP array to JavaScript
                        var files = <?php echo json_encode($files); ?>;
                        var currentIndex = 0; // Keep track of which file is displayed
                    </script>
                </div>

                <?php } else { ?>

                <form id="bulkActions" action="post.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                    <div class="table-responsive-sm">
                        <table class="table border">

                            <thead class="thead-light <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                            <tr>
                                <td class="bg-light pr-0">
                                    <div class="form-check">
                                        <input class="form-check-input" id="selectAllCheckbox" type="checkbox" onclick="checkAll(this)">
                                    </div>
                                </td>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=file_name&order=<?php echo $disp; ?>">
                                        Name <?php if ($sort == 'file_name') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=file_mime_type&order=<?php echo $disp; ?>">
                                        Type <?php if ($sort == 'file_mime_type') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=file_size&order=<?php echo $disp; ?>">
                                        Size <?php if ($sort == 'file_size') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sort; ?>&sort=file_created_at&order=<?php echo $disp; ?>">
                                        Uploaded <?php if ($sort == 'file_created_at') { echo $order_icon; } ?>
                                    </a>
                                </th>
                                <th></th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>

                            <tbody>

                            <?php
                            while ($row = mysqli_fetch_array($sql)) {
                                $file_id = intval($row['file_id']);
                                $file_name = nullable_htmlentities($row['file_name']);
                                $file_description = nullable_htmlentities($row['file_description']);
                                $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                                $file_ext = nullable_htmlentities($row['file_ext']);
                                if ($file_ext == 'pdf') {
                                    $file_icon = "file-pdf";
                                } elseif ($file_ext == 'gz' || $file_ext == 'tar' || $file_ext == 'zip' || $file_ext == '7z' || $file_ext == 'rar') {
                                    $file_icon = "file-archive";
                                } elseif ($file_ext == 'txt' || $file_ext == 'md') {
                                    $file_icon = "file-alt";
                                } elseif ($file_ext == 'msg') {
                                    $file_icon = "envelope";
                                } elseif ($file_ext == 'doc' || $file_ext == 'docx' || $file_ext == 'odt') {
                                    $file_icon = "file-word";
                                } elseif ($file_ext == 'xls' || $file_ext == 'xlsx' || $file_ext == 'ods') {
                                    $file_icon = "file-excel";
                                } elseif ($file_ext == 'pptx' || $file_ext == 'odp') {
                                    $file_icon = "file-powerpoint";
                                } elseif ($file_ext == 'mp3' || $file_ext == 'wav' || $file_ext == 'ogg') {
                                    $file_icon = "file-audio";
                                } elseif ($file_ext == 'mov' || $file_ext == 'mp4' || $file_ext == 'av1') {
                                    $file_icon = "file-video";
                                } elseif ($file_ext == 'jpg' || $file_ext == 'jpeg' || $file_ext == 'png' || $file_ext == 'gif' || $file_ext == 'webp' || $file_ext == 'bmp' || $file_ext == 'tif') {
                                    $file_icon = "file-image";
                                } else {
                                    $file_icon = "file";
                                }
                                $file_size = intval($row['file_size']);
                                $file_size_KB = number_format($file_size / 1024);
                                $file_mime_type = nullable_htmlentities($row['file_mime_type']);
                                $file_size = intval($row['file_size']);
                                $file_uploaded_by = nullable_htmlentities($row['user_name']);
                                $file_created_at = nullable_htmlentities($row['file_created_at']);
                                $file_folder_id = intval($row['file_folder_id']);
                                
                                // Check if shared
                                $sql_shared = mysqli_query(
                                    $mysqli,
                                    "SELECT * FROM shared_items
                                    WHERE item_client_id = $client_id
                                    AND item_active = 1
                                    AND item_views != item_view_limit
                                    AND item_expire_at > NOW()
                                    AND item_type = 'File'
                                    AND item_related_id = $file_id
                                    LIMIT 1"
                                );
                                $file_shared = (mysqli_num_rows($sql_shared) > 0) ? true : false;
                                if ($file_shared) {
                                    $row = mysqli_fetch_array($sql_shared);
                                    $item_id = intval($row['item_id']);
                                    $item_active = nullable_htmlentities($row['item_active']);
                                    $item_key = nullable_htmlentities($row['item_key']);
                                    $item_type = nullable_htmlentities($row['item_type']);
                                    $item_related_id = intval($row['item_related_id']);
                                    $item_note = nullable_htmlentities($row['item_note']);
                                    $item_recipient = nullable_htmlentities($row['item_recipient']);
                                    $item_views = nullable_htmlentities($row['item_views']);
                                    $item_view_limit = nullable_htmlentities($row['item_view_limit']);
                                    $item_created_at = nullable_htmlentities($row['item_created_at']);
                                    $item_expire_at = nullable_htmlentities($row['item_expire_at']);
                                    $item_expire_at_human = timeAgo($row['item_expire_at']);
                                }

                                ?>

                                <tr>
                                    <td class="bg-light pr-0">
                                        <div class="form-check">
                                            <input class="form-check-input bulk-select" type="checkbox" name="file_ids[]" value="<?php echo $file_id ?>">
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" target="_blank">
                                            <div class="media">
                                                <i class="fa fa-fw fa-2x fa-<?php echo $file_icon; ?> text-dark mr-3"></i>
                                                <div class="media-body">
                                                    <p>
                                                        <?php echo basename($file_name); ?>
                                                        <br>
                                                        <small class="text-secondary"><?php echo $file_description; ?></small>
                                                    </p>
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td><?php echo $file_mime_type; ?></td>
                                    <td><?php echo $file_size_KB; ?> KB</td>
                                    <td>
                                        <?php echo $file_created_at; ?>
                                        <div class="text-secondary mt-1"><?php echo $file_uploaded_by; ?></div>        
                                    </td>
                                    <td>
                                        <?php if ($file_shared) { ?>
                                            <div class="media" title="Expires <?php echo $item_expire_at_human; ?>">
                                                <i class="fas fa-link mr-2 mt-1"></i>
                                                <div class="media-body">Shared
                                                    <br>
                                                    <small class="text-secondary"><?php echo $item_recipient; ?></small>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" download="<?php echo $file_name; ?>">
                                                    <i class="fas fa-fw fa-cloud-download-alt mr-2"></i>Download
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'File', $file_id"; ?>)">
                                                    <i class="fas fa-fw fa-share mr-2"></i>Share
                                                </a>
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_file_rename.php"
                                                    data-ajax-id="<?php echo $file_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-edit mr-2"></i>Rename
                                                </a>
                                                <a class="dropdown-item" href="#"
                                                    data-toggle="ajax-modal"
                                                    data-ajax-url="ajax/ajax_file_move.php"
                                                    data-ajax-id="<?php echo $file_id; ?>"
                                                    >
                                                    <i class="fas fa-fw fa-exchange-alt mr-2"></i>Move
                                                </a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#linkAssetToFileModal<?php echo $file_id; ?>">
                                                    <i class="fas fa-fw fa-desktop mr-2"></i>Asset
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-danger confirm-link" href="post.php?archive_file=<?php echo $file_id; ?>">
                                                    <i class="fas fa-fw fa-archive mr-2"></i>Archive
                                                </a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger text-bold" href="#" data-toggle="modal" data-target="#deleteFileModal" onclick="populateFileDeleteModal(<?php echo "$file_id , '$file_name'" ?>)">
                                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                require "modals/client_file_link_asset_modal.php";

                            }
                            ?>
                            </tbody>

                        </table>
                    </div>
                    <?php require_once "modals/client_file_bulk_move_modal.php"; ?>
                </form>

                <?php } ?>

                <?php require_once "includes/filter_footer.php";
 ?>

            </div>
        </div>
    </div>
</div>

<script>
function openModal(index) {
    currentIndex = index;
    updateModalContent();
    $('#viewFileModal').modal('show');
}

function updateModalContent() {
    document.getElementById('modalTitle').innerText = files[currentIndex].name;
    document.getElementById('modalImage').src = files[currentIndex].preview;
}

function nextFile() {
    currentIndex = (currentIndex + 1) % files.length; // loop around
    updateModalContent();
}

function prevFile() {
    currentIndex = (currentIndex - 1 + files.length) % files.length; // loop around
    updateModalContent();
}
</script>

<script src="js/bulk_actions.js"></script>

<?php
require_once "modals/client_file_upload_modal.php";
require_once "modals/share_modal.php";
require_once "modals/client_file_delete_modal.php";
require_once "includes/footer.php";
