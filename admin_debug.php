<?php
require_once "inc_all_admin.php";

require_once "database_version.php";

require_once "config.php";

$folderPath = 'uploads';

function countFilesInDirectory($dir) {
    $count = 0;
    $size = 0;
    $files = scandir($dir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $dir . '/' . $file;

        if (is_file($filePath)) {
            $count++;
            $size += filesize($filePath);
        } elseif (is_dir($filePath)) {
            $result = countFilesInDirectory($filePath);
            $count += $result['count'];
            $size += $result['size'];
        }
    }

    return [
        'count' => $count,
        'size' => $size
    ];
}

// Function to compare two arrays recursively and return the differences
function arrayDiffRecursive($array1, $array2) {
    $diff = array();

    foreach ($array1 as $key => $value) {
        if (is_array($value)) {
            if (!isset($array2[$key]) || !is_array($array2[$key])) {
                $diff[$key] = $value;
            } else {
                $recursiveDiff = arrayDiffRecursive($value, $array2[$key]);
                if (!empty($recursiveDiff)) {
                    $diff[$key] = $recursiveDiff;
                }
            }
        } else {
            if (!isset($array2[$key]) || $array2[$key] !== $value) {
                $diff[$key] = $value;
            }
        }
    }

    return $diff;
}

// Function to load the table structures from an SQL dump file URL
function loadTableStructuresFromSQLDumpURL($fileURL) {
    $context = stream_context_create(array('http' => array('header' => 'Accept: application/octet-stream')));
    $fileContent = file_get_contents($fileURL, false, $context);

    if ($fileContent === false) {
        return null;
    }

    $structure = array();
    $queries = explode(";", $fileContent);

    foreach ($queries as $query) {
        $query = trim($query);

        if (!empty($query)) {
            if (preg_match("/^CREATE TABLE `(.*)` \((.*)\)$/s", $query, $matches)) {
                $tableName = $matches[1];
                $tableStructure = $matches[2];
                $structure[$tableName] = array('structure' => $tableStructure);
            }
        }
    }

    return $structure;
}

// Function to fetch the database structure from the MySQL server
function fetchDatabaseStructureFromServer() {

    global $mysqli;

    $tables = array();

    // Fetch table names
    $result = $mysqli->query("SHOW TABLES");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_row()) {
            $tableName = $row[0];
            $tables[$tableName] = array();
        }
    }

    // Fetch table structures
    foreach ($tables as $tableName => &$table) {
        $result = $mysqli->query("SHOW CREATE TABLE `$tableName`");

        if ($result->num_rows > 0) {
            $row = $result->fetch_row();
            $table['structure'] = $row[1];
        }
    }

    return $tables;
}

//function to get current crontab and return it as an array
function get_crontab() {
    $crontab = shell_exec('crontab -l');
    $crontab = explode(PHP_EOL, $crontab);
    return $crontab;
}

// URL to the SQL dump file
$fileURL = "https://raw.githubusercontent.com/itflow-org/itflow/master/db.sql";

// Load the desired table structures from the SQL dump file URL
$desiredStructure = loadTableStructuresFromSQLDumpURL($fileURL);

if ($desiredStructure === null) {
    die("Failed to load the desired table structures from the SQL dump file URL.");
}

// Fetch the current database structure from the MySQL server
$currentStructure = fetchDatabaseStructureFromServer();

if ($currentStructure === null) {
    die("Failed to fetch the current database structure from the server.");
}

// Compare the structures and display the differences
$differences = arrayDiffRecursive($desiredStructure, $currentStructure);

//DB Stats
// Query to fetch the number of tables
$tablesQuery = "SHOW TABLES";
$tablesResult = $mysqli->query($tablesQuery);

$numTables = $tablesResult->num_rows;
$numFields = 0;
$numRows = 0;

// Loop through each table
while ($row = $tablesResult->fetch_row()) {
    $tableName = $row[0];

    // Query to fetch the number of fields
    $fieldsQuery = "DESCRIBE `$tableName`";
    $fieldsResult = $mysqli->query($fieldsQuery);

    // Check if the query was successful
    if ($fieldsResult) {
        $numFields += $fieldsResult->num_rows;

        // Query to fetch the number of rows
        $rowsQuery = "SELECT COUNT(*) FROM `$tableName`";
        $rowsResult = $mysqli->query($rowsQuery);

        // Check if the query was successful
        if ($rowsResult) {
            $numRows += $rowsResult->fetch_row()[0];
        } else {
            echo "Error executing query: " . $mysqli->error;
        }
    } else {
        echo "Error executing query: " . $mysqli->error;
    }
}

//Get loaded PHP modules
$loadedModules = get_loaded_extensions();

//Get Server Info / Service versions
$phpVersion = phpversion();
$databaseInfo = mysqli_get_server_info($mysqli) . " / " .  $mysqli->server_version;
$operatingSystem = php_uname();
$webServer = $_SERVER['SERVER_SOFTWARE'];
$errorLog = ini_get('error_log') ?: "Debian/Ubuntu default is usually /var/log/apache2/error.log";
$updates = fetchUpdates();

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-bug mr-2"></i>Debug</h3>
        </div>
        <div class="card-body">

            <h2>Debugging</h2>
            <ul>
                <li>If you are experiencing a problem with ITFlow you may be directed to this page to gather server/app info.</li>
                <li>When creating forum posts / support requests ensure you share the information under <i>Server Info</i>, <i>ITFlow app</i> and <i>Database stats</i>.</li>
                <li><a class="text-danger text-bold">Caution:</a> Be careful when sharing the full debug output - it contains your PHP session variables/cookies ("PHPSESSID") which could allow anyone to login to your ITFlow instance</li>
                <li>Note: Sometimes you might need to gather <a href="https://docs.itflow.org/gathering_logs#error_logs">PHP error logs</a> as well</li>
            </ul>
            <br>

            <h3>Server Info</h3>

            <?php
            echo "PHP version: " . $phpVersion . "<br>";
            echo "Database Version: " . $databaseInfo . "<br>";
            echo "Operating System: " . $operatingSystem . "<br>";
            echo "Web Server: " . $webServer  . "<br>";
            echo "Apache/PHP Error Log: " . $errorLog
            ?>

            <hr>

            <h3>File System</h3>
            <?php
            $result = countFilesInDirectory($folderPath);

            $totalFiles = $result['count'];
            $totalSizeMB = round($result['size'] / (1024 * 1024), 2);

            echo "Total number of files in $folderPath and its subdirectories: " . $totalFiles . "<br>";
            echo "Total size of files in $folderPath and its subdirectories: " . $totalSizeMB . " MB";
            ?>

            <hr>
            <h3>ITFlow app</h3>
            <?php
            echo "App Version: " . $updates->current_version . "<br>";
            echo "Cron enabled: " . $config_enable_cron . "<br>";
            echo "App Timezone: " . $config_timezone;
            ?>

            <hr>

            <h3>Database Structure Check</h3>

            <h4>Database stats</h4>

            <?php
            echo "Number of tables: " . $numTables . "<br>";
            echo "Total number of fields: " . $numFields . "<br>";
            echo "Total number of rows: " . $numRows . "<br>";
            echo "Current Database Version: " . CURRENT_DATABASE_VERSION . "<br>";
            ?>

            <hr>

            <h4>Table Stats</h4>
            <?php
            // Fetch all table names from the database
            $tables = array();
            $result = mysqli_query($mysqli, "SHOW TABLES");
            while ($row = mysqli_fetch_array($result)) {
                $tables[] = $row[0];
            }

            // Generate an HTML table to display the results
            ?>
            <table class="table table-sm">
                <tr>
                    <th>Table Name</th>
                    <th>Number of Fields</th>
                    <th>Number of Rows</th>
                </tr>

                <?php

                foreach ($tables as $table) {
                    // Count the number of fields and rows for each table
                    $columns_result = mysqli_query($mysqli, "SHOW COLUMNS FROM `$table`");
                    $columns = mysqli_num_rows($columns_result);

                    $rows_result = mysqli_query($mysqli, "SELECT COUNT(*) FROM `$table`");
                    $rows = mysqli_fetch_array($rows_result)[0];
                    ?>

                    <tr>
                        <td><?php echo $table; ?></td>
                        <td><?php echo $columns; ?></td>
                        <td><?php echo $rows; ?></td>
                    </tr>
                <?php
                }
                ?>
            </table>

            <hr>

            <h3>PHP Modules Installed</h3>

            <?php
            foreach ($loadedModules as $module) {
                echo $module . "<br>";
            }
            ?>

            <hr>

            <h3>PHP Info</h3>
            <?php
            //Output phpinfo, but in a way that doesnt mess up the page
            ob_start();
            phpinfo();
            $phpinfo = ob_get_contents();
            ob_end_clean();

            //Remove everything before the body tag
            $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);

            //Remove everything after the body tag
            $phpinfo = preg_replace('%^(.*)</body>.*$%ms', '$1', $phpinfo);

            //Remove the body tag itself
            $phpinfo = preg_replace('%^<body>(.*)$%ms', '$1', $phpinfo);

            //Output the result
            echo $phpinfo;
            ?>

            <hr>
        </div>
    </div>

<?php

require_once "footer.php";

