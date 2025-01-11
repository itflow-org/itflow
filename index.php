<?php 

if (file_exists("config.php")) {
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

	require_once "includes/footer.php";


} else {
	header("Location: setup.php");
}

?>