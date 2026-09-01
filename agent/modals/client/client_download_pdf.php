<div class="modal" id="exportClientPDFModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-pdf me-2"></i>Export PDF</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off" target="_blank">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <div class="modal-body">
                    <ul class="list-group">
                        <?php renderClientPackSections(); ?>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="export_client_pdf" class="btn btn-primary text-bold"><i class="fa fa-fw fa-download me-2"></i>Export</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
