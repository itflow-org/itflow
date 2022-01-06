<div class="modal" id="manageTagsModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-tags"></i> Manage Tags</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <div class="form-group">
                        <input type="text" class="form-control" name="tag_name" placeholder="Tag Name" autofocus>
                    </div>
                    <button type="submit" name="add_document_tag" class="btn btn-primary">Add Tag</button>
                </form>
                <?php
                // Only show the edit/update tags if we have tags to work with
                if ($document_tags) { ?>
                <hr>
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <div class="form-group">
                        <select class="form-select" name="tag_id">
                            <?php
                            foreach($document_tags as $document_tag) {
                                echo "<option value='$document_tag[tag_id]'>"; echo htmlentities($document_tag['tag_name']); echo "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="delete_document_tag" class="btn btn-danger">Delete Tag</button>
                </form>
                <hr>
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <div class="form-group">
                        <select class="form-select" name="tag_id">
                            <?php
                            foreach($document_tags as $document_tag) {
                                echo "<option value='$document_tag[tag_id]'>"; echo htmlentities($document_tag['tag_name']); echo "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" name="tag_new_name" placeholder="Tag Name" autofocus>
                    </div>
                    <button type="submit" name="rename_document_tag" class="btn btn-primary">Rename Tag</button>
                </form>
            </div>
                <?php }
                ?>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
        </div>
    </div>
</div>