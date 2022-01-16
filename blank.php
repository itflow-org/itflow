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



<?php 

$company_name = "bum";


$postdata = http_build_query(
    array(
      'company_name' => "$company_name",
      'city' => "$city",
      'state' => "$state",
      'country' => "$country",
      'currency' => "$currency",
      'comments' => "$comments"
    )
  );
  
  $opts = array('http' =>
    array(
      'method' => 'POST',
      'header' => 'Content-type: application/x-www-form-urlencoded',
      'content' => $postdata
    )
  );
  
  $context = stream_context_create($opts);

  $result = file_get_contents('https://telemetry.itflow.org', false, $context);
  
  echo $result;

  header("Location: clients.php");

?>

<?php include("footer.php"); ?>