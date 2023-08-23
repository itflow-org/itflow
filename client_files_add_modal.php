<div class="modal" id="addFilesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt mr-2"></i>Upload Files</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <input type="file" class="form-control-file" name="file[]" multiple id="fileInput" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1">
                    </div>
                    <small class="text-secondary">Multiple files can be uploaded by holding down CTRL and selecting files</small>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_files" class="btn btn-primary text-bold"><i class="fa fa-upload mr-2"></i>Upload</button>
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
