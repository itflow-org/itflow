<?php

require_once "inc_all_reports.php";

validateTechRole();

if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}


// Custom columns
if (isset($_POST['submit'])) {
    $selected_columns = isset($_POST['columns']) ? $_POST['columns'] : [];
} else {
    // Set default columns if no selection is made
    $selected_columns = ['client_name', 'asset_name', 'asset_type', 'asset_status'];  // Example default columns
}

$available_columns_sql = mysqli_query($mysqli, "SHOW COLUMNS FROM assets");
while ($row = mysqli_fetch_array($available_columns_sql)) {
    $available_columns[] = $row['Field'];
}

if (!empty($selected_columns)) {
    $selected_columns = array_intersect($selected_columns, $available_columns); // Filter acceptable columns
    $selected_columns = ['client_name', ...$selected_columns];

    $columns_to_display = implode(", ", $selected_columns);
    $query = "SELECT $columns_to_display FROM assets
      LEFT JOIN clients on asset_client_id = client_id
      ORDER BY asset_client_id, asset_name";
} else {
    $query = "SELECT client_name, asset_name, asset_type, asset_status FROM assets
      LEFT JOIN clients on asset_client_id = client_id
      ORDER BY asset_client_id, asset_name";  // Fallback to default columns
}

$assets_sql = mysqli_query($mysqli, $query);

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>All Assets by Client - with custom columns</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive-sm">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <?php
                        foreach ($selected_columns as $col) {
                            echo "<th>" . htmlspecialchars($col) . "</th>";
                        }
                        ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    while ($row = mysqli_fetch_assoc($assets_sql)) {
                        echo "<tr>";
                        foreach ($selected_columns as $col) {
                            echo "<td>" . nullable_htmlentities($row[$col]) . "</td>";
                        }
                        echo "</tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <form method="post" action="">
                <label><input type="checkbox" name="columns[]" value="client_name" checked>Client Name</label>
                <label><input type="checkbox" name="columns[]" value="asset_name" checked>Name</label>
                <label><input type="checkbox" name="columns[]" value="asset_type" checked>Type</label>
                <label><input type="checkbox" name="columns[]" value="asset_make">Make</label>
                <label><input type="checkbox" name="columns[]" value="asset_model">Model</label>
                <label><input type="checkbox" name="columns[]" value="asset_serial">Serial</label>
                <label><input type="checkbox" name="columns[]" value="asset_os">OS</label>
                <label><input type="checkbox" name="columns[]" value="asset_status" checked>Status</label>
                <label><input type="checkbox" name="columns[]" value="asset_purchase_date">Purchase</label>
                <label><input type="checkbox" name="columns[]" value="asset_install_date">Install</label>
                <label><input type="checkbox" name="columns[]" value="asset_warranty_expire">Warranty</label>
                <label><input type="checkbox" name="columns[]" value="asset_archived_at">Archived</label>

                <input type="submit" name="submit" value="Update Columns">
            </form>
        </div>
    </div>

<?php
require_once "footer.php";

