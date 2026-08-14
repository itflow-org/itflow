<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe me-2"></i>New Domain</h5>
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

                <div class="mb-3">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="name" id="domain_name" placeholder="example.com" maxlength="200" required autofocus onfocusout="checkApexDomain()">
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="domain_check_info"></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Short Description">
                    </div>
                </div>

                <?php if ($client_id) { ?>
                <div class="mb-3">
                    <label>Registrar</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-select select2" name="registrar">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Webhost</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-select select2" name="webhost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>DNS Host</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-select select2" name="dnshost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Mail Host</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <select class="form-select select2" name="mailhost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_assoc($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = escapeHtml($row['vendor_name']);
                                ?>
                                <option value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="mb-3">
                    <label>Expire Date</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        <input type="date" class="form-control" name="expire" max="2999-12-31">
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
        <button type="submit" name="add_domain" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Checks domains aren't sub-domains (99%)
    function checkApexDomain() {
        var domain = document.getElementById("domain_name").value;
        //Send a GET request to ajax.php as ajax.php?apex_domain_check=true&domain=domain
        itflowGet(
            "ajax.php",
            {apex_domain_check: 'true', domain: domain},
            function(data) {
                //If we get a response from ajax.php, parse it as JSON
                const domain_check_data = JSON.parse(data);
                document.getElementById("domain_check_info").innerHTML = domain_check_data.message;
            }
        );
    }
</script>

<?php

require_once '../../../includes/modal_footer.php';
