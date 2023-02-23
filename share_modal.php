<script>

    function populateShareModal(client_id, item_type, item_ref_id) {
        document.getElementById("share_client_id").value = client_id;
        document.getElementById("share_item_type").value = item_type;
        document.getElementById("share_item_ref_id").value = item_ref_id;
    }

    function generateShareLink() {
        let client_id = document.getElementById("share_client_id").value;
        let item_type = document.getElementById("share_item_type").value;
        let item_ref_id = document.getElementById("share_item_ref_id").value;
        let item_note = document.getElementById("share_note").value;
        let item_views = document.getElementById("share_views").value;
        let item_expires = document.getElementById("share_expires").value;

        // Check values are provided
        if (item_views && item_expires && item_note) {
            // Send a GET request to ajax.php as ajax.php?share_generate_link=true....
            jQuery.get(
                "ajax.php",
                {share_generate_link: 'true', client_id: client_id, type: item_type, id: item_ref_id, note: item_note ,views: item_views, expires: item_expires},
                function(data) {

                    // If we get a response from ajax.php, parse it as JSON
                    const response = JSON.parse(data);

                    document.getElementById("share_link_header").hidden = false;
                    document.getElementById("share_link").hidden = false;
                    document.getElementById("share_link").value = response;

                    // Copy link to clipboard
                    navigator.clipboard.writeText(response);
                }
            );
        }
    }

</script>

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

                    <h3 id="share_link_header" hidden>Share URL:</h3>
                    <input type="text" class="form-control" disabled id="share_link" hidden value="">

                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-primary text-bold" onclick="event.preventDefault(); generateShareLink()"><i class="fas fa-check mr-2"></i>Generate</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
