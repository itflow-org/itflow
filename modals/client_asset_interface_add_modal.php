<div class="modal" id="addAssetInterfaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-ethernet mr-2"></i>New Network Interface</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Interface Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Interface Name" maxlength="200" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>MAC Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="text" class="form-control" name="mac" placeholder="MAC Address" data-inputmask="'alias': 'mac'" maxlength="200" data-mask>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>IP or DHCP</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="text" class="form-control" name="ip" placeholder="IP Address" data-inputmask="'alias': 'ip'" maxlength="200" data-mask>
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <input type="checkbox" name="dhcp" value="1" title="Check to mark address as DHCP controlled">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>IPv6</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="text" class="form-control" name="ipv6" placeholder="IPv6 Address" maxlength="200">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Port</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input type="text" class="form-control" name="port" placeholder="Interface Port ex. eth0" maxlength="200">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Network</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                            </div>
                            <select class="form-control select2" name="network">
                                <option value="">- None -</option>
                                <?php

                                $sql_network_select = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
                                while ($row = mysqli_fetch_array($sql_network_select)) {
                                    $network_id = $row['network_id'];
                                    $network_name = nullable_htmlentities($row['network_name']);
                                    $network = nullable_htmlentities($row['network']);

                                    ?>
                                    <option value="<?php echo $network_id; ?>"><?php echo $network_name; ?> - <?php echo $network; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Connected to</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="connected_to">
                                <option value="">- None -</option>
                                <?php

                                $sql_interfaces_select = mysqli_query($mysqli, "SELECT * FROM asset_interfaces LEFT JOIN assets ON interface_asset_id = asset_id WHERE asset_archived_at IS NULL AND asset_client_id = $client_id ORDER BY asset_name ASC, interface_port ASC");
                                while ($row = mysqli_fetch_array($sql_interfaces_select)) {
                                    $interface_id_select = intval($row['interface_id']);
                                    $interface_port_select = nullable_htmlentities($row['interface_port']);
                                    $asset_type_select = nullable_htmlentities($row['asset_type']);
                                    $asset_name_select = nullable_htmlentities($row['asset_name']);

                                    ?>
                                    <option value="<?php echo $interface_id_select; ?>"><?php echo "$asset_name_select - $interface_port_select"; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_asset_interface" class="btn btn-primary"><i class="fa fa-check"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
