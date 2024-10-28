<div class="modal" id="moveDocumentModal<?php echo $document_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Moving document: <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Move Document to</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control select2" name="folder">
                                <option value="0">/</option>
                                <?php
                                // Recursive function to display folder options
                                function display_folder_move_options($parent_folder_id, $client_id, $indent = 0) {
                                    global $mysqli, $document_folder_id;

                                    $sql_folders_select = mysqli_query($mysqli, "SELECT * FROM folders WHERE parent_folder = $parent_folder_id AND folder_client_id = $client_id ORDER BY folder_name ASC");
                                    while ($row = mysqli_fetch_array($sql_folders_select)) {
                                        $folder_id_select = intval($row['folder_id']);
                                        $folder_name_select = nullable_htmlentities($row['folder_name']);

                                        // Indentation for subfolders
                                        $indentation = str_repeat('&nbsp;', $indent * 4);

                                        // Check if this folder is selected
                                        $selected = '';
                                        if ($folder_id_select == $document_folder_id) {
                                            $selected = 'selected';
                                        }

                                        echo "<option value=\"$folder_id_select\" $selected>$indentation$folder_name_select</option>";

                                        // Recursively display subfolders
                                        display_folder_move_options($folder_id_select, $client_id, $indent + 1);
                                    }
                                }

                                // Start displaying folder options from the root (parent_folder = 0)
                                display_folder_move_options(0, $client_id);
                                ?>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="move_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Move</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
