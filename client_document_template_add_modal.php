<div class="modal" id="addDocumentTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>New Document Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" placeholder="Template name" required autofocus>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control summernote" name="content"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-white">

                    <button type="submit" name="add_document_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>

                </div>
            </form>
        </div>
    </div>
</div>
