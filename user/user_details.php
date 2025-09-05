<?php
require_once "includes/inc_all_user.php";

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cog mr-2"></i>User Preferences</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="row">
                <div class="col-md-3 text-center">
                    <?php if($session_avatar) { ?>
                    <img class="img-thumbnail" src="<?php echo "../uploads/users/$session_user_id/" . nullable_htmlentities($session_avatar); ?>">
                    <a href="post.php?clear_your_user_avatar&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger btn-block">Remove Avatar</a>
                    <hr>
                    <?php } ?>
                    <div class="form-group">
                        <label>Upload Avatar</label>
                        <input type="file" class="form-control-file" accept="image/*" name="avatar">
                    </div>
                </div>
                <div class="col-md-9">

                    <div class="form-group">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo stripslashes(nullable_htmlentities($session_name)); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                            </div>
                            <input type="text" class="form-control" value="<?php echo nullable_htmlentities($session_user_role_display); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            </div>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo nullable_htmlentities($session_email); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Signature</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-pen"></i></span>
                            </div>
                            <textarea class="form-control" name="signature" rows="4" placeholder="Create a signature automatically appended to tickets, emails etc"><?php echo getFieldById('user_settings',$session_user_id,'user_config_signature','html'); ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="edit_your_user_details" class="btn btn-primary btn-responsive"><i class="fas fa-check mr-2"></i>Save</button>

                </div>
            </div>

        </form>
                
    </div>

</div>

<?php
require_once "../includes/footer.php";
