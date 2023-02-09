<?php

require_once("inc_all_client.php");

// Folder
if (!empty($_GET['folder_id'])) {
    $folder = intval($_GET['folder_id']);
} else {
    $folder = 0;
}

// Sort by
if (!empty($_GET['sb'])) {
    $sb = strip_tags(mysqli_real_escape_string($mysqli, $_GET['sb']));
} else {
    $sb = "document_name";
}

// Search query SQL snippet
if (!empty($q)) {
    $query_snippet = "AND (MATCH(document_content_raw) AGAINST ('$q') OR document_name LIKE '%$q%')";
} else {
    $query_snippet = ""; // empty
}

//Rebuild URL
$url_query_strings_sb = http_build_query(array_merge($_GET, array('sb' => $sb, 'o' => $o)));

// Folder ID
$get_folder_id = 0;
if (!empty($_GET['folder_id'])) {
    $get_folder_id = intval($_GET['folder_id']);
}

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM documents
    WHERE document_client_id = $client_id
    AND documents.company_id = $session_company_id
    AND document_template = 0
    AND document_folder_id = $folder
    $query_snippet
    ORDER BY $sb $o LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2">
                <i class="fa fa-fw fa-file-alt"></i> Documents
            </h3>
            <button type="button" class="btn btn-dark dropdown-toggle ml-1" data-toggle="dropdown"></button>
            <div class="dropdown-menu">
                <a class="dropdown-item text-dark" href="client_document_templates.php?client_id=<?php echo $client_id; ?>">Templates</a>
            </div>

            <div class="card-tools">

                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDocumentModal">
                        <i class="fas fa-fw fa-plus"></i> New Document
                    </button>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addFolderModal"><i class="fa fa-fw fa-folder-plus"></i> Folder</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-dark" href="#" data-toggle="modal" data-target="#addDocumentFromTemplateModal">From Template</a>
                    </div>
                </div>

            </div>

        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 border-right">
                    <h4>Folders</h4>
                    <hr>
                    <ul class="nav nav-pills flex-column bg-light">
                        <li class="nav-item">
                            <a class="nav-link <?php if ($get_folder_id == 0) { echo "active"; } ?>" href="?client_id=<?php echo $client_id; ?>&folder_id=0">/</a>
                        </li>
                        <?php
                        $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_client_id = $client_id ORDER BY folder_name ASC");
                        while ($row = mysqli_fetch_array($sql_folders)) {
                            $folder_id = $row['folder_id'];
                            $folder_name = htmlentities($row['folder_name']);

                            $row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT('document_id') AS num FROM documents WHERE document_folder_id = $folder_id"));
                            $num_documents = $row['num'];

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
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#renameFolderModal<?php echo $folder_id; ?>">Rename</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?delete_folder=<?php echo $folder_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>

                            <?php
                            require("client_document_folder_rename_modal.php");

                        }
                        ?>
                    </ul>
                    <?php require_once("client_document_folder_add_modal.php"); ?>
                </div>

                <div class="col-md-9">
                    <form autocomplete="off">
                        <input type="hidden" name="client_id" value="<?php echo intval($client_id); ?>">
                        <input type="hidden" name="folder_id" value="<?php echo $get_folder_id; ?>">
                        <div class="input-group">
                            <input type="search" class="form-control " name="q" value="<?php if (isset($q)) { echo strip_tags(htmlentities($q)); } ?>" placeholder="Search Documents">
                            <div class="input-group-append">
                                <button class="btn btn-secondary"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                    </form>
                    <hr>

                    <div class="table-responsive">
                        <table class="table table-striped table-sm table-borderless table-hover">
                            <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                            <tr>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_name&o=<?php echo $disp; ?>">Name</a>
                                </th>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_created_at&o=<?php echo $disp; ?>">Created</a>
                                </th>
                                <th>
                                    <a class="text-secondary" href="?<?php echo $url_query_strings_sb; ?>&sb=document_updated_at&o=<?php echo $disp; ?>">Updated</a>
                                </th>
                                <th class="text-center">
                                    Action
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php

                            while ($row = mysqli_fetch_array($sql)) {
                                $document_id = $row['document_id'];
                                $document_name = htmlentities($row['document_name']);
                                $document_content = $row['document_content'];
                                $document_created_at = $row['document_created_at'];
                                $document_updated_at = $row['document_updated_at'];
                                $document_folder_id = $row['document_folder_id'];

                                ?>

                                <tr>
                                    <td>
                                        <a href="client_document_details.php?client_id=<?php echo $client_id; ?>&document_id=<?php echo $document_id; ?>"><i class="fas fa-fw fa-file-alt"></i> <?php echo $document_name; ?></a>
                                    </td>
                                    <td><?php echo $document_created_at; ?></td>
                                    <td><?php echo $document_updated_at; ?></td>
                                    <td>
                                        <div class="dropdown dropleft text-center">
                                            <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDocumentModal<?php echo $document_id; ?>">Edit</a>
                                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'Document', $document_id"; ?>)">Share</a>
                                                <?php if ($session_user_role == 3) { ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="post.php?delete_document=<?php echo $document_id; ?>">Delete</a>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <?php require("client_document_view_modal.php"); ?>
                                    </td>
                                </tr>

                                <?php

                                require("client_document_edit_modal.php");
                            }

                            ?>

                            </tbody>
                        </table>
                        <br>
                    </div>
                    <?php require_once("pagination.php"); ?>
                </div>
            </div>
        </div>
    </div>


<?php
require_once("share_modal.php");
require_once("client_document_add_modal.php");
require_once("client_document_add_from_template_modal.php");
require_once("footer.php");
