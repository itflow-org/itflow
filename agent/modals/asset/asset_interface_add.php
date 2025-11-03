<?php

require_once '../../../includes/modal_header.php';

$asset_id = intval($_GET['asset_id'] ?? 0);
$client_id = intval(getFieldById('assets', $asset_id, 'asset_client_id') ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-ethernet mr-2"></i>New Network Interface</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="asset_id" value="<?php echo $asset_id; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-interface-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-interface-network">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-interface-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-interface-details">

                <!-- Interface Name -->
                <div class="form-group">
                    <label>Interface Name or Port / <span class="text-secondary">Primary</span></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Interface name or port number" maxlength="200" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <input type="checkbox" name="primary_interface" value="1" title="Mark Interface as primary">
                            </div>
                        </div>
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

                <!-- Interface Description -->
                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input 
                            type="text" 
                            class="form-control" 
                            name="description"
                            placeholder="Short Description" 
                            maxlength="200"
                        >
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-interface-network">

                <!-- MAC Address -->
                <div class="form-group">
                    <label>MAC Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input type="text" class="form-control" name="mac" placeholder="MAC Address" data-inputmask="'alias': 'mac'" maxlength="200" data-mask>
                    </div>
                </div>

                <!-- IP (with optional DHCP checkbox) -->
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

                <!-- NAT IP -->
                <div class="form-group">
                    <label>NAT IP</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input
                            type="text"
                            class="form-control"
                            name="nat_ip"
                            placeholder="Nat IP"
                            maxlength="200"
                            data-inputmask="'alias': 'ip'"
                            data-mask
                        >
                    </div>
                </div>

                <!-- IPv6 -->
                <div class="form-group">
                    <label>IPv6</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input type="text" class="form-control" name="ipv6" placeholder="IPv6 Address" maxlength="200">
                    </div>
                </div>

                <!-- Network -->
                <div class="form-group">
                    <label>Network</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        </div>
                        <select class="form-control select2" name="network">
                            <option value="">- Select Network -</option>
                            <?php
                            $sql_network_select = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_archived_at IS NULL AND network_client_id = $client_id ORDER BY network_name ASC");
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
                </div>

                <div class="form-group">
                    <label>Connected to</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        </div>
                        <select class="form-control select2" name="connected_to">
                            <option value="">- Select Asset and Interface -</option>
                            <?php
                            $sql_interfaces_select = mysqli_query($mysqli, "
                                SELECT interface_id, interface_name, asset_name
                                FROM asset_interfaces
                                LEFT JOIN assets ON asset_id = interface_asset_id
                                WHERE asset_archived_at IS NULL
                                  AND asset_client_id = $client_id
                                  AND asset_id != $asset_id
                                  AND interface_id NOT IN (SELECT interface_a_id FROM asset_interface_links)
                                  AND interface_id NOT IN (SELECT interface_b_id FROM asset_interface_links)
                                ORDER BY asset_name ASC, interface_name ASC
                            ");

                            while ($row = mysqli_fetch_array($sql_interfaces_select)) {
                                $interface_id_select = intval($row['interface_id']);
                                $interface_name_select = nullable_htmlentities($row['interface_name']);
                                $asset_name_select = nullable_htmlentities($row['asset_name']);
                                ?>
                                <option value="<?php echo $interface_id_select; ?>">
                                    <?php echo "$asset_name_select - $interface_name_select"; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-interface-notes">
                <!-- Notes -->
                <div class="form-group">
                    <textarea class="form-control" rows="8" placeholder="Enter some notes" name="notes"></textarea>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_asset_interface" class="btn btn-primary text-bold">
            <i class="fas fa-check mr-2"></i>Create
        </button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Close</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
