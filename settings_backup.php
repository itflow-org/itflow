<?php
require_once "inc_all_settings.php";

$backupFolder = 'uploads/backups/';

// Check if the backup folder inside uploads exists, if not, create it
$uploadsBackupsFolder = 'uploads/backups/';
if (!file_exists($uploadsBackupsFolder) || !is_dir($uploadsBackupsFolder)) {
    if (!mkdir($uploadsBackupsFolder, 0777, true)) {
        die('Failed to create backups folder inside uploads');
    }
}

$backups = array_diff(scandir($backupFolder), array('..', '.'));

// Database connection
$mysqli = mysqli_connect($dbhost, $dbusername, $dbpassword, $database) or die('Database Connection Failed');
$conn = new mysqli($dbhost, $dbusername, $dbpassword, $database);

// Get the server's document root
$documentRootPath = $_SERVER['DOCUMENT_ROOT'];

// Handle backup action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['backup'])) {
    // Create a SQL backup excluding comments
    $sqlBackupFile = $documentRootPath . '/db_backup.sql';
    $sqlDumpCommand = "mysqldump --complete-insert --skip-comments --skip-triggers --host=$dbhost --user=$dbusername --password=$dbpassword $database > $sqlBackupFile";
    exec($sqlDumpCommand);

    // Check if the SQL dump was successful
    if (file_exists($sqlBackupFile)) {
        // Create a backup archive including the SQL backup file and excluding uploads/backups
        $backupFileName = date("Y-m-d_H-i-s") . ".tar.gz";
        $backupPath = $backupFolder . $backupFileName;

        // Use tar to create the archive
        $tarCommand = "tar -czf $backupPath --directory=$documentRootPath ";
        $tarCommand .= "--exclude=uploads/backups ";
        $tarCommand .= "--exclude=$sqlBackupFile ."; // Exclude SQL backup file

        // Execute the tar command
        exec($tarCommand);

        // Check if the tar command was successful
        if (file_exists($backupPath)) {
            // Refresh backup list after creating a new backup
            $backups = array_diff(scandir($backupFolder), array('..', '.'));

            // Success message
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Backup created successfully! 
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>';
        } else {
            // Error message for tar command
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Error creating backup! Please check the console for more details.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>';
        }
    } else {
        // Error message for mysqldump command
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error creating SQL backup! Please check the console for more details.
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>';
    }
}









// Handle restore action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['proceed-restore'])) {
    $selectedBackup = $_POST['proceed-restore'];

    // Use realpath to get the canonicalized absolute pathname
    $backupFile = realpath($backupFolder . $selectedBackup);

    // Check if the obtained path is within the allowed directory
    if ($backupFile !== false && strpos($backupFile, realpath($backupFolder)) === 0) {
        // Extract the tar archive to the document root
        $tarExtractCommand = "tar -xzf $backupFile --directory=$documentRootPath";
        exec($tarExtractCommand);

        // Restore the database from the SQL file
        $sqlContent = file_get_contents($backupFolder . 'database.sql');
        $result = $conn->multi_query($sqlContent);

        // Check for execution success
        if ($result === false) {
            // Display detailed error message and stop execution
            $errorMessage = $conn->error;
            $errorNumber = $conn->errno;
            echo "Error executing query: $errorMessage (Error Code: $errorNumber)";
            die();
        }

        // Display success message
        echo '<div class="alert alert-success" role="alert">Database restore successful!</div>';
    } else {
        // Log an error or take appropriate action for invalid paths
        echo 'Invalid backup path: ' . htmlspecialchars($backupFile, ENT_QUOTES, 'UTF-8');
    }
}


// Handle upload tar archive to backup list
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['filerestore-proceed'])) {
    $fileInputName = 'restore-file';

    // Check if a file was selected
    if (!empty($_FILES[$fileInputName])) {
        $uploadedFile = $_FILES[$fileInputName];

        // Check for upload errors
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="alert alert-danger" role="alert">File upload failed. Please try again.</div>';
        } else {
            // Validate file type
            $allowedExtensions = ['tar', 'gz'];
            $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);

            if (!in_array($fileExtension, $allowedExtensions)) {
                echo '<div class="alert alert-danger" role="alert">Invalid file format. Please upload a .tar.gz file.</div>';
            } else {
                // Move the uploaded file to a temporary location
                $tempFilePath = sys_get_temp_dir() . '/' . uniqid('temp_backup_') . '.' . $fileExtension;
                move_uploaded_file($uploadedFile['tmp_name'], $tempFilePath);

                // Run a virus scan using ClamAV (make sure ClamAV is installed on your server)
                $scanResult = shell_exec("clamscan --stdout --infected --no-summary " . escapeshellarg($tempFilePath));

                if (!empty($scanResult)) {
                    // Virus found, delete the temporary file and display an error message
                    unlink($tempFilePath);
                    echo '<div class="alert alert-danger" role="alert">Virus detected in the uploaded file.</div>';
                } else {
                    // Generate a unique filename in the backup folder using the original filename
                    $originalFileName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
                    $uniqueFileName = $backupFolder . $originalFileName . '.' . $fileExtension;

                    // Move the file to its final location
                    if (rename($tempFilePath, $uniqueFileName)) {
                        echo '<div class="alert alert-success" role="alert">File upload successful!</div>';
                        $backups = array_diff(scandir($backupFolder), array('..', '.')); // Refresh backup list
                    } else {
                        echo '<div class="alert alert-danger" role="alert">Failed to move the uploaded file. Please try again.</div>';
                    }
                }
            }
        }
    } else {
        echo '<div class="alert alert-warning" role="alert">No file selected for upload.</div>';
    }
}






// Handle delete action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete'])) {
    $selectedBackup = $_POST['delete'];

    // Validate the selectedBackup variable to prevent directory traversal
    if (in_array($selectedBackup, $backups)) {
        unlink($backupFolder . $selectedBackup);
    }
}

// Handle delete selected action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete-selected-confirm'])) {
    // Implement delete selected logic here
    if (isset($_POST['selectedBackups'])) {
        foreach ($_POST['selectedBackups'] as $selectedBackup) {
            $backupPath = $backupFolder . $selectedBackup;

            // Validate the file path to prevent directory traversal
            $realBackupPath = realpath($backupPath);
            $realBackupFolder = realpath($backupFolder);

            if ($realBackupPath !== false && $realBackupFolder !== false && strpos($realBackupPath, $realBackupFolder) === 0) {
                // Ensure the filename is safe before attempting to delete
                $safeBackupPath = realpath($backupPath);

                if ($safeBackupPath !== false && is_file($safeBackupPath)) {
                    unlink($safeBackupPath);
                } else {
                    // Log an error or take appropriate action for invalid paths
                    echo 'Invalid backup path: ' . htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8');
                }
            } else {
                // Log an error or take appropriate action for invalid paths
                echo 'Invalid backup path: ' . htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8');
            }
        }
    }
}


// Reverse the order of backups to display the latest on top
$backups = array_reverse(array_diff(scandir($backupFolder), array('..', '.')));

// Function to format file size in human-readable format
function formatBytes($bytes, $decimals = 2)
{
    $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$decimals}f", $bytes / (1024 ** $factor)) . ' ' . @$size[$factor];
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card card-dark mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Backup & Restore Database V1</h3>
            </div>
            <div class="card-body" style="text-align: center;">
                <form method="post">
                    <button type="submit" name="backup" class="btn btn-lg btn-primary"><i class="fas fa-fw fa-save"></i> New Backup</button>
                    <button type="button" class="btn btn-lg btn-secondary" data-toggle="modal" data-target="#schedule"><i class="fas fa-clock"></i> Schedule backup</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-dark">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-key mr-2"></i>Backup Master Encryption Key</h3>
            </div>
            <div class="card-body">
                <form action="post.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                    <div class="row d-flex justify-content-center">
                        <div class="input-group col-8">
                            <div class="input-group-prepend">
                                <input type="password" class="form-control" placeholder="Enter your account password" name="password" autocomplete="new-password" required>
                            </div>
                            <button class="btn btn-primary" type="submit" name="backup_master_key"><i class="fas fa-fw fa-key mr-2"></i>Get Master Key</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Display a table with backup list -->
<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Backup Manager</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#fileRestoreModal"><i class="fas fa-archive"></i> Add archive</button>
        </div>
    </div>
    <div class="card-body">
        <!-- Backup list table here -->
        <form method="post">
            <table class="table">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Backup Name</th>
                        <th>File Size</th>
                        <th>Actions</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup) : ?>
                        <?php
                        // Sanitize the file name for use as an HTML ID attribute
                        $modalId = preg_replace("/[^a-zA-Z0-9]/", "_", $backup);
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selectedBackups[]" value="<?= $backup ?>"></td>
                            <td><?= htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= formatBytes(filesize($backupFolder . $backup)) ?></td>
                            <td>
                                <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#restoreModal<?= $modalId ?>"><i class="fas fa-fw fa-undo"></i> Restore</button>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteModal<?= $modalId ?>"><i class="fas fa-fw fa-trash"></i> Delete</button>
								<a href="<?= $backupFolder . $backup ?>" download="<?= $backup ?>" class="btn btn-info"><i class="fas fa-fw fa-download"></i> Download</a>
                        </td>


                        </tr>

                        <!-- Restore Modal -->
                        <div class="modal" id="restoreModal<?= $modalId ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Restore Database</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to restore the database from the selected backup?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        <button type="submit" name="proceed-restore" value="<?= htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Proceed</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal" id="deleteModal<?= $modalId ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Backup</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete the backup: <?= htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') ?>?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" name="delete" value="<?= htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-danger">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Delete Selected Confirmation Modal (moved outside the loop) -->
            <div class="modal" id="deleteSelectedConfirmModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete Selected Backups</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete the selected backups?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete-selected-confirm" value="confirmed" class="btn btn-danger">Delete Selected</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteSelectedConfirmModal"><i class="fas fa-fw fa-trash"></i> Delete Selected</button>
        </form>
    </div>
</div>

                    
<!-- Restore from file modal -->
<div class="modal" id="fileRestoreModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Backup from File</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="restore-file">Select .tar file:</label>
                        <input type="file" class="form-control-file" id="restore-file" name="restore-file" accept=".tar.gz" required>
                    </div>
                    <button type="submit" name="filerestore-proceed" class="btn btn-primary">Proceed</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>





<?php
require_once "footer.php";
?>
