<div class="modal" id="addMultipleAssetInterfacesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-ethernet mr-2"></i>Creating Multiple Interfaces: <strong><?php echo $asset_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <!-- Starting Interface Number -->
                    <div class="form-group">
                        <label for="interface_start">Starting Interface Number</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="number" id="interface_start" class="form-control" name="interface_start" placeholder="e.g., 1" min="1" required>
                        </div>
                    </div>

                    <!-- Number of Interfaces -->
                    <div class="form-group">
                        <label for="interfaces">Number of Interfaces / Ports</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="number" id="interfaces" class="form-control" name="interfaces" placeholder="How many interfaces to create?" min="1" required>
                        </div>
                    </div>

                    <!-- Type -->
                    <div class="form-group">
                        <label for="network">Type</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                            </div>
                            <select class="form-control select2" name="type">
                                <option value="">- Select Type -</option>
                                <?php foreach($interface_types_array as $interface_type) { ?>
                                    <option><?php echo $interface_type; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <!-- Interface Name -->
                    <div class="form-group">
                        <label for="name_prefix">Interface Name / Port Prefix (Optional)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                            </div>
                            <input type="text" id="name_prefix" class="form-control" name="name_prefix" placeholder="e.g., eth-" maxlength="200">
                        </div>
                    </div>

                    <!-- Network -->
                    <div class="form-group">
                        <label for="network">Network Assignment</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                            </div>
                            <select id="network" class="form-control select2" name="network">
                                <option value="">- Select Network -</option>
                                <?php
                                $sql_network_select = mysqli_query($mysqli, "SELECT network_id, network_name, network FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
                                while ($row = mysqli_fetch_array($sql_network_select)) {
                                    $network_id = $row['network_id'];
                                    $network_name = nullable_htmlentities($row['network_name']);
                                    $network = nullable_htmlentities($row['network']);
                                    ?>
                                    <option value="<?php echo $network_id; ?>">
                                        <?php echo "$network_name - $network"; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <small class="form-text text-muted">Choose the network for these interfaces or leave as None.</small>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" class="form-control" rows="5" placeholder="Enter any additional details or notes" name="notes"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_asset_multiple_interfaces" class="btn btn-primary text-bold">
                        <i class="fas fa-check mr-2"></i>Create Interfaces
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
