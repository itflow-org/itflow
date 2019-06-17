<?php include("header.php"); ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="index.html">Dashboard</a>
  </li>
  <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>PHP SELF basename: <?php echo basename($_SERVER['PHP_SELF']); ?></h1>
<h1>PHP SELF: <?php echo $_SERVER['PHP_SELF']; ?></h1>
<hr>
<h3>PHP URI: <?php echo $_SERVER['REQUEST_URI']; ?></h3>
<h3>PHP Server_name: <?php echo $_SERVER['SERVER_NAME']; ?></h3>
<h3>PHP HTTP_HOST: <?php echo $_SERVER['HTTP_HOST']; ?></h3>

<h3><?php echo $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?></h3>
	
<h1>basename _FILE_ : <?php echo basename(__FILE__); ?></h1>
<h1>User Agent: <?php echo $_SERVER['HTTP_USER_AGENT']; ?>
<p>This is a great starting point for new custom pages.</p>

<?php 
require_once('rfc6238.php');
	
	$secretkey = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
	$currentcode = '739453';
	if (TokenAuth6238::verify($secretkey,$currentcode)) {
		echo "Code is valid\n";
	} else {
		echo "Invalid code\n";
	}
  print sprintf('<img src="%s"/>',TokenAuth6238::getBarCodeUrl('','',$secretkey,'My%20App'));
  print TokenAuth6238::getTokenCodeDebug($secretkey,0); 
 

?>


<h3><?php echo $config_quote_email_subject; ?></h3>

<?php include("footer.php"); ?>