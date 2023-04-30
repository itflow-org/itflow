<?php
/*
 * Client Portal
 * User profile
 */

header("Content-Security-Policy: default-src 'self' https: fonts.googleapis.com");

require_once('inc_portal.php');
?>

    <h2>Profile</h2>

    <p>Name: <?php echo $session_contact_name ?></p>
    <p>Email: <?php echo $session_contact_email ?></p>
    <p>Client: <?php echo $session_client_name ?></p>
    <br>
    <p>Client Primary Contact: <?php if ($session_client_primary_contact_id == $session_contact_id) {echo "Yes"; } else {echo "No";} ?></p>
    <p>Client Technical Contact: <?php if ($session_contact_is_technical_contact) {echo "Yes"; } else {echo "No";} ?></p>
    <p>Client Billing Contact: <?php if ($session_contact_is_billing_contact == $session_contact_id) {echo "Yes"; } else {echo "No";} ?></p>


    <p>Login via: <?php echo $_SESSION['login_method'] ?> </p>


    <!--  // Show option to change password if auth provider is local -->
<?php if ($_SESSION['login_method'] == 'local'): ?>
    <hr>
    <div class="col-md-6">
        <h4>Password</h4>
        <form action="portal_post.php" method="post" autocomplete="off">
            <div class="form-group">
                <label>New Password</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" minlength="6" required data-toggle="password" name="new_password" placeholder="Leave blank for no change" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" name="edit_profile" class="btn btn-primary text-bold mt-3"><i class="fas fa-check mr-2"></i>Save password</button>
        </form>
    </div>
<?php endif ?>

<?php
require_once('portal_footer.php');
