<?php include("header.php"); ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="index.html">Dashboard</a>
  </li>
  <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>PHP SELF: <?php echo basename($_SERVER['PHP_SELF']); ?></h1>
<hr>
<h3>PHP URI: <?php echo $_SERVER['REQUEST_URI']; ?></h1>
<h1>basename _FILE_ : <?php echo basename(__FILE__); ?></h1>
<h1>User Agent: <?php echo $_SERVER['HTTP_USER_AGENT']; ?>
<p>This is a great starting point for new custom pages.</p>

<?php include("footer.php"); ?>