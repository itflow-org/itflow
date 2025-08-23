<?php

require_once '../../../includes/modal_header_new.php';

$document_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM documents WHERE document_id = $document_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$client_id = intval($row['document_client_id']);
$document_folder_id = nullable_htmlentities($row['document_folder_id']);
$document_name = nullable_htmlentities($row['document_name']);


// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Moving document: <strong><?php echo $document_name; ?></strong></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
    <div class="modal-body">

        <div class="form-group">
            <label>Move Document to</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                </div>
                <select class="form-control select2" name="folder">
                    <option value="0">/</option>
                    <?php
                    // Fetch all folders for the client
                    $sql_all_folders = mysqli_query($mysqli, "SELECT folder_id, folder_name, parent_folder FROM folders WHERE folder_location = 0 AND folder_client_id = $client_id ORDER BY folder_name ASC");
                    $folders = array();

                    // Build an associative array of folders indexed by folder_id
                    while ($row = mysqli_fetch_assoc($sql_all_folders)) {
                        $folders[$row['folder_id']] = array(
                            'folder_id' => intval($row['folder_id']),
                            'folder_name' => nullable_htmlentities($row['folder_name']),
                            'parent_folder' => intval($row['parent_folder']),
                            'children' => array()
                        );
                    }

                    // Build the folder hierarchy
                    foreach ($folders as $id => &$folder) {
                        if ($folder['parent_folder'] != 0 && isset($folders[$folder['parent_folder']])) {
                            $folders[$folder['parent_folder']]['children'][] = &$folder;
                        }
                    }
                    unset($folder); // Break the reference

                    // Prepare a list of root folders
                    $root_folders = array();
                    foreach ($folders as $id => $folder) {
                        if ($folder['parent_folder'] == 0) {
                            $root_folders[] = $folder;
                        }
                    }

                    // Display the folder options iteratively
                    $stack = array();
                    foreach (array_reverse($root_folders) as $folder) {
                        $stack[] = array('folder' => $folder, 'level' => 0);
                    }

                    while (!empty($stack)) {
                        $node = array_pop($stack);
                        $folder = $node['folder'];
                        $level = $node['level'];

                        // Indentation for subfolders
                        $indentation = str_repeat('&nbsp;', $level * 4);

                        // Check if this folder is selected
                        $selected = '';
                        if ($folder['folder_id'] == $document_folder_id) {
                            $selected = 'selected';
                        }

                        echo "<option value=\"{$folder['folder_id']}\" $selected>$indentation{$folder['folder_name']}</option>";

                        // Add children to the stack
                        if (!empty($folder['children'])) {
                            foreach (array_reverse($folder['children']) as $child_folder) {
                                $stack[] = array('folder' => $child_folder, 'level' => $level + 1);
                            }
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="move_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Move</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer_new.php';
