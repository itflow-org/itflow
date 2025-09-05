<div class="modal" id="uploadFilesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt mr-2"></i>Upload File(s)</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php if (isset($_GET['contact_id'])) { ?>
                <input type="hidden" name="contact" value="<?php echo intval($_GET['contact_id']); ?>">
                <?php } ?>
                <?php if (isset($_GET['asset_id'])) { ?>
                <input type="hidden" name="asset" value="<?php echo intval($_GET['asset_id']); ?>">
                <?php } ?>
                <div class="modal-body">

                    <div class="form-group">
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" maxlength="250" placeholder="Description of the file(s)">
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label>Folder</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                            </div>
                            <select class="form-control select2" name="folder_id">
                                <option value="0">/</option>
                                <?php
                                // Start displaying folder options from the root (parent_folder = 0)
                                display_folder_options(0, $client_id, 1);
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="file" class="form-control-file" name="file[]" multiple id="fileInput" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn, .cfg, .ps1, .vsdx, .drawio, .pfx, .unf, .key, .stk, .bat">
                    </div>
                    <small class="text-secondary">Up to 20 files can be uploaded at once by holding down CTRL and selecting files</small>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="upload_files" class="btn btn-primary text-bold"><i class="fa fa-upload mr-2"></i>Upload</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const maxFiles = 20; // Set the maximum number of allowed files
    const maxTotalSize = 500 * 1024 * 1024; // 500MB in bytes

    const fileInput = document.getElementById('fileInput');
    const uploadForm = document.getElementById('uploadForm');

    fileInput.addEventListener('change', function () {
        const totalSize = calculateTotalFileSize(fileInput.files);
        if (fileInput.files.length > maxFiles || totalSize > maxTotalSize) {
            alert(`You can only upload up to ${maxFiles} files at a time and the total file size must not exceed 500MB.`);
            resetFileInput();
        }
    });

    uploadForm.addEventListener('submit', function (event) {
        const totalSize = calculateTotalFileSize(fileInput.files);
        if (fileInput.files.length > maxFiles || totalSize > maxTotalSize) {
            event.preventDefault();
            alert(`You can only upload up to ${maxFiles} files at a time and the total file size must not exceed 500MB.`);
            resetFileInput();
        }
    });

    function calculateTotalFileSize(files) {
        let totalSize = 0;
        for (const file of files) {
            totalSize += file.size;
        }
        return totalSize;
    }

    function resetFileInput() {
        fileInput.value = ''; // Clear the selected files
    }
</script>
