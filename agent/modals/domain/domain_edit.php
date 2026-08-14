<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$domain_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT domain_archived_at, domain_client_id, domain_created_at, domain_description,
    domain_dnshost, domain_expire, domain_ip, domain_mail_servers, domain_mailhost,
    domain_name, domain_name_servers, domain_notes, domain_raw_whois, domain_registrar,
    domain_txt, domain_webhost FROM domains WHERE domain_id = $domain_id LIMIT 1");

$row = mysqli_fetch_assoc($sql);
$domain_name = escapeHtml($row['domain_name']);
$domain_description = escapeHtml($row['domain_description']);
$domain_expire = escapeHtml($row['domain_expire']);
$domain_registrar = intval($row['domain_registrar']);
$domain_webhost = intval($row['domain_webhost']);
$domain_dnshost = intval($row['domain_dnshost']);
$domain_mailhost = intval($row['domain_mailhost']);
$domain_ip = escapeHtml($row['domain_ip']);
$domain_name_servers = escapeHtml($row['domain_name_servers']);
$domain_mail_servers = escapeHtml($row['domain_mail_servers']);
$domain_txt = escapeHtml($row['domain_txt']);
$domain_raw_whois = escapeHtml($row['domain_raw_whois']);
$domain_notes = escapeHtml($row['domain_notes']);
$domain_created_at = escapeHtml($row['domain_created_at']);
$domain_archived_at = escapeHtml($row['domain_archived_at']);
$client_id = intval($row['domain_client_id']);

$history_sql = mysqli_query($mysqli, "SELECT domain_history_column, domain_history_modified_at, domain_history_new_value,
    domain_history_old_value FROM domain_history WHERE domain_history_domain_id = $domain_id");

enforceClientAccess();

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-globe me-2"></i>Editing domain: <span class="text-bold"><?= $domain_name ?></span></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="domain_id" value="<?= $domain_id ?>">

    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-overview<?= $domain_id ?>">Overview</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-records<?= $domain_id ?>">Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsEditNotes<?= $domain_id ?>">Notes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pillsEditHistory<?= $domain_id ?>">History</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pills-overview<?= $domain_id ?>">

                <div class="mb-3">
                    <label>Domain Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Domain name example.com" maxlength="200" value="<?= $domain_name ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?= $domain_description ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Domain Registrar</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-control select2" name="registrar">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_registrar == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Webhost</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-control select2" name="webhost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_webhost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>DNS Host</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        <select class="form-control select2" name="dnshost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_dnshost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Mail Host</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        <select class="form-control select2" name="mailhost">
                            <option value="">- Select Vendor -</option>
                            <?php
                            $vendor_sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_client_id = $client_id AND vendor_archived_at IS NULL ORDER BY vendor_name ASC");
                                while ($row = mysqli_fetch_assoc($vendor_sql)) {
                                    $vendor_id = $row['vendor_id'];
                                    $vendor_name = $row['vendor_name'];
                                ?>
                                <option <?php if ($domain_mailhost == $vendor_id) { echo "selected"; } ?> value="<?= $vendor_id ?>"><?= $vendor_name ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Expire Date</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= $domain_expire ?>">
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pills-records<?= $domain_id ?>">

                <div class="mb-3">
                    <label>Domain IP(s)</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-project-diagram"></i></span>
                        <textarea class="form-control" rows="1" name="domain_ip" disabled><?= $domain_ip ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Name Servers</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-crown"></i></span>
                        <textarea class="form-control" rows="1" name="name_servers" disabled><?= $domain_name_servers ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label>MX Records</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-mail-bulk"></i></span>
                        <textarea class="form-control" rows="1" name="mail_servers" disabled><?= $domain_mail_servers ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label>TXT Records</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-check-double"></i></span>
                        <textarea class="form-control" rows="1" name="txt_records" disabled><?= $domain_txt ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Raw WHOIS</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-search-plus"></i></span>
                        <textarea class="form-control" rows="6" name="raw_whois" disabled><?= $domain_raw_whois ?></textarea>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes<?= $domain_id ?>">
                <div class="mb-3">
                    <textarea class="form-control" name="notes" rows="12" placeholder="Enter some notes"><?= $domain_notes ?></textarea>
                </div>
            </div>

            <div class="tab-pane fade" id="pillsEditHistory<?= $domain_id ?>">
                <div class="table-responsive">
                    <table class='table table-sm table-striped border table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>Date</th>
                                <th>Field</th>
                                <th>Before</th>
                                <th>After</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                while ($row = mysqli_fetch_assoc($history_sql)) {
                                $domain_modified_at = escapeHtml($row['domain_history_modified_at']);
                                $domain_field = escapeHtml($row['domain_history_column']);
                                $domain_before_value = escapeHtml($row['domain_history_old_value']);
                                $domain_after_value = escapeHtml($row['domain_history_new_value']);
                            ?>
                            <tr>
                                <td><?= $domain_modified_at ?></td>
                                <td><?= $domain_field ?></td>
                                <td><?= $domain_before_value ?></td>
                                <td><?= $domain_after_value ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_domain" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
