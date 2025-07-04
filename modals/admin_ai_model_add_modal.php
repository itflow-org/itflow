<div class="form-group">
    <div class="modal" id="addAIModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>New AI Provider</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="provider" value="<?php echo $provider_id; ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Model Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                            </div>
                            <input type="text" class="form-control" name="model" placeholder="ex gpt-4">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Use Case <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-th-list"></i></span>
                            </div>
                            <select class="form-control select2" name="use_case">
                                <option>Tickets</option>
                                <option>Documentation</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Prompt</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                            </div>
                            <textarea class="form-control" name="prompt" placeholder="Enter a model prompt:"></textarea>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_ai_model" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>