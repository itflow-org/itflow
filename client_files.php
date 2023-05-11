<?php

require_once("inc_all_client.php");

$sql_files_images = mysqli_query($mysqli, "SELECT * FROM files WHERE file_client_id = $client_id AND (file_ext LIKE 'JPG' OR file_ext LIKE 'jpg' OR file_ext LIKE 'JPEG' OR file_ext LIKE 'jpeg' OR file_ext LIKE 'png' OR file_ext LIKE 'PNG') ORDER BY file_name ASC");

$sql_files_other = mysqli_query($mysqli, "SELECT * FROM files WHERE file_client_id = $client_id AND file_ext NOT LIKE 'JPG' AND file_ext NOT LIKE 'jpg' AND file_ext NOT LIKE 'jpeg' AND file_ext NOT LIKE 'JPEG' AND file_ext NOT LIKE 'webp' AND file_ext NOT LIKE 'WEBP' AND file_ext NOT LIKE 'png' AND file_ext NOT LIKE 'PNG' ORDER BY file_name ASC");

$num_of_files = mysqli_num_rows($sql_files_images) + mysqli_num_rows($sql_files_other);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fa fa-fw fa-paperclip mr-2"></i>Files</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFileModal"><i class="fas fa-fw fa-cloud-upload-alt mr-2"></i>Upload File</button>
            </div>
        </div>
        <div class="card-body">

            <?php
            if ($num_of_files == 0) {
                echo "<div style='text-align: center;'><h3 class='text-secondary'>No Records Here</h3></div>";
            } ?>

            <div class="row">

                <?php

                while ($row = mysqli_fetch_array($sql_files_images)) {
                    $file_id = intval($row['file_id']);
                    $file_name = nullable_htmlentities($row['file_name']);
                    $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                    $file_ext = nullable_htmlentities($row['file_ext']);

                    ?>

                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                        <div class="card">
                            <a href="#" data-toggle="modal" data-target="#viewFileModal<?php echo $file_id; ?>">
                                <img class="img-fluid" src="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" alt="<?php echo $file_reference_name ?>">
                            </a>
                            <div class="card-footer bg-dark text-white p-1" style="text-align: center;">
                                <a href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" download="<?php echo $file_name; ?>" class="text-white float-left ml-1"><i class="fa fa-cloud-download-alt"></i></a>
                                <a href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'File', $file_id"; ?>)" class="text-white float-left ml-1"><i class="fa fa-share"></i></a>

                                <small><?php echo $file_name; ?></small>

                                <a href="#" data-toggle="modal" data-target="#deleteFileModal" onclick="populateFileDeleteModal(<?php echo "$file_id , '$file_name'" ?>)" class="text-white float-right mr-1"><i class="fa fa-times"></i></a>
                            </div>
                        </div>
                    </div>

                    <?php
                    require("client_file_view_modal.php");
                }
                ?>
            </div>

            <div class="row">

                <table class="table">

                    <?php
                    while ($row = mysqli_fetch_array($sql_files_other)) {
                        $file_id = intval($row['file_id']);
                        $file_name = nullable_htmlentities($row['file_name']);
                        $file_reference_name = nullable_htmlentities($row['file_reference_name']);
                        $file_ext = nullable_htmlentities($row['file_ext']);
                        if ($file_ext == 'pdf') {
                            $file_icon = "file-pdf";
                        } elseif ($file_ext == 'gz' || $file_ext == 'tar' || $file_ext == 'zip' || $file_ext == '7z' || $file_ext == 'rar') {
                            $file_icon = "file-archive";
                        } elseif ($file_ext == 'txt') {
                            $file_icon = "file-alt";
                        } elseif ($file_ext == 'doc' || $file_ext == 'docx') {
                            $file_icon = "file-word";
                        } elseif ($file_ext == 'xls' || $file_ext == 'xlsx' || $file_ext == 'ods') {
                            $file_icon = "file-excel";
                        } elseif ($file_ext == 'mp3' || $file_ext == 'wav' || $file_ext == 'ogg') {
                            $file_icon = "file-audio";
                        } else {
                            $file_icon = "file";
                        }
                        ?>

                        <tr>
                            <td><a href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" target="_blank" class="text-secondary"><i class="fa fa-fw fa-2x fa-<?php echo $file_icon; ?> mr-3"></i> <?php echo basename($file_name); ?></a></td>
                            <td>
                                <a href="<?php echo "uploads/clients/$client_id/$file_reference_name"; ?>" download="<?php echo $file_name; ?>" class="text-secondary float-left ml-1"><i class="fa fa-cloud-download-alt"></i></a>
                                <a href="#" data-toggle="modal" data-target="#shareModal" onclick="populateShareModal(<?php echo "$client_id, 'File', $file_id"; ?>)" class="text-secondary float-left ml-1"><i class="fa fa-share"></i></a>
                                <a href="#" data-toggle="modal" data-target="#deleteFileModal" onclick="populateFileDeleteModal(<?php echo "$file_id , '$file_name'" ?>)" class="text-secondary float-right mr-1"><i class="fa fa-times"></i></a>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>

            </div>
        </div>
    </div>

<?php
require_once("client_file_add_modal.php");
require_once("share_modal.php");
require_once("client_file_delete_modal.php");
require_once("footer.php");

