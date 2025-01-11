<?php
require_once "includes/inc_all_user.php";

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-user mr-2"></i>Your User Details</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <center class="mb-3 px-5">
                <?php if (empty($session_avatar)) { ?>
                    <i class="fas fa-user-circle fa-8x text-secondary"></i>
                <?php } else { ?>
                    <img alt="User avatar" src="<?php echo "uploads/users/$session_user_id/" . nullable_htmlentities($session_avatar); ?>" class="img-size-64">
                <?php } ?>
                <h4 class="text-secondary mt-2"><?php echo nullable_htmlentities($session_user_role_display); ?></h4>
            </center>

            <hr>

            <div class="form-group">
                <label>Your Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" name="name" placeholder="Full Name" value="<?php echo stripslashes(nullable_htmlentities($session_name)); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Your Email <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                    </div>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo nullable_htmlentities($session_email); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Your Avatar</label>
                <?php if ($session_avatar) { ?>
                    <br><a href="post.php?clear_your_user_avatar&csrf_token=<?= $_SESSION['csrf_token'] ?>">Avatar is set, click to clear</a>
                <?php } else { ?>
                    <input type="file" class="form-control-file" accept="image/*" name="avatar">
                <?php } ?>
            </div>

            <button type="submit" name="edit_your_user_details" class="btn btn-primary btn-block mt-3"><i class="fas fa-check mr-2"></i>Save</button>


        </form>
                
    </div>

</div>

<?php
require_once "includes/footer.php";
