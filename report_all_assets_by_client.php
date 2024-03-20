<?php

require_once "inc_all_reports.php";

validateTechRole();


if (isset($_GET['year'])) {
    $year = intval($_GET['year']);
} else {
    $year = date('Y');
}

$sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-life-ring mr-2"></i>All Assets by Client</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print mr-2"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive-sm">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Asset Name</th>
                        <th>Asset Type</thss=>
                        <th>Asset Status</th=>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($sql_clients)) {
                        $client_id = intval($row['client_id']);
                        $client_name = nullable_htmlentities($row['client_name']);
                        $sql_assets = mysqli_query($mysqli, "SELECT * FROM assets WHERE asset_client_id = $client_id AND asset_archived_at IS NULL ORDER BY asset_name ASC");

                        foreach ($sql_assets as $asset) {
                            $asset_id = intval($asset['asset_id']);
                            $asset_name = nullable_htmlentities($asset['asset_name']);
                            $asset_type = nullable_htmlentities($asset['asset_type']);
                            $asset_status = nullable_htmlentities($asset['asset_status']);
                    ?>

                            <tr>
                                <td>
                                    <a href="/client_assets.php?client_id=<?php echo $client_id ?>">
                                        <?php echo $client_name ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="client_asset_details.php?client_id=<?php echo $client_id ?>&asset_id=<?php echo $asset_id ?>">
                                        <?php echo $asset_name ?>
                                    </a>
                                </td>
                                <td><?php echo $asset_type ?></td>
                                <td><?php echo $asset_status ?></td>
                            </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once "footer.php";

