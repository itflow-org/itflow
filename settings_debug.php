<?php
require_once("inc_all_settings.php");
require_once("database_version.php");
require_once("config.php");

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
    
    $mysqli->close();
    
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

?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-bug mr-2"></i>Debug</h3>
        </div>
        <div class="card-body">
            
            <h3>Database Structure Check</h3>
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

<?php

require_once("footer.php");
