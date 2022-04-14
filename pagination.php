<?php
/*
 * Pagination Body/Footer
 * Displays page number buttons
 *
 * Should not be accessed directly, but called from other pages
 * Relies upon the $num_rows variable being set correctly
 */

$total_found_rows = $num_rows[0];
$total_pages = ceil($total_found_rows / $_SESSION['records_per_page']);

if ($total_found_rows > 10) {
	$i=0;

?>

<hr>

<div class="row">
	<div class="col mb-3">
		<form action="post.php" method="post">
			<select onchange="this.form.submit()" class="input-form select2" name="change_records_per_page">
				<option <?php if($_SESSION['records_per_page'] == 5){ echo "selected"; } ?> >5</option>
				<option <?php if($_SESSION['records_per_page'] == 10){ echo "selected"; } ?> >10</option>
				<option <?php if($_SESSION['records_per_page'] == 20){ echo "selected"; } ?> >20</option>
				<option <?php if($_SESSION['records_per_page'] == 50){ echo "selected"; } ?> >50</option>
				<option <?php if($_SESSION['records_per_page'] == 100){ echo "selected"; } ?> >100</option>
				<option <?php if($_SESSION['records_per_page'] == 500){ echo "selected"; } ?> >500</option>
			</select>
		</form>
	</div>
	<div class="col mb-3">
		<p class="text-center mt-2"><?php echo $total_found_rows; ?></p>
	</div>
	<div class="col mb-3">

		<ul class="pagination justify-content-end">

		<?php
			
			if($total_pages <= 100){
				$pages_split = 10;
			}
			if(($total_pages <= 1000) && ($total_pages > 100)){
				$pages_split = 100;
			}
			if(($total_pages <= 10000) && ($total_pages > 1000)){
				$pages_split = 1000;
			}
			if($p > 1){
				$prev_class = "";
			}else{
				$prev_class = "disabled";
			}
			if($p <> $total_pages) {
				$next_class = "";
			}else{
				$next_class = "disabled";
			}
		    $url_query_strings = http_build_query(array_merge($_GET,array('p' => $i)));
		    $prev_page = $p - 1;
		    $next_page  = $p + 1;
			
			if($p > 1){
				echo "<li class='page-item $prev_class'><a class='page-link' href='?$url_query_strings&p=$prev_page'>Prev</a></li>";
			}
		
			while($i < $total_pages){
		    	$i++;
				if(($i == 1) || (($p <= 3) && ($i <= 6)) || (($i >  $total_pages - 6) && ($p > $total_pages - 3 )) || (is_int($i / $pages_split)) || (($p > 3) && ($i >= $p - 2) && ($i <= $p + 3)) || ($i == $total_pages)){
			        if($p == $i ) {
			        	$page_class = "active"; 
			        }else{ 
			        	$page_class = "";
			    	}
			    	echo "<li class='page-item $page_class'><a class='page-link' href='?$url_query_strings&p=$i'>$i</a></li>";
				}
			}

			if($p <> $total_pages){
				echo "<li class='page_item $next_class'><a class='page-link' href='?$url_query_strings&p=$next_page'>Next</a></li>";
			}

		?>

		</ul>
	</div>
</div>

<?php

}
          
if($total_found_rows == 0){
	echo "<center class='my-3'><i class='far fa-fw fa-6x fa-meh-rolling-eyes text-secondary'></i><h3 class='text-secondary mt-3'>No Results</h3></center>";
}

?>
