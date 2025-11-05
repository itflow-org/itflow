<div class="modal" id="deleteClientModal<?php echo $client_id; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="mb-4" style="text-align: center;">
                    <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
                    <h2>Are you really, really, really sure?</h2>
                    <h6 class="mb-4 text-secondary">Do you really want to <b>delete <i><?php echo $client_name; ?></i> and ALL associated data</b>? This includes <i><?php echo $client_name; ?></i>'s documents, tickets, files, payments, invoices, logs, etc.<br>See <a href="https://forum.itflow.org/d/1147-deleting-a-client-deletes-payments" target="_blank">this</a> forum post.<br><br>This process cannot be undone.</h6>
                    <div class="form-group">
                        <input type="hidden" id="clientName<?php echo $client_id ?>" value="<?php echo $client_name; ?>">
                        <input class="form-control" type="text" id="clientNameProvided<?php echo $client_id ?>" onkeyup="validateClientNameDelete(<?php echo $client_id ?>)" placeholder="Type '<?php echo $client_name; ?>' to confirm data deletion">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-danger btn-lg px-5 disabled" id="clientDeleteButton<?php echo $client_id ?>" href="post.php?delete_client=<?php echo $client_id; ?>&csrf_token=<?php echo $_SESSION['csrf_token'] ?>">Yes, Delete!</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/agent/js/client_delete_confirm.js"></script>
