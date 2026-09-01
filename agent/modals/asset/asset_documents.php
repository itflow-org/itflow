<div class="modal" id="assetDocumentsModal<?= $asset_id ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-<?= $device_icon ?> me-2"></i><?= $asset_name ?> Documents</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <?php
                while ($row = mysqli_fetch_assoc($sql_related_documents)) {
                    $related_document_id = intval($row['document_id']);
                    $related_document_name = escapeHtml($row['document_name']);
                    ?>
                    <p>
                        <i class="fas fa-fw fa-document text-secondary"></i>
                        <?= $related_document_name ?> <a href="client_documents.php?q=<?= $related_document_name ?>"><?= $related_document_name ?></a>
                    </p>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
