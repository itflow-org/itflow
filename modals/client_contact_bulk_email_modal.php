<div class="modal" id="bulkSendEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-envelope-open mr-2"></i>Bulk Send Email</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">

                <div class="form-group">
                    <select type="text" class="form-control select2" name="mail_from">
                        <option value="<?php echo nullable_htmlentities($config_mail_from_email); ?>">
                            <?php echo nullable_htmlentities("$config_mail_from_name - $config_mail_from_email"); ?></option>
                        <option value="<?php echo nullable_htmlentities($config_invoice_from_email); ?>">
                            <?php echo nullable_htmlentities("$config_invoice_from_name - $config_invoice_from_email"); ?></option>
                        <option value="<?php echo nullable_htmlentities($config_quote_from_email); ?>">
                            <?php echo nullable_htmlentities("$config_quote_from_name - $config_quote_from_email"); ?></option>
                        <option value="<?php echo nullable_htmlentities($config_ticket_from_email); ?>">
                            <?php echo nullable_htmlentities("$config_ticket_from_name - $config_ticket_from_email"); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="mail_from_name" placeholder="From Name" maxlength="255"
                        value="<?php echo nullable_htmlentities($config_mail_from_name); ?>">
                </div>

                <div class="form-group">
                    <input type="text" class="form-control" name="subject" placeholder="Subject" maxlength="255">
                </div>

                <div class="form-group">
                    <textarea class="form-control tinymce" name="body"
                        placeholder="Type an email in here"></textarea>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        </div>
                        <input type="datetime-local" class="form-control" name="queued_at">
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-white">
                <button type="submit" name="send_bulk_mail_now" class="btn btn-primary text-bold"><i class="fas fa-paper-plane mr-2"></i>Send</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>