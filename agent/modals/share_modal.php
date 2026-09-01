<script src="js/share_modal.js"></script>

<div class="modal" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-share me-2"></i>Share Link</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="ajax.php" method="GET" id="newShareLink">
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="client_id" id="share_client_id" value="">
                <input type="hidden" name="item_type" id="share_item_type" value="">
                <input type="hidden" name="item_ref_id" id="share_item_ref_id" value="">
                <div class="modal-body">

                    <div id="div_share_link_form">

                        <div class="mb-3">
                            <label>Share with</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="contact_email" id="share_email" data-placeholder="Select or enter an Email">
                                    <option value=""></option>
                                    <?php

                                    $sql_client_contacts_select = mysqli_query($mysqli, "SELECT contact_email, contact_id, contact_name FROM contacts WHERE contact_client_id = $client_id AND contact_email <> '' AND contact_archived_at IS NULL ORDER BY contact_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql_client_contacts_select)) {
                                        $contact_id_select = intval($row['contact_id']);
                                        $contact_name_select = escapeHtml($row['contact_name']);
                                        $contact_email_select = escapeHtml($row['contact_email']);
                                        ?>
                                        <option value="<?= $contact_email_select ?>"><?= "$contact_name_select - $contact_email_select" ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <label>Expiration</label>
                        <div class="mb-3">
                            <div class="btn-group w-100" role="group">
                                <input class="btn-check" id="expires_opt0" type="radio" name="expires" value="1 HOUR" checked>
                                <label class="btn btn-outline-dark" for="expires_opt0">1 hour</label>
                                <input class="btn-check" id="expires_opt1" type="radio" name="expires" value="24 HOUR">
                                <label class="btn btn-outline-dark" for="expires_opt1">1 day</label>
                                <input class="btn-check" id="expires_opt2" type="radio" name="expires" value="168 HOUR">
                                <label class="btn btn-outline-dark" for="expires_opt2">1 week</label>
                                <input class="btn-check" id="expires_opt3" type="radio" name="expires" value="730 HOUR">
                                <label class="btn btn-outline-dark" for="expires_opt3">1 month</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="views" id="share_views" value="1">
                                <label class="form-check-label" for="share_views">Delete after view</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <textarea class="form-control" rows="6" name="note" id="share_note" placeholder="Client visible note" maxlength="255"></textarea>
                        </div>

                        <hr>

                    </div>

                    <div id="div_share_link_output" hidden>
                        <h3 id="share_link_header">Share URL</h3>
                        <input type="text" class="form-control" disabled id="share_link" value="">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" id="div_share_link_generate" class="btn btn-primary text-bold" onclick="event.preventDefault(); generateShareLink()"><i class="fas fa-paper-plane me-2"></i>Send and Show Link</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
