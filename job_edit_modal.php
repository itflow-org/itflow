<div class="modal" id="editJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fas fa-fw fa-edit mr-2"></i>Edit Job</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="job_id" id="editJobID" value="">

                <div class="modal-body bg-white">
                    <div class="form-group">
                        <label>Scope</label>
                        <input type="text" class="form-control" name="scope" id="editJobScope" placeholder="Scope">
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" name="type" id="editJobType">
                            <option value="Project">Project</option>
                            <option value="Service Call">Service Call</option>
                            <option value="Supply">Supply</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <input type="text" class="form-control" name="status" id="editJobStatus" placeholder="Status">
                    </div>

                    <div class="form-group">
                        <label>Dropbox Link</label>
                        <input type="url" class="form-control" name="dropbox_link" id="editJobDropboxLink" placeholder="Dropbox Link">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_job" class="btn btn-primary"><i class="fas fa-check"></i> Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>

        </div>
    </div>
</div>
