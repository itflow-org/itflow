<script src="js/share_modal.js"></script>

<div class="modal" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-share mr-2"></i>Generate Share Link</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="ajax.php" method="GET" id="newShareLink">
                <input type="hidden" name="client_id" id="share_client_id"  value="">
                <input type="hidden" name="item_type" id="share_item_type"  value="">
                <input type="hidden" name="item_ref_id" id="share_item_ref_id"  value="">
                <div class="modal-body bg-white">

                    <div id="div_share_link_form">

                        <label>Views / Expire <strong class="text-danger">*</strong></label>
                        <div class="form-row">
                            <div class="col-4">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="views" id="share_views" placeholder="Views before link expires" value="1" required>
                                </div>
                            </div>
                            <div class="col-8">
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    </div>
                                    <input type="datetime-local" class="form-control" name="expires" id="share_expires" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <textarea class="form-control" rows="4" name="note" id="share_note" placeholder="Client visible note (required)" required></textarea>
                        </div>

                        <p><i>Note: Logins are shared "as is" and will not update</i></p>

                        <hr>

                    </div>

                    <div id="div_share_link_output" hidden>
                        <h3 id="share_link_header">Share URL</h3>
                        <input type="text" class="form-control" disabled id="share_link" value="">
                    </div>

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" id="div_share_link_generate" class="btn btn-primary text-bold" onclick="event.preventDefault(); generateShareLink()"><i class="fas fa-check mr-2"></i>Generate</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
