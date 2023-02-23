<div class="modal" id="addFileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt mr-2"></i>Upload File</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>File name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-file"></i></span>
                            </div>
                            <input type="text" class="form-control" name="new_name" placeholder="leave blank to use existing name">
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="file" class="form-control-file" name="file" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .doc, .docx, .csv, .xls, .xlsx, .zip, .tar, .gz">
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_file" class="btn btn-primary text-bold"><i class="fa fa-upload mr-2"></i>Upload</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
