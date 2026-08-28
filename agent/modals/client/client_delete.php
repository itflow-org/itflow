<div class="modal" id="deleteClientModal<?= $client_id ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="mb-4" style="text-align: center;">
                    <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
                    <h2>Are you really, really, really sure?</h2>
                    <h6 class="mb-4 text-secondary">Do you really want to <b>delete <i><?= $client_name ?></i> and ALL associated data</b>? This includes <i><?= $client_name ?></i>'s documents, tickets, files, payments, invoices, logs, etc.<br>See <a href="https://forum.itflow.org/d/1147-deleting-a-client-deletes-payments" target="_blank">this</a> forum post.<br><br>This process cannot be undone.</h6>
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
<?php /* defer, because this include is not at the end of the page.
         inc_client_top_head.php requires this file, so on EVERY client-context
         page this <script> sits in the middle of the body, between the client
         header card and the page's own content. Without defer the HTML parser
         stops here until the file arrives - and it arrives late, queued behind
         the whole footer bundle the speculative preload scanner has already
         started fetching - so the chrome paints and the content area below
         stays blank for the duration. Measured on vendors.php?client_id=1:
         document complete at 105ms, this script at 175ms, content painted at
         219ms. That ~100ms hole is the flash.

         defer is safe here: validateClientNameDelete() is only ever called
         from the onkeyup handler above, and a deferred script is guaranteed to
         have run before DOMContentLoaded, long before anyone can type. */ ?>
<script src="/agent/js/client_delete_confirm.js" defer></script>
