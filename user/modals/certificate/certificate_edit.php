<?php

require_once '../../../includes/modal_header.php';

$certificate_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM certificates WHERE certificate_id = $certificate_id LIMIT 1");

$row = mysqli_fetch_array($sql);
$certificate_name = nullable_htmlentities($row['certificate_name']);
$certificate_description = nullable_htmlentities($row['certificate_description']);
$certificate_domain = nullable_htmlentities($row['certificate_domain']);
$certificate_domain_id = intval($row['certificate_domain_id']);
$certificate_issued_by = nullable_htmlentities($row['certificate_issued_by']);
$certificate_public_key = nullable_htmlentities($row['certificate_public_key']);
$certificate_notes = nullable_htmlentities($row['certificate_notes']);
$certificate_expire = nullable_htmlentities($row['certificate_expire']);
$certificate_created_at = nullable_htmlentities($row['certificate_created_at']);
$client_id = intval($row['certificate_client_id']);

$history_sql = mysqli_query($mysqli, "SELECT * FROM certificate_history WHERE certificate_history_certificate_id = $certificate_id");

// Generate the HTML form content using output buffering.
ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>Editing certificate: <span class="text-bold"><?php echo $certificate_name; ?></span></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="certificate_id" value="<?php echo $certificate_id; ?>">
    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="pill" href="#pillsEditDetails<?php echo $certificate_id; ?>">Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditCertificate<?php echo $certificate_id; ?>">Certificate</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditNotes<?php echo $certificate_id; ?>">Notes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="pill" href="#pillsEditHistory<?php echo $certificate_id; ?>">History</a>
            </li>
        </ul>

        <hr>

        <div class="tab-content" <?php if (lookupUserPermission('module_support') <= 1) { echo 'inert'; } ?>>

            <div class="tab-pane fade show active" id="pillsEditDetails<?php echo $certificate_id; ?>">

                <div class="form-group">
                    <label>Certificate Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <input type="text" class="form-control" name="name" placeholder="Certificate name" maxlength="200" value="<?php echo $certificate_name; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-angle-right"></i></span>
                        </div>
                        <input type="text" class="form-control" name="description" placeholder="Short Description" value="<?php echo $certificate_description; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Domain</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                        </div>
                        <select class="form-control select2" name="domain_id">
                            <option value="">- Select Domain -</option>
                            <?php
                            $domains_sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains WHERE domain_client_id = $client_id");
                            while ($row = mysqli_fetch_array($domains_sql)) {
                                $domain_id = intval($row['domain_id']);
                                $domain_name = nullable_htmlentities($row['domain_name']);
                            ?>
                            <option value="<?php echo $domain_id; ?>" <?php if ($certificate_domain_id == $domain_id) { echo "selected"; } ?>><?php echo $domain_name; ?></option>

                            <?php } ?>
                        </select>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditCertificate<?php echo $certificate_id; ?>">

                <div class="form-group">
                    <label>Domain <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i>&nbsp;https://</span>
                        </div>
                        <input type="text" class="form-control" name="domain" id="editCertificateDomain" placeholder="Domain" maxlength="200" value="<?php echo $certificate_domain; ?>" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" onclick="fetchSSL('edit')"><i class="fas fa-fw fa-sync-alt"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Issued By</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                        </div>
                        <input type="text" class="form-control" name="issued_by" id="editCertificateIssuedBy" maxlength="200" placeholder="Issued By" value="<?php echo $certificate_issued_by; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Expire Date</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                        </div>
                        <input type="date" class="form-control" name="expire" id="editCertificateExpire" max="2999-12-31" value="<?php echo $certificate_expire; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Public Key </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <textarea class="form-control" rows="8" name="public_key" id="editCertificatePublicKey"><?php echo $certificate_public_key; ?></textarea>
                    </div>
                </div>

            </div>

            <div class="tab-pane fade" id="pillsEditNotes<?php echo $certificate_id; ?>">
                <div class="form-group">
                    <textarea class="form-control" name="notes" rows="12" placeholder="Enter some notes"><?php echo $certificate_notes; ?></textarea>
                </div>
            </div>

            <div class="tab-pane fade" id="pillsEditHistory<?php echo $certificate_id; ?>">
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
                            $certificate_modified_at = nullable_htmlentities($row['certificate_history_modified_at']);
                            $certificate_field = nullable_htmlentities($row['certificate_history_column']);
                            $certificate_before_value = nullable_htmlentities($row['certificate_history_old_value']);
                            $certificate_after_value = nullable_htmlentities($row['certificate_history_new_value']);
                            ?>
                            <tr>
                                <td><?php echo $certificate_modified_at; ?></td>
                                <td><?php echo $certificate_field; ?></td>
                                <td><?php echo $certificate_before_value; ?></td>
                                <td><?php echo $certificate_after_value; ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="edit_certificate" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
