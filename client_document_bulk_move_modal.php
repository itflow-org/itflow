<div class="modal" id="bulkMoveDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Moving documents</strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <div class="form-group">
                    <label>Move Document to</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_folder_id">
                            <option value="0">/</option>
                            <?php
                            $sql_folders_select = mysqli_query($mysqli, "SELECT * FROM folders WHERE folder_location = $folder_location AND folder_client_id = $client_id ORDER BY folder_name ASC");
                            while ($row = mysqli_fetch_array($sql_folders_select)) {
                                $folder_id_select = intval($row['folder_id']);
                                $folder_name_select = nullable_htmlentities($row['folder_name']);
                                ?>
                                <option value="<?php echo $folder_id_select ?>"><?php echo $folder_name_select; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="bulk_move_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Move</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
        </div>
    </div>
</div>
