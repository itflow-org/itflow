<?php

require_once '../../../includes/modal_header.php';

$network_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = $network_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$network_name = nullable_htmlentities($row['network_name']);
$network_description = nullable_htmlentities($row['network_description']);
$network_vlan = intval($row['network_vlan']);
$network = nullable_htmlentities($row['network']);
$network_subnet = nullable_htmlentities($row['network_subnet']);
$network_gateway = nullable_htmlentities($row['network_gateway']);
$network_primary_dns = nullable_htmlentities($row['network_primary_dns']);
$network_secondary_dns = nullable_htmlentities($row['network_secondary_dns']);
$network_dhcp_range = nullable_htmlentities($row['network_dhcp_range']);
$network_notes = nullable_htmlentities($row['network_notes']);
$network_location_id = intval($row['network_location_id']);
$client_id = intval($row['network_client_id']);

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>Editing network: <span class="text-bold"><?php echo $network_name; ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="network_id" value="<?php echo $network_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pillsEditDetails<?php echo $network_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNetwork<?php echo $network_id; ?>">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditDNS<?php echo $network_id; ?>">DNS</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNotes<?php echo $network_id; ?>">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pillsEditDetails<?php echo $network_id; ?>">

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Network name (VLAN, WAN, LAN2 etc)" value="<?php echo $network_name; ?>" maxlength="200" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?php echo $network_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        </div>
                        <select class="form-control select2" name="location">
                            <option value="">- Select Location -</option>
                            <?php 
                            $locations_sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_client_id = $client_id");
                            while ($row = mysqli_fetch_array($locations_sql)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                                ?>
                                <option value="<?php echo $location_id; ?>" <?php if ($location_id == $network_location_id) { echo "selected"; } ?>>
                                    <?php echo $location_name; ?>
                                </option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNetwork<?php echo $network_id; ?>">

                <div class="form-group">
                    <label>vLAN</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" name="vlan" placeholder="ex. 20" value="<?php echo $network_vlan; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>IP / Network <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        </div>
                        <input type="text" class="form-control" name="network" placeholder="Network or IP ex 192.168.1.0/24" maxlength="200" value="<?php echo $network; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Subnet Mask</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-mask"></i></span>
                        </div>
                        <input type="text" class="form-control" name="subnet" placeholder="ex 255.255.255.0" maxlength="200" data-inputmask="'alias': 'ip'" data-mask value="<?php echo $network_subnet; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gateway <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
                        </div>
                        <input type="text" class="form-control" name="gateway" placeholder="ex 192.168.1.1" maxlength="200" data-inputmask="'alias': 'ip'" data-mask value="<?php echo $network_gateway; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>DHCP Range / IPs</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                        </div>
                        <input type="text" class="form-control" name="dhcp_range" placeholder="ex 192.168.1.11-199" maxlength="200" value="<?php echo $network_dhcp_range; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditDNS<?php echo $network_id; ?>">

                <div class="form-group">
                    <label>Primary DNS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <input type="text" class="form-control" name="primary_dns" placeholder="ex 9.9.9.9" maxlength="200" data-inputmask="'alias': 'ip'" data-mask value="<?php echo $network_primary_dns; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Secondary DNS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <input type="text" class="form-control" name="secondary_dns" placeholder="ex 1.1.1.1" maxlength="200" data-inputmask="'alias': 'ip'" data-mask value="<?php echo $network_secondary_dns; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes<?php echo $network_id; ?>">

                <div class="form-group">
                    <textarea class="form-control" rows="12" name="notes" placeholder="Enter some notes"><?php echo $network_notes; ?></textarea>
                </div>

                <p class="text-muted text-right"><?php echo $network_id; ?></p>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_network" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
