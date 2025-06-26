<?php
/*
* Client Portal
* Contact management for PTC / technical contacts
*/

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

if ($session_contact_primary == 0 && !$session_contact_is_technical_contact) {
    header("Location: post.php?logout");
    exit();
}

$assets_sql = mysqli_query($mysqli, "SELECT * FROM assets LEFT JOIN contacts ON asset_contact_id = contact_id WHERE asset_client_id = $session_client_id AND asset_archived_at IS NULL ORDER BY asset_type ASC, asset_name ASC");
?>

    <div class="row">
        <div class="col">
            <h3>Assets</h3>
        </div>
    </div>

    <div class="row">

        <div class="col-md-12">

            <table class="table tabled-bordered border border-dark">
                <thead class="thead-dark">
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Model</th>
                    <th>Serial</th>
                    <th>Assigned</th>
                    <th>Purchase</th>
                    <th>Warranty</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>

                <?php
                while ($row = mysqli_fetch_array($assets_sql)) {
                    $asset_id = intval($row['asset_id']);
                    $asset_name = nullable_htmlentities($row['asset_name']);
                    $asset_description = nullable_htmlentities($row['asset_description']);
                    $asset_type = nullable_htmlentities($row['asset_type']);
                    $asset_make = nullable_htmlentities($row['asset_make']);
                    $asset_model = nullable_htmlentities($row['asset_model']);
                    $asset_serial = nullable_htmlentities($row['asset_serial']);
                    $asset_purchase_date = nullable_htmlentities($row['asset_purchase_date'] ?? "-");
                    $asset_warranty_expire = nullable_htmlentities($row['asset_warranty_expire'] ?? "-");
                    $assigned_to = nullable_htmlentities($row['contact_name'] ?? "-");
                    $asset_status = nullable_htmlentities($row['asset_status']);

                    ?>

                    <tr>
                        <td>
                            <a href="#"><?php echo $asset_name ?></a>
                            <br>
                            <small class="text-secondary"><?php echo $asset_description; ?></small>
                        </td>
                        <td><?php echo $asset_type; ?></td>
                        <td><?php echo "$asset_make<br><span class='text-secondary'>$asset_model</span>"; ?></td>
                        <td><?php echo $asset_serial; ?></td>
                        <td><?php echo $assigned_to; ?></td>
                        <td><?php echo $asset_purchase_date; ?></td>
                        <td><?php echo $asset_warranty_expire; ?></td>
                        <td><?php echo $asset_status; ?></td>
                    </tr>

                <?php } ?>

                </tbody>
            </table>

        </div>

    </div>

<?php
require_once "includes/footer.php";
