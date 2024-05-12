<?php
$key = randomString(156);
?>
<div class="modal" id="addApiKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-key mr-2"></i>New Key</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="key" value="<?php echo $key ?>">

                    <div class="form-group">
                        <label>API Key <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                            </div>
                            <input type="text" class="form-control" value="<?php echo $key ?>" required disabled>
                            <div class="input-group-append">
                                <button class="btn btn-default clipboardjs" type="button" data-clipboard-text="<?php echo $key; ?>"><i class="fa fa-fw fa-copy"></i></button>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Key Name" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Expiration Date <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="expire" min="<?php echo date('Y-m-d')?>" max="2999-12-31" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Client Access <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="client" required>
                                <option value="0"> ALL CLIENTS </option>
                                <?php
                                $sql = mysqli_query($mysqli, "SELECT * FROM clients ORDER BY client_name ASC");
                                while ($row = mysqli_fetch_array($sql)) {
                                    $client_id = intval($row['client_id']);
                                    $client_name = nullable_htmlentities($row['client_name']); ?>
                                    <option value="<?php echo $client_id; ?>"><?php echo "$client_name  (Client ID: $client_id)"; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="add_api_key" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
