

<ul class="pagination justify-content-end">

<?php
	
	if($total_pages <= 100){
		$pages_split = 10;
	}
	if(($total_pages <= 1000) AND ($total_pages > 100)){
		$pages_split = 100;
	}
	if(($total_pages <= 10000) AND ($total_pages > 1000)){
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
		if(($i == 1) OR (($p <= 3) AND ($i <= 6)) OR (($i >  $total_pages - 6) AND ($p > $total_pages - 3 )) OR (is_int($i / $pages_split)) OR (($p > 3) AND ($i >= $p - 2) AND ($i <= $p + 3)) OR ($i == $total_pages)){
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

<?php

echo "<div class='justify-content-start'><br><b>$record_from to $record_to Records:</b> $total_found_rows</div>";


?>