<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe mr-2"></i>New Domain</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-details">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-notes">Notes</a>
            </li>
            
        </ul>

        <hr>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-details">

                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php } else { ?>

                    <div class="form-group">
                        <label>Client <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client_id" required>
                                <option value="">- Select Client -</option>
                                <?php

                                $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL $access_permission_query ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $client_id_select = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']); ?>
                                    <option <?php if ($client_id == $client_id_select) { echo "selected"; } ?> value="<?php echo $client_id_select; ?>"><?php echo $client_name; ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="form-group">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" id="domain_name" placeholder="example.com" maxlength="200" required autofocus onfocusout="domain_check()">
                    </div>
                    <div class="mt-2">
                        <span class="text-info" id="domain_check_info"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description">
                    </div>
                </div>

                <?php if ($client_id) { ?>
                <div class="form-group">
                    <label>Registrar</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" name="registrar">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = nullable_htmlentities($row['vendor_name']);
                                ?>
                                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Webhost</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" name="webhost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = nullable_htmlentities($row['vendor_name']);
                                ?>
                                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>DNS Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" name="dnshost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = nullable_htmlentities($row['vendor_name']);
                                ?>
                                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mail Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <select class="form-control select2" name="mailhost">
                            <option value="">- Vendor -</option>
                            <?php

                            $sql = mysqli_query($mysqli, "SELECT * FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id ORDER BY vendor_name ASC");
                            while ($row = mysqli_fetch_array($sql)) {
                                $vendor_id = intval($row['vendor_id']);
                                $vendor_name = nullable_htmlentities($row['vendor_name']);
                                ?>
                                <option value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <?php } ?>

                <div class="form-group">
                    <label>Expire Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" max="2999-12-31">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-notes">
                <div class="form-group">
                    <textarea class="form-control" rows="12" placeholder="Enter some notes" name="notes"></textarea>
                </div>
            </div>

        </div>
    
    </div>

    <div class="modal-footer">
        <button type="submit" name="add_domain" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<script>
    // Checks domains aren't sub-domains (99%)
    function domain_check() {
        var domain = document.getElementById("domain_name").value;
        //Send a GET request to ajax.php as ajax.php?apex_domain_check=true&domain=domain
        jQuery.get(
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
