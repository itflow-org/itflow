<?php

// Determine the linked interface for $interface_id
$linked_interface_id = null;
$sql_link = mysqli_query($mysqli, "
    SELECT interface_a_id, interface_b_id
    FROM asset_interface_links
    WHERE interface_a_id = $interface_id
       OR interface_b_id = $interface_id
    LIMIT 1
");
if ($link_row = mysqli_fetch_assoc($sql_link)) {
    if ($link_row['interface_a_id'] == $interface_id) {
        $linked_interface_id = intval($link_row['interface_b_id']);
    } else {
        $linked_interface_id = intval($link_row['interface_a_id']);
    }
}

?>

<div class="modal" id="editAssetInterfaceModal<?php echo $interface_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-fw fa-ethernet mr-2"></i>
                    Editing: <?php echo htmlspecialchars($interface_name, ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="interface_id" value="<?php echo $interface_id; ?>">

                <div class="modal-body bg-white" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>    

                    <!-- Interface Name -->
                    <div class="form-group">
                        <label>Interface Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="name"
                                placeholder="Interface Name" 
                                maxlength="200"
                                value="<?php echo nullable_htmlentities($interface_name); ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- MAC Address -->
                    <div class="form-group">
                        <label>MAC Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="mac"
                                placeholder="MAC Address" 
                                maxlength="200"
                                value="<?php echo nullable_htmlentities($interface_mac); ?>"
                                data-inputmask="'alias': 'mac'"
                                data-mask
                            >
                        </div>
                    </div>

                    <!-- IPv4 or DHCP -->
                    <div class="form-group">
                        <label>IPv4 or DHCP</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="ip"
                                placeholder="IP Address" 
                                maxlength="200"
                                value="<?php echo nullable_htmlentities($interface_ip); ?>"
                                data-inputmask="'alias': 'ip'"
                                data-mask
                            >
                            <div class="input-group-append">
                                <div class="input-group-text">
                                    <input 
                                        type="checkbox" 
                                        name="dhcp" 
                                        value="1"
                                        title="Check to mark address as DHCP controlled"
                                        <?php if ($interface_ip === 'DHCP') echo "checked"; ?>
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IPv6 -->
                    <div class="form-group">
                        <label>IPv6</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="ipv6"
                                placeholder="IPv6 Address" 
                                maxlength="200"
                                value="<?php echo nullable_htmlentities($interface_ipv6); ?>"
                            >
                        </div>
                    </div>

                    <!-- Port -->
                    <div class="form-group">
                        <label>Port</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control" 
                                name="port"
                                placeholder="Interface Port ex. eth0"
                                maxlength="200"
                                value="<?php echo nullable_htmlentities($interface_port); ?>"
                            >
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
                                <option value="">- None -</option>
                                <?php
                                $sql_network_select = mysqli_query($mysqli, "
                                    SELECT network_id, network_name, network
                                    FROM networks
                                    WHERE network_archived_at IS NULL
                                      AND network_client_id = $client_id
                                    ORDER BY network_name ASC
                                ");
                                while ($net_row = mysqli_fetch_array($sql_network_select)) {
                                    $network_id_select   = intval($net_row['network_id']);
                                    $network_name_select = nullable_htmlentities($net_row['network_name']);
                                    $network_select      = nullable_htmlentities($net_row['network']);

                                    $selected = ($network_id == $network_id_select) ? 'selected' : '';
                                    echo "<option value='$network_id_select' $selected>$network_name_select - $network_select</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>



                    <!-- Connected to (One-to-One) -->
                    <div class="form-group">
                        <label>Connected to</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                            </div>
                            <select class="form-control select2" name="connected_to">
                                <option value="">- None -</option>
                                <?php
                                $sql_interfaces_select = mysqli_query($mysqli, "
                                    SELECT i.interface_id, i.interface_port, a.asset_name
                                    FROM asset_interfaces i
                                    LEFT JOIN assets a ON a.asset_id = i.interface_asset_id
                                    WHERE a.asset_archived_at IS NULL
                                      AND a.asset_client_id = $client_id
                                      AND i.interface_id != $interface_id
                                      AND a.asset_id != $asset_id
                                      AND (
                                           (
                                             i.interface_id NOT IN (SELECT interface_a_id FROM asset_interface_links)
                                             AND i.interface_id NOT IN (SELECT interface_b_id FROM asset_interface_links)
                                           )
                                           OR i.interface_id = " . (int)$linked_interface_id . "
                                      )
                                    ORDER BY a.asset_name ASC, i.interface_port ASC
                                ");
                                while ($row_if = mysqli_fetch_array($sql_interfaces_select)) {
                                    $iface_id_select         = intval($row_if['interface_id']);
                                    $iface_port_select       = nullable_htmlentities($row_if['interface_port']);
                                    $iface_asset_name_select = nullable_htmlentities($row_if['asset_name']);

                                    $selected = ($linked_interface_id === $iface_id_select) ? 'selected' : '';
                                    echo "<option value='$iface_id_select' $selected>";
                                    echo "$iface_asset_name_select - $iface_port_select";
                                    echo "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <textarea class="form-control" rows="5" placeholder="Enter some notes" name="notes"><?php echo $interface_notes; ?></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_asset_interface" class="btn btn-primary">
                        <i class="fa fa-check mr-2"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
