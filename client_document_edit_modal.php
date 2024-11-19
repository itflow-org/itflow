<div class="modal" id="editDocumentModal<?php echo $document_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Editing document: <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php if($document_parent == 0){ echo $document_id; } else { echo $document_parent; } ?>">
                <input type="hidden" name="document_parent" value="<?php echo $document_parent; ?>">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="created_by" value="<?php echo $document_created_by_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" value="<?php echo $document_name; ?>" placeholder="Name" required>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control tinymce<?php if($config_ai_enable) { echo "AI"; } ?>" id="textInput" name="content"><?php echo $document_content; ?></textarea>
                    </div>
        
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control" name="folder">
                                <option value="0">/</option>
                                <?php
                                $sql_folders_select = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_location = $folder_location AND folder_client_id = $client_id ORDER BY folder_name ASC");
                                while ($row = mysqli_fetch_array($sql_folders_select)) {
                                    $folder_id_select = intval($row['folder_id']);
                                    $folder_name_select = nullable_htmlentities($row['folder_name']);
                                    ?>
                                    <option <?php if ($folder_id_select == $document_folder_id) echo "selected"; ?> value="<?php echo $folder_id_select ?>"><?php echo $folder_name_select; ?></option>
                                    <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="text" class="form-control" name="description" value="<?php echo $document_description; ?>" placeholder="Short summary of changes">
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
