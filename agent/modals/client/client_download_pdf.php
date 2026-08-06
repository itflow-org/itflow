<div class="modal" id="exportClientPDFModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-file-pdf mr-2"></i>Export PDF</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
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
                    <button type="submit" name="export_client_pdf" class="btn btn-primary text-bold"><i class="fa fa-fw fa-download mr-2"></i>Export</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
