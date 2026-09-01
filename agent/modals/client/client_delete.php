<div class="modal" id="deleteClientModal<?= $client_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="mb-4" style="text-align: center;">
                    <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
                    <h2>Are you really, really, really sure?</h2>
                    <br>
                    <h6 class="mb-4 text-secondary"><b><u>DANGER</u></b><br> Do you really want to <b>delete <i><?= $client_name ?></i> and ALL associated data</b>? This includes <i><?= $client_name ?></i>'s documents, tickets, files, payments, invoices, logs, etc.
                    <br><br>
                    <?php if ($config_module_enable_accounting) { ?>                   
                        <div class="alert alert-danger" role="alert">
                            Deleting this client will also delete all associated accounting data. See <a href="https://forum.itflow.org/d/1147-deleting-a-client-deletes-payments" target="_blank">this</a> forum post for why that may be a bad idea.
                        </div>
                    <?php } ?>
                    <u>This process cannot be undone.</u></h6>
                    <div class="mb-3">
                        <input type="hidden" id="clientName<?= $client_id ?>" value="<?= $client_name ?>">
                        <input class="form-control" type="text" id="clientNameProvided<?= $client_id ?>" onkeyup="validateClientNameDelete(<?= $client_id ?>)" placeholder="Type '<?= $client_name ?>' to confirm data deletion">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-lg px-5 me-4" data-bs-dismiss="modal">Cancel</button>
                    <a class="btn btn-danger btn-lg px-5 disabled" id="clientDeleteButton<?= $client_id ?>" href="post.php?delete_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">Yes, Delete!</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /* defer is safe here */ ?>
<script src="/agent/js/client_delete_confirm.js" defer></script>
