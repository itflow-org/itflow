<script src="js/file_delete_modal.js"></script>
<div class="modal" id="deleteFileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="mb-4" style="text-align: center;">
                    <i class="far fa-10x fa-times-circle text-danger mb-3 mt-3"></i>
                    <h2>Are you sure?</h2>
                    <h6 class="mb-4 text-secondary">Do you really want to delete this file?</h6>
                    <h5 class="mb-4 text-secondary text-bold" id="file_delete_name">Name</h5>
                    <form action="post.php" method="POST">
                        <input type="hidden" name="file_id" id="file_delete_id" value="id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                        <button type="button" name="cancel" class="btn btn-outline-secondary btn-lg px-5 mr-4" data-dismiss="modal">Cancel</button>
                        <input type="submit" name="delete_file" class="btn btn-danger btn-lg px-5" value="Yes, Delete!">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
