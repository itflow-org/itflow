<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$contact_id = intval($_GET['contact_id'] ?? 0);
$asset_id = intval($_GET['asset_id'] ?? 0);
intval($_GET['folder_id'] ?? 0);

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-cloud-upload-alt me-2"></i>Upload File(s)</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="contact_id" value="<?= $contact_id ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <label>Description</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" maxlength="250" placeholder="Description of the file(s)">
            </div>
        </div>

        <div class="mb-3 mb-4">
            <label>Folder</label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-folder"></i></span>
                <select class="form-control select2" name="folder_id">
                    <option value="0">/</option>
                    <?php
                    // Start displaying folder options from the root (parent_folder = 0)
                    displayFolderOptions(0, $client_id, 1);
                    ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <input type="file" class="form-control" name="file[]" multiple id="fileInput" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn, .cfg, .ps1, .vsdx, .drawio, .pfx, .unf, .key, .stk, .bat, .swb">
        </div>
        <small class="text-secondary">Up to 20 files can be uploaded at once by holding down CTRL and selecting files</small>

    </div>
    <div class="modal-footer">
        <button type="submit" name="upload_files" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i>Upload</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

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

<?php
require_once '../../../includes/modal_footer.php';
