<div class="modal" id="addCertificateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-lock mr-2"></i>New Certificate</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label>Certificate Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name"
                                placeholder="Certificate name" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Domain <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i>&nbsp;https://</span>
                            </div>
                            <input type="text" class="form-control" name="domain"
                                id="domain" placeholder="FQDN" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary"
                                    onclick="fetchSSL('new')"><i class="fas fa-fw fa-sync-alt"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Issued By </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                            </div>
                            <input type="text" class="form-control" name="issued_by" id="issuedBy"
                                placeholder="Issued By">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expire Date</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                            </div>
                            <input type="date" class="form-control" name="expire" id="expire" max="2999-12-31">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Public Key </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <textarea class="form-control" name="public_key" id="publicKey"
                                placeholder="-----BEGIN CERTIFICATE-----"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Domain</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                            </div>
                            <select class="form-control select2" name="domain_id">
                                <option value="">- Domain -</option>
                                <?php
                                $domains_sql = mysqli_query(
                                    $mysqli,
                                    "SELECT * FROM domains
                                    WHERE domain_archived_at IS NULL AND domain_client_id = $client_id
                                    ORDER BY domain_name ASC"
                                    );
                                while ($domain_row = mysqli_fetch_array($domains_sql)) {
                                    $domain_id = intval($domain_row['domain_id']);
                                    $domain_name = nullable_htmlentities($domain_row['domain_name']);
                                    echo "<option value=\"$domain_id\">$domain_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" name="add_certificate" class="btn btn-primary text-bold">
                        <i class="fa fa-check"></i> Create
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fa fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
