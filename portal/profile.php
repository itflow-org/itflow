<?php
/*
 * Client Portal
 * User profile
 */

require('inc_portal.php');
?>

<h2>Profile</h2>

<p>Name: <?php echo $session_contact_name ?></p>
<p>Email: <?php echo $session_contact_email ?></p>
<p>Client: <?php echo $session_client_name ?></p>
<p>Client Primary Contact: <?php if($session_client_primary_contact_id == $session_contact_id) {echo "Yes"; } else {echo "No";} ?></p>
<p>Login via: <?php echo $_SESSION['login_method'] ?> </p>

<?php
include('portal_footer.php');