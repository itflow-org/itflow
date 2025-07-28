<div class="modal" id="addDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-alt mr-2"></i>New Document</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php if (isset($_GET['contact_id'])) { ?>
                <input type="hidden" name="contact" value="<?php echo intval($_GET['contact_id']); ?>">
                <?php } ?>
                <?php if (isset($_GET['asset_id'])) { ?>
                <input type="hidden" name="asset" value="<?php echo intval($_GET['asset_id']); ?>">
                <?php } ?>
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="text" class="form-control" name="name" placeholder="Name" maxlength="200" required autofocus>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control tinymce<?php if($config_ai_enable) { echo "AI"; } ?>" name="content"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Select Folder</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control select2" name="folder">
                                <option value="0">/</option>
                                <?php
                                // Start displaying folder options from the root (parent_folder = 0)
                                display_folder_options(0, $client_id);
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" placeholder="Short summary of the document">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
