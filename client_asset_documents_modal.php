<div class="modal" id="assetDocumentsModal<?php echo $asset_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-<?php echo $device_icon; ?> mr-2"></i><?php echo $asset_name; ?> Documents</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body bg-white">
                <?php
                while ($row = mysqli_fetch_array($sql_related_documents)) {
                    $related_document_id = intval($row['document_id']);
                    $related_document_name = nullable_htmlentities($row['document_name']);
                    ?>
                    <p>
                        <i class="fas fa-fw fa-document text-secondary"></i>
                        <?php echo $related_document_name; ?> <a href="client_documents.php?q=<?php echo $related_document_name; ?>"><?php echo $related_document_name; ?></a>
                    </p>
                <?php } ?>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>

        </div>
    </div>
</div>
