<?php 

require_once "includes/inc_all.php";

?>
	
<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="index.php">Dashboard</a>
  </li>
  <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>Blank Page</h1>
<hr>
<?php

if (isset($config_start_page)) { ?>
    <meta http-equiv="refresh" content="0;url=<?php echo $config_start_page; ?>">
<?php }

require_once "../includes/footer.php";