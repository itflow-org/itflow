<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self' fonts.googleapis.com fonts.gstatic.com");

require_once "inc_portal.php";


?>

<div class="row">
    <div class="col-md-1 text-center">
        <?php if (!empty($session_contact_photo)) { ?>
            <img src="<?php echo "../uploads/clients/$session_client_id/$session_contact_photo"; ?>" alt="..." height="50" width="50" class="img-circle img-responsive">

        <?php } else { ?>

            <span class="fa-stack fa-2x rounded-left">
                <i class="fa fa-circle fa-stack-2x text-secondary"></i>
                <span class="fa fa-stack-1x text-white"><?php echo $session_contact_initials; ?></span>
            </span>
        <?php } ?>
    </div>

    <div class="col-md-11 p-0">
        <h4>Welcome, <strong><?php echo $session_contact_name ?></strong>!</h4>
        <hr>
    </div>
    <br>

    <div class="col-md-2 offset-1">
        <a href="ticket_add.php" class="btn btn-primary btn-block">New ticket</a>
    </div>

</div>

<?php require_once "portal_footer.php";
 ?>
