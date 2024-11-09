<div class="modal" id="editDocumentClientVisibileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-fw fa-handshake mr-2"></i>
                    Edit Visibility Status for <strong><?php echo "$document_name"; ?></strong>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                    <div class="form-group">
                        <label>Visibility</label>
                        <p>Should this document be visible in the portal to client contacts with the 'Technical' role?</p>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                            </div>
                            <select class="form-control" name="document_visible">
                                <option <?php if ($document_client_visible == 1) { echo "selected"; } ?> value="1">Yes</option>
                                <option <?php if ($document_client_visible == 0) { echo "selected"; } ?> value="0">No</option>
                            </select>
                        </div>

                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="toggle_document_visibility" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>

            </form>

        </div>
    </div>
</div>
