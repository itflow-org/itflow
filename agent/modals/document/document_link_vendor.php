<div class="modal" id="linkVendorToDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-building mr-2"></i>Link Vendor to <strong><?php echo $document_name; ?></strong></h5>
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
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            </div>
                            <select class="form-control select2" name="vendor_id">
                                <option value="">- Select a Vendor -</option>
                                <?php
                                // Check if there are any associated vendors
                                if (!empty($associated_vendors)) {
                                    $excluded_vendor_ids = implode(",", $associated_vendors);
                                    $exclude_condition = "AND vendor_id NOT IN ($excluded_vendor_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed vendors
                                }

                                $sql_vendors_select = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors 
                                    WHERE vendor_client_id = $client_id 
                                    AND vendor_archived_at IS NULL
                                    $exclude_condition
                                    ORDER BY vendor_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_vendors_select)) {
                                    $vendor_id = intval($row['vendor_id']);
                                    $vendor_name = nullable_htmlentities($row['vendor_name']);

                                    ?>
                                    <option value="<?php echo $vendor_id ?>"><?php echo $vendor_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="link_vendor_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
