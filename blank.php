<?php include("header.php"); ?>

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
<button class="btn clipboard" type="button" data-clipboard-text="Just because you can doesn't mean you should — clipboard.js">
    Copy to clipboard
</button>

<?php 

echo "$session_permission_companies";
print_r($session_permission_companies_array); 


?>

<?php include("footer.php"); ?>