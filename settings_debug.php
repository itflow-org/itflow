<?php
require_once("inc_all_settings.php");
require_once("database_version.php");
require_once("config.php");

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
    
    //$mysqli->close();
    
    return $tables;
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

//Get Versions
$phpVersion = phpversion();
$mysqlVersion = $mysqli->server_version;
$operatingSystem = shell_exec('uname -a');
$webServer = $_SERVER['SERVER_SOFTWARE'];

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-bug mr-2"></i>Debug</h3>
        </div>
        <div class="card-body">

            <div class="row">

                <div class="col-md-12">
                    <div class="card card-dark">
                        <div class="card-header py-3">
                            <h3 class="card-title"><i class="fas fa-fw fa-database mr-2"></i>Database Structure Check</h3>
                        </div>
                        <div class="card-body">            
                            
                            <?php
                            if (empty($differences)) {
                                echo "The database structure matches the desired structure.";
                            } else {
                                echo "Differences found:\n";
                                print_r($differences);
                            }
                            ?>

                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-dark">
                        <div class="card-header py-3">
                            <h3 class="card-title"><i class="fas fa-fw fa-chart-bar mr-2"></i>Database Statistics</h3>
                        </div>
                        <div class="card-body">
                            
                            <?php
                            echo "Number of tables: " . $numTables . "<br>";
                            echo "Total number of fields: " . $numFields . "<br>";
                            echo "Total number of rows: " . $numRows . "<br>";
                            ?>
                            
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-dark">
                        <div class="card-header py-3">
                            <h3 class="card-title"><i class="fas fa-fw fa-check mr-2"></i>Versions Check</h3>
                        </div>
                        <div class="card-body">
                            
                            <?php
                            echo "PHP version: " . $phpVersion . "<br>";
                            echo "MySQL Version: " . $mysqlVersion . "<br>";
                            echo "Operating System: " . $operatingSystem . "<br>";
                            echo "Web Server: " . $webServer;

                            ?>

                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-dark">
                        <div class="card-header py-3">
                            <h3 class="card-title"><i class="fas fa-fw fa-hdd mr-2"></i>File system Check</h3>
                        </div>
                        <div class="card-body">

                            <?php
                            $result = countFilesInDirectory($folderPath);

                            $totalFiles = $result['count'];
                            $totalSizeMB = round($result['size'] / (1024 * 1024), 2);

                            echo "Total number of files in $folderPath and its subdirectories: " . $totalFiles . "<br>";
                            echo "Total size of files in $folderPath and its subdirectories: " . $totalSizeMB . " MB";
                            ?>

                        
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card card-dark">
                        <div class="card-header py-3">
                            <h3 class="card-title"><i class="fas fa-fw fa-puzzle-piece mr-2"></i>PHP Modules Installed</h3>
                        </div>
                        <div class="card-body">                
                            
                            <?php
                            foreach ($loadedModules as $module) {
                                echo $module . "<br>";
                            }
                            ?>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

<?php

require_once("footer.php");
