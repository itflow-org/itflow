<?php

require_once "includes/inc_all_admin.php";
require_once "../includes/database_version.php";
require_once "../config.php";

$checks = [];

// Execute the git command to get the latest commit hash
$commitHash = shell_exec('git log -1 --format=%H');

// Get branch info
$gitBranch = shell_exec('git rev-parse --abbrev-ref HEAD');

// Section: System Information
$systemInfo = [];

// Operating System and Version
$os = php_uname();
$systemInfo[] = [
    'name' => 'Operating System',
    'value' => $os,
];

// Web Server and Version
$webServer = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$systemInfo[] = [
    'name' => 'Web Server',
    'value' => $webServer,
];

// Kernel and Version
$kernelVersion = php_uname('r');
$systemInfo[] = [
    'name' => 'Kernel Version',
    'value' => $kernelVersion,
];

// Database and Version
$dbVersion = $mysqli->server_info;
$systemInfo[] = [
    'name' => 'Database Version',
    'value' => $dbVersion,
];

// Section: PHP Extensions
$phpExtensions = [];
$extensions = [
    'php-mailparse' => 'mailparse',
    'php-imap' => 'imap',
    'php-mysqli' => 'mysqli',
    'php-intl' => 'intl',
    'php-curl' => 'curl',
    'php-mbstring' => 'mbstring',
    'php-gd' => 'gd',
    'php-zip' => 'zip',
];

foreach ($extensions as $name => $ext) {
    $loaded = extension_loaded($ext);
    $phpExtensions[] = [
        'name' => "$name installed",
        'passed' => $loaded,
        'value' => $loaded ? 'Installed' : 'Not Installed',
    ];
}

// Section: PHP Configuration
$phpConfig = [];

// Check if shell_exec is enabled
$disabled_functions = explode(',', ini_get('disable_functions'));
$disabled_functions = array_map('trim', $disabled_functions);
$shell_exec_enabled = !in_array('shell_exec', $disabled_functions);

$phpConfig[] = [
    'name' => 'shell_exec is enabled',
    'passed' => $shell_exec_enabled,
    'value' => $shell_exec_enabled ? 'Enabled' : 'Disabled',
];

// Check upload_max_filesize and post_max_size >= 500M
function return_bytes($val) {
    $val = trim($val);
    $unit = strtolower(substr($val, -1));
    $num = (float)$val;
    switch ($unit) {
        case 'g':
            $num *= 1024;
        case 'm':
            $num *= 1024;
        case 'k':
            $num *= 1024;
    }
    return $num;
}

$required_bytes = 500 * 1024 * 1024; // 500M in bytes

$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');

$upload_passed = return_bytes($upload_max_filesize) >= $required_bytes;
$post_passed = return_bytes($post_max_size) >= $required_bytes;

$phpConfig[] = [
    'name' => 'upload_max_filesize >= 500M',
    'passed' => $upload_passed,
    'value' => $upload_max_filesize,
];

$phpConfig[] = [
    'name' => 'post_max_size >= 500M',
    'passed' => $post_passed,
    'value' => $post_max_size,
];

// PHP Memory Limit >= 128M
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = return_bytes($memoryLimit);
$memoryLimitPassed = $memoryLimitBytes >= (128 * 1024 * 1024);
$phpConfig[] = [
    'name' => 'PHP Memory Limit >= 128M',
    'passed' => $memoryLimitPassed,
    'value' => $memoryLimit,
];

// Max Execution Time >= 300 seconds
$maxExecutionTime = ini_get('max_execution_time');
$maxExecutionTimePassed = $maxExecutionTime >= 300;
$phpConfig[] = [
    'name' => 'Max Execution Time >= 300 seconds',
    'passed' => $maxExecutionTimePassed,
    'value' => $maxExecutionTime . ' seconds',
];

// Check PHP version >= 8.2.0
$php_version = PHP_VERSION;
$php_passed = version_compare($php_version, '8.2.0', '>=');

$phpConfig[] = [
    'name' => 'PHP version >= 8.2.0',
    'passed' => $php_passed,
    'value' => $php_version,
];

// Section: Shell Commands
$shellCommands = [];

if ($shell_exec_enabled) {
    $commands = ['whois', 'dig', 'git'];

    foreach ($commands as $command) {
        $which = trim(shell_exec("which $command 2>/dev/null"));
        $exists = !empty($which);
        $shellCommands[] = [
            'name' => "Command '$command' available",
            'passed' => $exists,
            'value' => $exists ? $which : 'Not Found',
        ];
    }
} else {
    // If shell_exec is disabled, mark commands as unavailable
    foreach (['whois', 'dig', 'git'] as $command) {
        $shellCommands[] = [
            'name' => "Command '$command' available",
            'passed' => false,
            'value' => 'shell_exec Disabled',
        ];
    }
}

// Section: SSL Checks
$sslChecks = [];

// Check if accessing via HTTPS
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$sslChecks[] = [
    'name' => 'Accessing via HTTPS',
    'passed' => $https,
    'value' => $https ? 'Yes' : 'No',
];

// SSL Certificate Validity Check
if ($https) {
    $streamContext = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
    $socket = @stream_socket_client("ssl://{$_SERVER['HTTP_HOST']}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $streamContext);

    if ($socket) {
        $params = stream_context_get_params($socket);
        $cert = $params['options']['ssl']['peer_certificate'];
        $certInfo = openssl_x509_parse($cert);

        $validFrom = $certInfo['validFrom_time_t'];
        $validTo = $certInfo['validTo_time_t'];
        $currentTime = time();

        $certValid = ($currentTime >= $validFrom && $currentTime <= $validTo);

        $sslChecks[] = [
            'name' => 'SSL Certificate is valid',
            'passed' => $certValid,
            'value' => $certValid ? 'Valid' : 'Invalid or Expired',
        ];
    } else {
        $sslChecks[] = [
            'name' => 'SSL Certificate is valid',
            'passed' => false,
            'value' => 'Unable to retrieve certificate',
        ];
    }
} else {
    $sslChecks[] = [
        'name' => 'SSL Certificate is valid',
        'passed' => false,
        'value' => 'Not using HTTPS',
    ];
}

// Section: Domain Checks
$domainChecks = [];

// Check if the site has a valid FQDN
$fqdn = $_SERVER['HTTP_HOST'];
$isValidFqdn = (bool) filter_var('http://' . $fqdn, FILTER_VALIDATE_URL) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $fqdn);

$domainChecks[] = [
    'name' => 'Site has a valid FQDN',
    'passed' => $isValidFqdn,
    'value' => $fqdn,
];

// Section: File Permissions
$filePermissions = [];

// Check if web user has write access to webroot directory
$webroot = $_SERVER['DOCUMENT_ROOT'];
$writable = is_writable($webroot);
$filePermissions[] = [
    'name' => 'Web user has write access to webroot directory',
    'passed' => $writable,
    'value' => $webroot,
];

// Section: Uploads Directory Stats
$uploadsStats = [];

// Define the uploads directory path
$uploadsDir = __DIR__ . '/uploads'; // Adjust the path if needed

if (is_dir($uploadsDir)) {
    // Function to recursively count files and calculate total size
    function getDirStats($dir) {
        $files = 0;
        $size = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files++;
                $size += $file->getSize();
            }
        }
        return ['files' => $files, 'size' => $size];
    }

    $stats = getDirStats($uploadsDir);
    $sizeInMB = round($stats['size'] / (1024 * 1024), 2);

    $uploadsStats[] = [
        'name' => 'Number of files in uploads directory',
        'value' => $stats['files'],
    ];

    $uploadsStats[] = [
        'name' => 'Total size of uploads directory (MB)',
        'value' => $sizeInMB . ' MB',
    ];
} else {
    $uploadsStats[] = [
        'name' => 'Uploads directory exists',
        'value' => 'Directory not found',
    ];
}

// Section: Database Stats
$databaseStats = [];

// Get list of tables
$tablesResult = $mysqli->query("SHOW TABLE STATUS");
if ($tablesResult) {
    $totalTables = 0;
    $totalFields = 0;
    $totalRows = 0;
    $totalSize = 0;
    $tableDetails = [];

    while ($table = $tablesResult->fetch_assoc()) {
        $tableName = $table['Name'];

        // Accurate row count
        $countResult = $mysqli->query("SELECT COUNT(*) AS cnt FROM `$tableName`");
        $countRow = $countResult->fetch_assoc();
        $tableRows = $countRow['cnt'];
        $countResult->free();

        $dataLength = $table['Data_length'];
        $indexLength = $table['Index_length'];
        $tableSize = ($dataLength + $indexLength) / (1024 * 1024); // Size in MB

        // Get number of fields
        $fieldsResult = $mysqli->query("SHOW COLUMNS FROM `$tableName`");
        $numFields = $fieldsResult->num_rows;
        $fieldsResult->free();

        $totalTables++;
        $totalFields += $numFields;
        $totalRows += $tableRows;
        $totalSize += $tableSize;

        $tableDetails[] = [
            'name' => $tableName,
            'fields' => $numFields,
            'rows' => $tableRows,
            'size' => round($tableSize, 2),
        ];
    }
    $tablesResult->free();

    $databaseStats[] = [
        'name' => 'Total number of tables',
        'value' => $totalTables,
    ];
    $databaseStats[] = [
        'name' => 'Total number of fields',
        'value' => $totalFields,
    ];
    $databaseStats[] = [
        'name' => 'Total number of rows',
        'value' => $totalRows,
    ];
    $databaseStats[] = [
        'name' => 'Total database size (MB)',
        'value' => round($totalSize, 2) . ' MB',
    ];
}

// Section: Database Structure Comparison
$dbComparison = [];

// Path to the db.sql file
$dbSqlFile = __DIR__ . '/db.sql';

if (file_exists($dbSqlFile)) {
    // Read the db.sql file
    $sqlContent = file_get_contents($dbSqlFile);

    // Remove comments and empty lines
    $lines = explode("\n", $sqlContent);
    $sqlStatements = [];
    $statement = '';

    foreach ($lines as $line) {
        // Remove single-line comments
        $line = preg_replace('/--.*$/', '', $line);
        $line = preg_replace('/\/\*.*?\*\//', '', $line);

        // Skip empty lines
        if (trim($line) == '') {
            continue;
        }

        // Append line to the current statement
        $statement .= $line . "\n";

        // Check if the statement ends with a semicolon
        if (preg_match('/;\s*$/', $line)) {
            $sqlStatements[] = $statement;
            $statement = '';
        }
    }

    // Parse the CREATE TABLE statements
    $sqlTables = [];

    foreach ($sqlStatements as $sql) {
        if (preg_match('/CREATE TABLE\s+`?([^` ]+)`?\s*\((.*)\)(.*?);/msi', $sql, $match)) {
            $tableName = $match[1];
            $columnsDefinition = $match[2];

            // Extract column names and data types
            $columns = [];
            $columnLines = explode("\n", $columnsDefinition);
            foreach ($columnLines as $line) {
                $line = trim($line);
                // Skip empty lines and lines that do not define columns
                if ($line == '' || strpos($line, 'PRIMARY KEY') !== false || strpos($line, 'UNIQUE KEY') !== false || strpos($line, 'KEY') === 0 || strpos($line, 'CONSTRAINT') === 0 || strpos($line, ')') === 0) {
                    continue;
                }

                // Remove trailing comma if present
                $line = rtrim($line, ',');

                // Match column definition
                if (preg_match('/^`([^`]+)`\s+(.+)/', $line, $colMatch)) {
                    $colName = $colMatch[1];
                    $colDefinition = $colMatch[2];

                    // Extract the data type from the column definition
                    $tokens = preg_split('/\s+/', $colDefinition);
                    $colType = $tokens[0];

                    // Handle data types with parentheses (e.g., varchar(255), decimal(15,2))
                    if (preg_match('/^([a-zA-Z]+)\(([^)]+)\)/', $colType, $typeMatch)) {
                        $colType = $typeMatch[1] . '(' . $typeMatch[2] . ')';
                    }

                    $columns[$colName] = $colType;
                }
            }
            $sqlTables[$tableName] = $columns;
        }
    }

    // Get current database table structures
    $dbTables = [];
    $tablesResult = $mysqli->query("SHOW TABLES");
    while ($row = $tablesResult->fetch_row()) {
        $tableName = $row[0];
        $columnsResult = $mysqli->query("SHOW COLUMNS FROM `$tableName`");
        $columns = [];
        while ($col = $columnsResult->fetch_assoc()) {
            $columns[$col['Field']] = $col['Type'];
        }
        $columnsResult->free();
        $dbTables[$tableName] = $columns;
    }
    $tablesResult->free();

    // Compare the structures
    foreach ($sqlTables as $tableName => $sqlColumns) {
        if (!isset($dbTables[$tableName])) {
            $dbComparison[] = [
                'name' => "Table `$tableName` missing in database",
                'status' => 'Missing Table',
            ];
            continue;
        }

        // Compare columns
        $dbColumns = $dbTables[$tableName];
        foreach ($sqlColumns as $colName => $colType) {
            if (!isset($dbColumns[$colName])) {
                $dbComparison[] = [
                    'name' => "Column `$colName` missing in table `$tableName`",
                    'status' => 'Missing Column',
                ];
            } else {
                // Normalize data types for comparison
                $sqlColType = strtolower($colType);
                $dbColType = strtolower($dbColumns[$colName]);

                // Remove attributes and constraints
                $sqlColType = preg_replace('/\s+.*$/', '', $sqlColType);
                $dbColType = preg_replace('/\s+.*$/', '', $dbColType);

                // Remove additional attributes like unsigned, zerofill, etc.
                $sqlColType = preg_replace('/\s+unsigned|\s+zerofill|\s+binary/', '', $sqlColType);
                $dbColType = preg_replace('/\s+unsigned|\s+zerofill|\s+binary/', '', $dbColType);

                if ($sqlColType != $dbColType) {
                    $dbComparison[] = [
                        'name' => "Data type mismatch for `$colName` in table `$tableName`",
                        'status' => "Expected: $colType, Found: {$dbColumns[$colName]}",
                    ];
                }
            }
        }

        // Check for extra columns in the database that are not in the SQL file
        foreach ($dbColumns as $colName => $colType) {
            if (!isset($sqlColumns[$colName])) {
                $dbComparison[] = [
                    'name' => "Extra column `$colName` in table `$tableName` not present in db.sql",
                    'status' => 'Extra Column',
                ];
            }
        }
    }

    // Check for tables in the database not present in the db.sql file
    foreach ($dbTables as $tableName => $dbColumns) {
        if (!isset($sqlTables[$tableName])) {
            $dbComparison[] = [
                'name' => "Extra table `$tableName` in database not present in db.sql",
                'status' => 'Extra Table',
            ];
        }
    }
} else {
    $dbComparison[] = [
        'name' => 'db.sql file not found',
        'status' => 'File Missing',
    ];
}

$mysqli->close();
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-bug mr-2"></i>Debug</h3>
    </div>
    <div class="card-body">

        <h2>Debugging</h2>
        <ul>
            <li>If you are experiencing a problem with ITFlow, this page should help you identify any configuration issues.</li>
            <li>Note: You might also need to gather <a href="https://docs.itflow.org/gathering_logs#error_logs">error logs</a></li>
        </ul>
        <hr>

        <div class="table-responsive">
            <table class="table table-bordered mb-3">
                <tr>
                    <th>ITFlow release version</th>
                    <th><?php echo APP_VERSION; ?></th>
                </tr>
                <tr>
                    <td>Current DB Version</td>
                    <td><?php echo CURRENT_DATABASE_VERSION; ?></td>
                </tr>
                <tr>
                    <td>Current Code Commit</td>
                    <td><?php echo $commitHash; ?></td>
                </tr>
                <tr>
                    <td>Current Branch</td>
                    <td><?php echo $gitBranch; ?></td>
                </tr>
            </table>
        </div>

        <!-- System Information Table -->
        <h3>System Information</h3>
        <table class="table table-sm table-bordered">
            <tbody>
                <?php foreach ($systemInfo as $info): ?>
                    <tr>
                        <td><?= htmlspecialchars($info['name']); ?></td>
                        <td><?= htmlspecialchars($info['value']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- PHP Extensions and Configuration Table -->
        <h3 class="mt-3">PHP Extensions and Configuration</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <!-- PHP Extensions Section -->
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">PHP Extensions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($phpExtensions as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <!-- PHP Configuration Section -->
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">PHP Configuration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($phpConfig as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">Shell Commands</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shellCommands as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">SSL Checks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sslChecks as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">Domain Checks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domainChecks as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <!-- File Permissions Table -->
                <thead>
                    <tr class="table-secondary">
                        <th colspan="3">File Permissions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filePermissions as $check): ?>
                        <tr>
                            <td><?= htmlspecialchars($check['name']); ?></td>
                            <td class="text-center">
                                <?php if ($check['passed']): ?>
                                    <i class="fas fa-check" style="color:green"></i>
                                <?php else: ?>
                                    <i class="fas fa-times" style="color:red"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($check['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        

        <!-- Database Structure Comparison Table -->
        <h3 class="mt-3">Database Structure Comparison</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <tbody>
                    <?php if (!empty($dbComparison)): ?>
                        <?php foreach ($dbComparison as $issue): ?>
                            <tr>
                                <td><?= htmlspecialchars($issue['name']); ?></td>
                                <td colspan="2"><?= htmlspecialchars($issue['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">No discrepancies found between the database and db.sql file.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Uploads Directory Stats Table -->
        <h3 class="mt-3">Uploads Directory Stats</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <tbody>
                    <?php foreach ($uploadsStats as $stat): ?>
                        <tr>
                            <td><?= htmlspecialchars($stat['name']); ?></td>
                            <td colspan="2"><?= htmlspecialchars($stat['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Database Stats Table -->
        <h3 class="mt-3">Database Stats</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <tbody>
                    <?php foreach ($databaseStats as $stat): ?>
                        <tr>
                            <td><?= htmlspecialchars($stat['name']); ?></td>
                            <td colspan="2"><?= htmlspecialchars($stat['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Stats Table -->
        <h3 class="mt-3">Table Stats</h3>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Fields / Rows</th>
                        <th>Size (MB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableDetails as $table): ?>
                        <tr>
                            <td><?= htmlspecialchars($table['name']); ?></td>
                            <td><?= htmlspecialchars("Fields: {$table['fields']}, Rows: {$table['rows']}"); ?></td>
                            <td><?= htmlspecialchars($table['size'] . ' MB'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<?php

require_once "../includes/footer.php";

