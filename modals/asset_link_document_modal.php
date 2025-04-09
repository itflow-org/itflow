<div class="modal" id="linkDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-folder mr-2"></i>Link Document to <strong><?php echo $asset_name; ?></strong></h5>
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
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control select2" name="document_id">
                                <option value="">- Select a Document -</option>
                                <?php
                                // Check if there are any associated documents
                                if ($linked_documents) {
                                    $excluded_document_ids = implode(",", $linked_documents);
                                    $exclude_condition = "AND document_id NOT IN ($excluded_document_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed documents
                                }

                                $sql_documents_select = mysqli_query($mysqli, "SELECT * FROM documents
                                    WHERE document_client_id = $client_id 
                                    AND document_archived_at IS NULL
                                    $exclude_condition
                                    ORDER BY document_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_documents_select)) {
                                    $document_id = intval($row['document_id']);
                                    $document_name = nullable_htmlentities($row['document_name']);

                                    ?>
                                    <option value="<?php echo $document_id ?>"><?php echo $document_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="link_asset_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
