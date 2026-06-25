<?php

require_once '../../../includes/modal_header.php';

$network_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM networks WHERE network_id = $network_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$network_name = nullable_htmlentities($row['network_name']);
$network_description = nullable_htmlentities($row['network_description']);
$network_vlan = intval($row['network_vlan']);
$network = nullable_htmlentities($row['network']);
$network_gateway = nullable_htmlentities($row['network_gateway']);
$network_primary_dns = nullable_htmlentities($row['network_primary_dns']);
$network_secondary_dns = nullable_htmlentities($row['network_secondary_dns']);
$network_dhcp_range = nullable_htmlentities($row['network_dhcp_range']);
$network_notes = nullable_htmlentities($row['network_notes']);
$network_location_id = intval($row['network_location_id']);
$client_id = intval($row['network_client_id']);

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-network-wired mr-2"></i>Editing network: <span class="text-bold"><?= $network_name ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="network_id" value="<?= $network_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pillsEditDetails">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNetwork">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditDNS">DNS</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNotes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pillsEditDetails">

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
                            while ($row = mysqli_fetch_assoc($locations_sql)) {
                                $location_id = intval($row['location_id']);
                                $location_name = nullable_htmlentities($row['location_name']);
                                ?>
                                <option value="<?= $location_id ?>" <?php if ($location_id == $network_location_id) { echo "selected"; } ?>>
                                    <?= $location_name ?>
                                </option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="LAN, WAN, VOIP, Uplink" value="<?= $network_name ?>" maxlength="200" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Guest WiFi, VoIP VLAN, Server LAN, WAN Uplink" value="<?= $network_description ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNetwork">

                <div class="form-group">
                    <label>VLAN</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" inputmode="numeric" name="vlan" placeholder="e.g. 20" value="<?= $network_vlan ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Network (CIDR) <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" name="network" placeholder="192.168.1.0/24 or 2001:db8::/64" maxlength="200" value="<?= $network ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Assignable IP Range</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-arrows-alt-h"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" name="dhcp_range" placeholder="192.168.1.100-192.168.1.200" maxlength="200" value="<?= $network_dhcp_range ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gateway</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" name="gateway" placeholder="192.168.1.1 or 2001:db8::1" maxlength="200" value="<?= $network_gateway ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditDNS">

                <div class="form-group">
                    <label>Primary DNS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" name="primary_dns" placeholder="9.9.9.9 or 2620:fe::fe" maxlength="200" value="<?= $network_primary_dns ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Secondary DNS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control text-monospace" name="secondary_dns" placeholder="1.1.1.1 or 2606:4700:4700::1111" maxlength="200" value="<?= $network_secondary_dns ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes">

                <div class="form-group">
                    <textarea class="form-control" rows="12" name="notes" placeholder="Enter some notes"><?= $network_notes ?></textarea>
                </div>

                <p class="text-muted text-right"><?= $network_id ?></p>
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
