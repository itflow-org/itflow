<div class="modal" id="editCustomFieldModal<?= $custom_field_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-th-list me-2"></i>Editing custom field: <strong><?= $custom_field_label ?></strong></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="custom_field_id" value="<?= $custom_field_id ?>">
                <div class="modal-body">

                    <div class="mb-3">
                        <label>Label <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="label" maxlength="255" value="<?= $custom_field_label ?>" required>
                    </div>

                    <div class="mb-3">
                        <label>Type <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-th"></i></span>
                            <select class="form-select select2" name="type" required>
                                <option value="">- Select a field type -</option>
                                <option <?php if ($custom_field_type == 'text') { echo "selected"; } ?> value="text">Text</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_custom_field" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
