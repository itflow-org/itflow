<div class="modal" id="associateFileToDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-paperclip mr-2"></i>Associate File to <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                            </div>
                            <select class="form-control select2" name="file_id">
                                <option value="">- Select a File -</option>
                                <?php
                                $sql_files_select = mysqli_query($mysqli, "SELECT * FROM files 
                                    LEFT JOIN folders ON folder_id = file_folder_id
                                    WHERE file_client_id = $client_id ORDER BY folder_name ASC, file_name ASC");
                                while ($row = mysqli_fetch_array($sql_files_select)) {
                                    $file_id = intval($row['file_id']);
                                    $file_name = nullable_htmlentities($row['file_name']);
                                    $folder_name = nullable_htmlentities($row['folder_name']);

                                    ?>
                                    <option value="<?php echo $file_id ?>"><?php echo "$folder_name/$file_name"; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="associate_file_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Associate</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
