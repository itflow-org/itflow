<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-white"><i class="fa fa-fw fa-stream me-2"></i>New Service</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">
        <?php if ($client_id) { ?>
        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-overview">Overview</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-general">General</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-assets">Assets</a>
            </li>

        </ul>

        <hr>

        <?php } ?>

        <div class="tab-content">

            <!-- //TODO: The multiple selects won't play nicely with the icons or just general formatting. I've just added blank <p> tags to format it better for now -->

            <div class="tab-pane fade show active" id="pills-overview">

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
                                    $client_id = intval($row['client_id']);
                                    $client_name = escapeHtml($row['client_name']); ?>
                                    <option <?php if ($client_id == isset($_GET['client'])) { echo "selected"; } ?> value="<?= $client_id ?>"><?= $client_name ?></option>

                                <?php } ?>
                            </select>
                        </div>
                    </div>

                <?php } ?>

                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-stream"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="Name of Service" maxlength="200" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                        <input type="text" class="form-control" name="description" placeholder="Description of Service" maxlength="200" required>
                    </div>
                </div>

                <!--   //TODO: Integrate with company wide categories: /categories.php  -->
                <div class="mb-3">
                    <label>Category</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-info"></i></span>
                        <input type="text" class="form-control" name="category" placeholder="Category" maxlength="20">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Importance</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        <select class="form-select select2" name="importance" required>
                            <option>Low</option>
                            <option>Medium</option>
                            <option>High</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Backup</label>
                    <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-hdd"></i></span>
                        <input type="text" class="form-control" name="backup" placeholder="Backup strategy" maxlength="200">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Notes</label>
                    <textarea class="form-control" rows="3" placeholder="Enter some notes" name="note"></textarea>
                </div>
            </div>

            <?php if ($client_id) { ?>

            <div class="tab-pane fade" id="pills-general">
                <div class="mb-3">
                    <label for="contacts">Select related Contacts</label>
                    <select class="form-select select2" id="contacts" name="contacts[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name FROM contacts WHERE contact_archived_at IS NULL AND contact_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $contact_id = intval($row['contact_id']);
                            $contact_name = escapeHtml($row['contact_name']);
                            echo "<option value=\"$contact_id\">$contact_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="vendors">Select related vendors</label>
                    <select class="form-select select2" id="vendors" name="vendors[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_archived_at IS NULL AND vendor_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $vendor_id = intval($row['vendor_id']);
                            $vendor_name = escapeHtml($row['vendor_name']);
                            echo "<option value=\"$vendor_id\">$vendor_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="documents">Select related documents</label>
                    <select class="form-select select2" id="documents" name="documents[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT document_id, document_name FROM documents WHERE document_archived_at IS NULL AND document_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $document_id = intval($row['document_id']);
                            $document_name = escapeHtml($row['document_name']);
                            echo "<option value=\"$document_id\">$document_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- TODO: Services related to other services -->

            </div>

            <div class="tab-pane fade" id="pills-assets">

                <div class="mb-3">
                    <label for="assets">Select related assets</label>
                    <select class="form-select select2" id="assets" name="assets[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name FROM assets WHERE asset_archived_at IS NULL AND asset_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $asset_id = intval($row['asset_id']);
                            $asset_name = escapeHtml($row['asset_name']);
                            echo "<option value=\"$asset_id\">$asset_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="credentials">Select related Credentials</label>
                    <select class="form-select select2" id="credentials" name="credentials[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT credential_id, credential_name FROM credentials WHERE credential_archived_at IS NULL AND credential_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $credential_id = intval($row['credential_id']);
                            $credential_name = escapeHtml($row['credential_name']);
                            echo "<option value=\"$credential_id\">$credential_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="domains">Select related domains</label>
                    <select class="form-select select2" id="domains" name="domains[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains WHERE domain_archived_at IS NULL AND domain_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $domain_id = intval($row['domain_id']);
                            $domain_name = escapeHtml($row['domain_name']);
                            echo "<option value=\"$domain_id\">$domain_name</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="certificates">Select related certificates</label>
                    <select class="form-select select2" id="certificates" name="certificates[]" multiple>
                        <?php
                        $sql = mysqli_query($mysqli, "SELECT certificate_domain, certificate_id, certificate_name FROM certificates WHERE certificate_archived_at IS NULL AND certificate_client_id = $client_id");
                        while ($row = mysqli_fetch_assoc($sql)) {
                            $cert_id = intval($row['certificate_id']);
                            $cert_name = escapeHtml($row['certificate_name']);
                            $cert_domain = escapeHtml($row['certificate_domain']);
                            echo "<option value=\"$cert_id\">$cert_name ($cert_domain)</option>";
                        }
                        ?>
                    </select>
                </div>

            </div>
            <?php } ?>

        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="add_service" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php

require_once '../../../includes/modal_footer.php';
