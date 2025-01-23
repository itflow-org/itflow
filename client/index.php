<?php
/*
 * Client Portal
 * Landing / Home page for the client portal
 */

header("Content-Security-Policy: default-src 'self'");

require_once "includes/inc_all.php";

?>
<div class="col-md-2 offset-1">
    <a href="ticket_add.php" class="btn btn-primary btn-block">New ticket</a>
</div>

<?php require_once "includes/footer.php"; ?>
