<div class="modal" id="linkFileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-paperclip mr-2"></i>Link File to <strong><?php echo $asset_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="asset_id" value="<?php echo intval($_GET['asset_id']); ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-paperclip"></i></span>
                            </div>
                            <select class="form-control select2" name="file_id">
                                <option value="">- Select a File -</option>
                                <?php
                                // Check if there are any associated files
                                if (!empty($linked_files)) {
                                    $excluded_file_ids = implode(",", $linked_files);
                                    $exclude_condition = "AND file_id NOT IN ($excluded_file_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed vendors
                                }

                                $sql_files_select = mysqli_query($mysqli, "SELECT * FROM files 
                                    LEFT JOIN folders ON folder_id = file_folder_id
                                    WHERE file_client_id = $client_id 
                                    $exclude_condition
                                    ORDER BY folder_name ASC, file_name ASC"
                                );
                                
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
                <div class="modal-footer bg-white">
                    <button type="submit" name="link_asset_to_file" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
