<script>
    function populateShareModal(client_id, item_type, item_ref_id){
        document.getElementById("share_client_id").value = client_id;
        document.getElementById("share_item_type").value = item_type;
        document.getElementById("share_item_ref_id").value = item_ref_id;
    }

    function generateShareLink(){
        let client_id = document.getElementById("share_client_id").value;
        let item_type = document.getElementById("share_item_type").value;
        let item_ref_id = document.getElementById("share_item_ref_id").value;
        let item_note = document.getElementById("share_note").value;
        let item_views = document.getElementById("share_views").value;
        let item_expires = document.getElementById("share_expires").value;

        // Check values are provided
        if(item_views && item_expires && item_note){
            // Send a GET request to post.php as post.php?share_generate_link=true....
            jQuery.get(
                "post.php",
                {share_generate_link: 'true', client_id: client_id, type: item_type, id: item_ref_id, note: item_note ,views: item_views, expires: item_expires},
                function(data){

                    // If we get a response from post.php, parse it as JSON
                    const response = JSON.parse(data);

                    document.getElementById("share_link_header").hidden = false;
                    document.getElementById("share_link").hidden = false;
                    document.getElementById("share_link").value = response;
                }
            );
        }
    }
</script>
<div class="modal" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-fw fa-share"></i> Share</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body bg-white">

                <h2>Get Share URL</h2>
                <form action="post.php" method="GET" id="newShareLink">
                    <input type="hidden" name="client_id" id="share_client_id"  value="">
                    <input type="hidden" name="item_type" id="share_item_type"  value="">
                    <input type="hidden" name="item_ref_id" id="share_item_ref_id"  value="">
                    <div class="form-group">
                        <label for="views">Number of views allowed <strong class="text-danger">*</strong></label>
                        <input type="number" class="form-control" name="views" id="share_views" placeholder="Views before link expires" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="views">Link Expiry date <strong class="text-danger">*</strong></label>
                        <input type="datetime-local" class="form-control" name="expires" id="share_expires" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="note">Note <strong class="text-danger">*</strong></label>
                        <input type="text" class="form-control" name="note" id="share_note" placeholder="Client visible note" required autofocus>
                    </div>
                    <button class="form-control" onclick="event.preventDefault(); generateShareLink()">Share</button>
                </form>
                <p><i>Note: Login passwords are shared "as is" and will not update</i></p>

                <hr>

                <h3 id="share_link_header" hidden>Share URL:</h3>
                <input type="text" class="form-control" disabled id="share_link" hidden value="">

            </div>

            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>