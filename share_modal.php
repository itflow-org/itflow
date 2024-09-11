<script src="js/share_modal.js"></script>

<div class="modal" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-share mr-2"></i>Share Link</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="ajax.php" method="GET" id="newShareLink">
                <input type="hidden" name="client_id" id="share_client_id" value="">
                <input type="hidden" name="item_type" id="share_item_type" value="">
                <input type="hidden" name="item_ref_id" id="share_item_ref_id" value="">
                <div class="modal-body bg-white">

                    <div id="div_share_link_form">

                        <label>Views / Expire <strong class="text-danger">*</strong> <small class="text-secondary">Enter 0 for unlimited</small></label>
                        <div class="form-row">
                            <div class="col-4">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="views" id="share_views" placeholder="Views before link expires" value="1" required>
                                </div>
                            </div>
                            <div class="col-8">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <select class="form-control" name="expires" id="share_expires" required>
                                        <option value="30 MINUTE">30 Minutes</option>
                                        <option value="24 HOUR">24 Hours (1 Day)</option>
                                        <option value="72 HOUR">72 Hours (3 Days)</option>
                                        <option value="100 YEAR">NEVER</option>

                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Share with</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                </div>
                                <select class="form-control select2" name="contact_email" id="share_email">
                                    <option value="">-Select a contact-</option>
                                    <?php

                                    $sql_client_contacts_select = mysqli_query($mysqli, "SELECT * FROM contacts WHERE contact_client_id = $client_id AND contact_email <> '' AND contact_archived_at IS NULL ORDER BY contact_name ASC");
                                    while ($row = mysqli_fetch_array($sql_client_contacts_select)) {
                                        $contact_id_select = intval($row['contact_id']);
                                        $contact_name_select = nullable_htmlentities($row['contact_name']);
                                        $contact_email_select = nullable_htmlentities($row['contact_email']);
                                        ?>
                                        <option value="<?php echo $contact_email_select; ?>"><?php echo "$contact_name_select - $contact_email_select"; ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <textarea class="form-control" rows="4" name="note" id="share_note" placeholder="Client visible note"></textarea>
                        </div>

                        <hr>

                    </div>

                    <div id="div_share_link_output" hidden>
                        <h3 id="share_link_header">Share URL</h3>
                        <input type="text" class="form-control" disabled id="share_link" value="">
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" id="div_share_link_generate" class="btn btn-primary text-bold" onclick="event.preventDefault(); generateShareLink()"><i class="fas fa-paper-plane mr-2"></i>Send and Show Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

