<div class="modal" id="bulkTransferAssetClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-exchange mr-2"></i>Transferring Asset(s)</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <div class="form-group">
                    <label>New Client <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                        </div>
                        <select class="form-control select2" name="bulk_client_id">
                            <option value="">- Client -</option>
                            <?php
                                $clients_sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_id != $client_id AND client_archived_at IS NULL");
                        
                                while ($row = mysqli_fetch_array($clients_sql)) {
                                    $client_id_select = intval($row["client_id"]);
                                    $client_name_select = nullable_htmlentities($row["client_name"]);
                                ?>
                                <option value='<?php echo $client_id_select; ?>'><?php echo $client_name_select; ?></option>
                                <?php
                                }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="alert alert-dark" role="alert">
                    <i>The current asset will be archived and content copied to a new asset.</i>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" name="bulk_transfer_client_asset" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Transfer</button>
                <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
            </div>
        </div>
    </div>
</div>
