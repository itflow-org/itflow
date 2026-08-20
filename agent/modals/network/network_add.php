<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);
$location_id = intval($_GET['location_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-network-wired me-2"></i>New Network</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-network">Network</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-dns">DNS</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-notes">Notes</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <?php } else { ?>

                    <div class="mb-3">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <select class="form-select select2" name="client_id" required>
                                <option value="">- Select Client -</option>
                                <?php

                                $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_assoc($sql)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']); ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?= $client_id_select ?>"><?= $client_name ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Location</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                        <select class="form-select select2" name="location">
                            <option value="">- Select Location -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT location_id, location_name FROM locations WHERE location_archived_at IS NULL AND location_client_id = $client_id ORDER BY location_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $location_id_select = intval($row['location_id']);
                                $location_name = escapeHtml($row['location_name']);
                            ?>
                            <option <?php if ($location_id == $location_id_select) { echo "selected"; } ?> value="<?= $location_id_select ?>"><?= $location_name ?></option>

                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-ethernet"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="LAN, WAN, VOIP, Uplink" maxlength="200" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Guest WiFi, VoIP VLAN, Server LAN, WAN Uplink">
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-network">
                <div class="mb-3">
                    <label>VLAN</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <input type="text" class="form-control font-monospace" inputmode="numeric" name="vlan" placeholder="e.g. 20">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Network (CIDR) <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-network-wired"></i></span>
                        <input type="text" class="form-control font-monospace" name="network" placeholder="192.168.1.0/24 or 2001:db8::/64" maxlength="200" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Assignable IP Range</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-arrows-alt-h"></i></span>
                        <input type="text" class="form-control font-monospace" name="dhcp_range" placeholder="192.168.1.100-192.168.1.200"  maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Gateway</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-route"></i></span>
                        <input type="text" class="form-control font-monospace" name="gateway" placeholder="192.168.1.1 or 2001:db8::1" maxlength="200">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-dns">
                <div class="mb-3">
                    <label>Primary DNS</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control font-monospace" name="primary_dns" placeholder="9.9.9.9 or 2620:fe::fe"  maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Secondary DNS</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control font-monospace" name="secondary_dns" placeholder="1.1.1.1 or 2606:4700:4700::1111"  maxlength="200">
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-notes">
                <div class="mb-3">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"></textarea>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_network" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
