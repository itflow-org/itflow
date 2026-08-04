<div class="modal" id="quoteNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white">
                    <i class="fas fa-fw fa-edit mr-2"></i>Quote Notes
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <textarea class="form-control" rows="8" name="note" placeholder="Enter some notes"><?= $quote_note ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="quote_note" class="btn btn-primary text-bold">
                        <i class="fas fa-check mr-2"></i>Save
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
