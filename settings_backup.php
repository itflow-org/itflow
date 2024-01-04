<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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



// Handle backup action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['backup'])) {
    // Create a backup
    $backupFileName = date("d-m-Y_H-i-s") . ".sql";
    $backupPath = $backupFolder . $backupFileName;

    // Run mysqldump command to include table content
    $escapedBackupPath = escapeshellarg($backupPath);
    $command = "mysqldump --complete-insert --skip-comments --host=$dbhost --user=$dbusername --password=$dbpassword $database > $escapedBackupPath";

    // Execute mysqldump command
    exec($command);


    // Remove comments from the dumped SQL file using sed
    $sedCommand = "sed -i -E '/\\/\\*[^;]*;/d' $escapedBackupPath";
    exec($sedCommand);

    // Refresh backup list after creating a new backup
    $backups = array_diff(scandir($backupFolder), array('..', '.'));
}





// Handle restore from file action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['filerestore-proceed'])) {
    // Define the target folder for uploaded files
    $targetFolder = $backupFolder;

    // Define allowed file types
    $allowedFileTypes = array('sql');

    // Get the uploaded file details
    $fileName = basename($_FILES['fileToRestore']['name']);
    $targetFilePath = $targetFolder . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    // Check if the file type is allowed
    if (in_array($fileType, $allowedFileTypes)) {
        // Upload the file to the server
        if (move_uploaded_file($_FILES['fileToRestore']['tmp_name'], $targetFilePath)) {
            // Add the uploaded file information to the list
            $backups[] = $fileName;

            // Display success message
            echo '<div class="alert alert-success" role="alert">File uploaded successfully!</div>';
        } else {
            echo '<div class="alert alert-danger" role="alert">Failed to upload file.</div>';
        }
    } else {
        echo '<div class="alert alert-danger" role="alert">Invalid file type. Only .sql files are allowed.</div>';
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
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete-selected'])) {
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

// Handle restore from file action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['filerestore-proceed'])) {
    // Define the target folder for uploaded files
    $targetFolder = $backupFolder;

    // Define allowed file types
    $allowedFileTypes = array('sql');

    // Get the uploaded file details
    $fileName = basename($_FILES['fileToRestore']['name']);
    $targetFilePath = $targetFolder . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

    // Check if the file type is allowed
    if (in_array($fileType, $allowedFileTypes)) {
        // Upload the file to the server
        if (move_uploaded_file($_FILES['fileToRestore']['tmp_name'], $targetFilePath)) {
            // Execute the restore process
            $sqlContent = file_get_contents($targetFilePath);
            $result = $conn->multi_query($sqlContent);

            // Check for execution success
            if ($result === false) {
                // Display detailed error message and stop execution
                $errorMessage = $conn->error;
                $errorNumber = $conn->errno;
                echo "Error executing query: $errorMessage (Error Code: $errorNumber)";
                die();
            }

            // Display success message and add the file to the restore list
            $restoredBackup = htmlspecialchars($backup, ENT_QUOTES, 'UTF-8');
            echo '<div class="alert alert-success" role="alert">Backup file added to the restore list: ' . $restoredBackup . '</div>';
            $backups[] = $restoredBackup;


            // Delete the uploaded file after restore
            unlink($targetFilePath);
        } else {
            echo '<div class="alert alert-danger" role="alert">Failed to upload file.</div>';
        }
    } else {
        echo '<div class="alert alert-danger" role="alert">Invalid file type. Only .sql files are allowed.</div>';
    }

    // Reverse the order of backups to display the latest on top
    $backups = array_reverse(array_diff(scandir($backupFolder), array('..', '.')));
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
                <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Backup Database Maria 23</h3>
            </div>
            <div class="card-body" style="text-align: center;">
                <form method="post">
                    <button type="submit" name="backup" class="btn btn-lg btn-primary"><i class="fas fa-fw fa-save"></i> New Backup</button>
                    <button type="button" class="btn btn-lg btn-warning" data-toggle="modal" data-target="#fileRestoreModal"><i class="fas fa-fw fa-undo"></i> Restore from file</button>

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

<!-- File Restore Modal -->
<div class="modal" id="fileRestoreModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Database from File</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fileToRestore">Select .sql File:</label>
                        <input type="file" class="form-control-file" id="fileToRestore" name="fileToRestore" accept=".sql" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="filerestore-proceed" class="btn btn-primary">Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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

<!-- File Restore Modal -->
<div class="modal" id="fileRestoreModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Database from File</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fileToRestore">Select .sql File:</label>
                        <input type="file" class="form-control-file" id="fileToRestore" name="fileToRestore" accept=".sql" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="filerestore-proceed" class="btn btn-primary">Proceed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php
require_once "footer.php";
?>
