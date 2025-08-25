<?php

require_once '../../includes/modal_header.php';

$type = intval($_GET['id']);

?>

<!-- <option value="1">Client Tag</option> -->
<!-- <option value="2">Location Tag</option> -->
<!-- <option value="3">Contact Tag</option> -->
<!-- <option value="4">Credential Tag</option> -->

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-tag mr-2"></i>New Tag</h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="type" value="<?php echo $type; ?>">
    <div class="modal-body">
        <div class="form-group">
            <div class="input-group">
                <input type="text" class="form-control" name="name" placeholder="Tag name" maxlength="200" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <div class="input-group">
                <input type="color" class="form-control col-3" name="color" required>
            </div>
        </div>

        <div class="form-group">
            <div class="input-group">
                <input type="text" class="form-control" name="icon" placeholder="Icon ex handshake">
            </div>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_tag" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../includes/modal_footer.php';
