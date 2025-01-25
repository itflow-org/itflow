<div class="modal" id="addDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>New Document</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" placeholder="Name" maxlength="200" required autofocus>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control tinymce<?php if($config_ai_enable) { echo "AI"; } ?>" id="textInput" name="content"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="folderSelect">Select Folder</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control select2" name="folder" id="folderSelect">
                                <option value="0">/</option>
                                <?php
                                // Recursive function to display folder options
                                function display_folder_options($parent_folder_id, $client_id, $indent = 0) {
                                    global $mysqli;

                                    $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE parent_folder = $parent_folder_id AND folder_location = 0 AND folder_client_id = $client_id ORDER BY folder_name ASC");
                                    while ($row = mysqli_fetch_array($sql_folders)) {
                                        $folder_id = intval($row['folder_id']);
                                        $folder_name = nullable_htmlentities($row['folder_name']);

                                        // Indentation for subfolders
                                        $indentation = str_repeat('&nbsp;', $indent * 4);

                                        // Check if this folder is selected
                                        $selected = '';
                                        if ((isset($_GET['folder_id']) && $_GET['folder_id'] == $folder_id) || (isset($_POST['folder']) && $_POST['folder'] == $folder_id)) {
                                            $selected = 'selected';
                                        }

                                        echo "<option value=\"$folder_id\" $selected>$indentation$folder_name</option>";

                                        // Recursively display subfolders
                                        display_folder_options($folder_id, $client_id, $indent + 1);
                                    }
                                }

                                // Start displaying folder options from the root (parent_folder = 0)
                                display_folder_options(0, $client_id);
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descriptionInput">Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" id="descriptionInput" placeholder="Short summary of the document">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
