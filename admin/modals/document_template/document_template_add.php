<div class="modal" id="addDocumentTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>Creating Document Template</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" placeholder="Template name" maxlength="200">
                    </div>

                    <div class="form-group">
                        <label>Enter a prompt for the type of IT documentation you want to generate:</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" id="aiPrompt" placeholder="e.g. 'A network troubleshooting guide for junior technicians'">
                            <div class="input-group-append">
                                <button class="btn btn-info" type="button" id="generateAIContent">
                                    <i class="fa fa-fw fa-magic mr-1"></i>Generate with AI
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TinyMCE Content -->
                    <div class="form-group">
                        <textarea class="form-control tinymce" name="content"></textarea>
                    </div>

                    <div class="form-group">
                        <input type="text" class="form-control" name="description" placeholder="Enter a short summary">
                    </div>
                
                </div>

                <div class="modal-footer">

                    <button type="submit" name="add_document_template" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>

                </div>
            </form>
        </div>
    </div>
</div>
