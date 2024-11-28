<div class="modal" id="addJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-briefcase mr-2"></i>New Job</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">

                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Scope</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                            </div>
                            <input type="text" class="form-control" name="scope" placeholder="Enter job scope" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client_id" required>
                                <option value="">- Select Client -</option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']);
                                    ?>
                                    <option value="<?php echo $client_id; ?>"><?php echo $client_name; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Type <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                            </div>
                            <select class="form-control" name="type" required>
                                <option value="">- Select Type -</option>
                                <option value="Project">Project</option>
                                <option value="Service Call">Service Call</option>
                                <option value="Supply">Supply</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-info-circle"></i></span>
                            </div>
                            <input type="text" class="form-control" name="status" placeholder="Job status">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Dropbox Link</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                            </div>
                            <input type="url" class="form-control" name="dropbox_link" placeholder="Dropbox link">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_job" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
