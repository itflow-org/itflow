<div class="modal" id="editDocumentTemplateModal<?php echo $document_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt"></i> Editing template: <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" value="<?php echo $document_name; ?>" placeholder="Name" required>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control summernote" name="content"><?php echo $document_content; ?></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_document_template" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
