<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$interface_ids = array_map('intval', $_GET['interface_ids'] ?? []);

$count = count($interface_ids);

ob_start();

?>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php foreach ($interface_ids as $interface_id) { ?><input type="hidden" name="interface_ids[]" value="<?= $interface_id ?>"><?php } ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>Bulk Assign Network</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>

    <div class="modal-body">

        <!-- Network -->
        <div class="form-group">
            <label>Network</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                </div>
                <select class="form-control select2" name="bulk_network">
                    <option value="">- Select a Network -</option>
                    <?php
                    $sql_network_select = mysqli_query($mysqli, "
                        SELECT network_id, network_name, network
                        FROM networks
                        WHERE network_archived_at IS NULL
                            AND network_client_id = $client_id
                        ORDER BY network_name ASC
                    ");
                    while ($net_row = mysqli_fetch_assoc($sql_network_select)) {
                        $network_id_select   = intval($net_row['network_id']);
                        $network_name_select = escapeHtml($net_row['network_name']);
                        $network_select = escapeHtml($net_row['network']);
                        ?>
                        <option value="<?php echo $network_id_select; ?>"><?php echo "$network_name_select - $network_select"; ?></option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="bulk_edit_asset_interface_network" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Assign</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
