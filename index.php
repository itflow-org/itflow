<?php include("header.php"); ?>
	<?php $os = get_ip(); ?>
	<!-- Breadcrumbs-->
	<ol class="breadcrumb">
	  <li class="breadcrumb-item">
	    <a href="index.html">Dashboard</a>
	  </li>
	  <li class="breadcrumb-item active">Blank Page</li>
	</ol>

	<!-- Page Content -->
	<h1>Blank Page</h1>
	<hr>
	<p><?php echo get_user_agent(); ?></p>
	<p><?php echo get_ip(); ?></p>
	<p><?php echo get_os(); ?></p>
	<p><?php echo get_web_browser(); ?></p>
	<p><?php echo get_device(); ?></p>

<?php include("footer.php"); ?>