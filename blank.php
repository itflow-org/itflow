<?php include("inc_all.php"); ?>

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
<p>This is a great starting point for new custom pages.</p>

<?php 

$fmt = numfmt_create( 'us_EN', NumberFormatter::CURRENCY );
echo numfmt_format_currency($fmt, -199.99, "USD")."\n"; 

?>

<?php include("footer.php"); ?>