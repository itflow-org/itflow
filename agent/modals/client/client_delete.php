<div class="modal" id="deleteClientModal<?= $client_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-trash me-2"></i>Delete <strong><?= $client_name ?></strong></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong><?= $client_name ?></strong> goes for good, with everything that is theirs - documents, tickets, files, payments, invoices, logs and the rest. This can't be undone; to keep their history, archive them instead.
                </div>
                <?php if ($config_module_enable_accounting) { ?>
                    <div class="alert alert-warning">
                        Their payments and the rest of their accounting go too - see <a href="https://forum.itflow.org/d/1147-deleting-a-client-deletes-payments" target="_blank" rel="noopener">why that may be a bad idea</a>.
                    </div>
                <?php } ?>
                <label class="form-label" for="clientNameProvided<?= $client_id ?>">Type <strong><?= $client_name ?></strong> to confirm</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-keyboard"></i></span>
                    <input type="hidden" id="clientName<?= $client_id ?>" value="<?= $client_name ?>">
                    <input class="form-control" type="text" id="clientNameProvided<?= $client_id ?>" oninput="validateClientNameDelete(<?= $client_id ?>)" placeholder="<?= $client_name ?>" autocomplete="off">
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-danger fw-bold disabled" id="clientDeleteButton<?= $client_id ?>" href="post.php?delete_client=<?= $client_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>"><i class="fas fa-trash me-2"></i>Delete</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
<?php /* defer is safe here */ ?>
<script src="<?= staticFileUrl('/agent/js/client_delete_confirm.js') ?>" defer></script>
