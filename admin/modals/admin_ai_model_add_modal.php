<div class="form-group">
    <div class="modal" id="addAIModelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-robot mr-2"></i>Add AI Model</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body">

                    <div class="form-group">
                        <label>Provider <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                            </div>
                            <select class="form-control select2" name="provider" required>
                                <option value="">- Select an AI Provider -</option>
                                <?php
                                    $sql_ai_providers = mysqli_query($mysqli, "SELECT * FROM ai_providers");
                                    while ($row = mysqli_fetch_array($sql_ai_providers)) {
                                        $ai_provider_id = intval($row['ai_provider_id']);
                                        $ai_provider_name = nullable_htmlentities($row['ai_provider_name']);

                                    ?>
                                    <option value="<?php echo $ai_provider_id; ?>"><?php echo $ai_provider_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

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
                                <option>General</option>
                                <option>Tickets</option>
                                <option>Documentation</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="8" name="prompt" placeholder="Enter a model prompt:"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_ai_model" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>