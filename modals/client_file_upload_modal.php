<div class="modal" id="uploadFilesModal" tabindex="-1">
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
                        <label>Description</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                            </div>
                            <input type="text" class="form-control" name="description" maxlength="250" placeholder="Description of the file">
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
                                // Recursive function to display folder options
                                function display_folder_options($parent_folder_id, $client_id, $indent = 0) {
                                    global $mysqli;

                                    $sql_folders = mysqli_query($mysqli, "SELECT * FROM folders WHERE parent_folder = $parent_folder_id AND folder_location = 1 AND folder_client_id = $client_id ORDER BY folder_name ASC");
                                    while ($row = mysqli_fetch_array($sql_folders)) {
                                        $folder_id = intval($row['folder_id']);
                                        $folder_name = nullable_htmlentities($row['folder_name']);

                                        // Indentation for subfolders
                                        $indentation = str_repeat('&nbsp;', $indent * 4);

                                        // Check if this folder is selected
                                        $selected = '';
                                        if ((isset($_GET['folder_id']) && $_GET['folder_id'] == $folder_id) || (isset($_POST['folder']) && $_POST['folder'] == $folder_id)) {
                                            $selected = 'selected';
                                        }

                                        echo "<option value=\"$folder_id\" $selected>$indentation$folder_name</option>";

                                        // Recursively display subfolders
                                        display_folder_options($folder_id, $client_id, $indent + 1);
                                    }
                                }

                                // Start displaying folder options from the root (parent_folder = 0)
                                display_folder_options(0, $client_id);
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <input type="file" class="form-control-file" name="file[]" multiple id="fileInput" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn, .cfg, .ps1, .vsdx, .drawio, .pfx, .unf, .key">
                    </div>
                    <small class="text-secondary">Up to 20 files can be uploaded at once by holding down CTRL and selecting files</small>

                </div>
                <div class="modal-footer bg-white">
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
