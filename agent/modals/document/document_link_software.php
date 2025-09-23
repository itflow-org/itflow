<div class="modal" id="linkSoftwareToDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Link Software to <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-box-open"></i></span>
                            </div>
                            <select class="form-control select2" name="software_id">
                                <option value="">- Select a License -</option>
                                <?php
                                // Check if there are any associated vendors
                                if (!empty($linked_software)) {
                                    $excluded_software_ids = implode(",", $linked_software);
                                    $exclude_condition = "AND software_id NOT IN ($excluded_software_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed vendors
                                }

                                $sql_software_select = mysqli_query($mysqli, "SELECT software_id, software_name FROM software
                                    WHERE software_client_id = $client_id 
                                    AND software_archived_at IS NULL
                                    $exclude_condition
                                    ORDER BY software_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_software_select)) {
                                    $software_id = intval($row['software_id']);
                                    $software_name = nullable_htmlentities($row['software_name']);

                                    ?>
                                    <option value="<?php echo $software_id ?>"><?php echo $software_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="link_software_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
