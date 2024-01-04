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





// Handle restore action
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['proceed-restore'])) {
    $selectedBackup = $_POST['proceed-restore'];

    // Use realpath to get the canonicalized absolute pathname
    $sqlFile = realpath($backupFolder . $selectedBackup);

    // Check if the obtained path is within the allowed directory
    if ($sqlFile !== false && strpos($sqlFile, realpath($backupFolder)) === 0) {
        $sqlContent = file_get_contents($sqlFile);

        // Execute the entire content using $conn
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
        echo 'Invalid backup path: ' . htmlspecialchars($sqlFile, ENT_QUOTES, 'UTF-8');
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
    if ($_FILES['sqlfile']['error'] === UPLOAD_ERR_OK) {
        $uploadedFileName = $_FILES['sqlfile']['name'];
        $uploadedFilePath = $backupFolder . $uploadedFileName;

        // Move the uploaded file to the backups folder
        if (move_uploaded_file($_FILES['sqlfile']['tmp_name'], $uploadedFilePath)) {
            // Display success message
            echo '<div class="alert alert-success" role="alert">File added to backups list. You can now restore it from the list below.</div>';
        } else {
            // Display error message
            echo '<div class="alert alert-danger" role="alert">Error moving uploaded file. Please try again.</div>';
        }
    } else {
        // Display error message
        echo '<div class="alert alert-danger" role="alert">Error uploading file. Please try again.</div>';
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
                <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Backup Database Maria 18</h3>
            </div>
            <div class="card-body" style="text-align: center;">
                <form method="post">
                    <button type="submit" name="backup" class="btn btn-lg btn-primary"><i class="fas fa-fw fa-save"></i> New Backup</button>
                     <button type="submit" name="filerestore" class="btn btn-lg btn-warning" data-toggle="modal" data-target="#fileRestoreModal"><i class="fas fa-fw fa-undo"></i> Restore from file</button>
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
                        <th>Download</th>
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
                            </td>
                            <td><a href="<?= $backupFolder . $backup ?>" download class="btn btn-info"><i class="fas fa-fw fa-download"></i> Download</a></td>
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
            <button type="submit" name="delete-selected" class="btn btn-danger"><i class="fas fa-fw fa-trash"></i> Delete Selected</button>
        </form>
    </div>
</div>


<!-- Restore from file Modal -->
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
                        <label for="sqlfile">Select SQL File:</label>
                        <input type="file" class="form-control" name="sqlfile" accept=".sql" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="filerestore-proceed">Add to Restore</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once "footer.php";
?>
