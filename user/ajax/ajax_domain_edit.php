<?php

require_once '../includes/ajax_header.php';

$domain_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM domains WHERE domain_id = $domain_id LIMIT 1");
                     
$row = mysqli_fetch_array($sql);
$domain_name = nullable_htmlentities($row['domain_name']);
$domain_description = nullable_htmlentities($row['domain_description']);
$domain_expire = nullable_htmlentities($row['domain_expire']);
$domain_registrar = intval($row['domain_registrar']);
$domain_webhost = intval($row['domain_webhost']);
$domain_dnshost = intval($row['domain_dnshost']);
$domain_mailhost = intval($row['domain_mailhost']);
$domain_ip = nullable_htmlentities($row['domain_ip']);
$domain_name_servers = nullable_htmlentities($row['domain_name_servers']);
$domain_mail_servers = nullable_htmlentities($row['domain_mail_servers']);
$domain_txt = nullable_htmlentities($row['domain_txt']);
$domain_raw_whois = nullable_htmlentities($row['domain_raw_whois']);
$domain_notes = nullable_htmlentities($row['domain_notes']);
$domain_created_at = nullable_htmlentities($row['domain_created_at']);
$domain_archived_at = nullable_htmlentities($row['domain_archived_at']);
$client_id = intval($row['domain_client_id']);

$history_sql = mysqli_query($mysqli, "SELECT * FROM domain_history WHERE domain_history_domain_id = $domain_id");

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe mr-2"></i>Editing domain: <span class="text-bold"><?php echo $domain_name; ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body bg-white">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pills-overview<?php echo $domain_id; ?>">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pills-records<?php echo $domain_id; ?>">Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNotes<?php echo $domain_id; ?>">Notes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditHistory<?php echo $domain_id; ?>">History</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-overview<?php echo $domain_id; ?>">

                <div class="form-group">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Domain name example.com" maxlength="200" value="<?php echo $domain_name; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?php echo $domain_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Domain Registrar</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <select class="form-control select2" name="registrar">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_registrar == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php 
                            }
                            ?>
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
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_webhost == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php 
                            }
                            ?>
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
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_dnshost == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php 
                            }
                            ?>
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
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_array($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_mailhost == $vendor_id) { echo "selected"; } ?> value="<?php echo $vendor_id; ?>"><?php echo $vendor_name; ?></option>
                            <?php 
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Expire Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?php echo $domain_expire; ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-records<?php echo $domain_id; ?>">

                <div class="form-group">
                    <label>Domain IP(s)</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="domain_ip" disabled><?php echo $domain_ip; ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Name Servers</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-crown"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="name_servers" disabled><?php echo $domain_name_servers; ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>MX Records</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-mail-bulk"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="mail_servers" disabled><?php echo $domain_mail_servers; ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>TXT Records</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-check-double"></i></span>
                        </div>
                        <textarea class="form-control" rows="1" name="txt_records" disabled><?php echo $domain_txt; ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label>Raw WHOIS</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-search-plus"></i></span>
                        </div>
                        <textarea class="form-control" rows="6" name="raw_whois" disabled><?php echo $domain_raw_whois; ?></textarea>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes<?php echo $domain_id; ?>">
                <div class="form-group">
                    <textarea class="form-control" name="notes" rows="12" placeholder="Enter some notes"><?php echo $domain_notes; ?></textarea>
                </div>
            </div>

            <div class="tab-pane fade" id="pillsEditHistory<?php echo $domain_id; ?>">
                <div class="table-responsive">
                    <table class='table table-sm table-striped border table-hover'>
                        <thead class='thead-dark'>
                            <tr>
                                <th>Date</th>
                                <th>Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                                while ($row = mysqli_fetch_array($history_sql)) { 
                                $domain_modified_at = nullable_htmlentities($row['domain_history_modified_at']);
                                $domain_field = nullable_htmlentities($row['domain_history_column']);
                                $domain_before_value = nullable_htmlentities($row['domain_history_old_value']);
                                $domain_after_value = nullable_htmlentities($row['domain_history_new_value']);
                            ?>
                            <tr>
                                <td><?php echo $domain_modified_at; ?></td>
                                <td><?php echo $domain_field; ?></td>
                                <td><?php echo $domain_before_value; ?></td>
                                <td><?php echo $domain_after_value; ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer bg-white">
        <button type="submit" name="edit_domain" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once "../includes/ajax_footer.php";
