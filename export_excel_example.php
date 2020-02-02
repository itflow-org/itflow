<?php

header("Content-Type: application/xls");    
header("Content-Disposition: attachment; filename=excel.xls");  
header("Pragma: no-cache"); 
header("Expires: 0");

require_once 'conn.php';

$output = "";

if(ISSET($_POST['export'])){
	$output .="
		<table>
			<thead>
				<tr>
					<th>ID</th>
					<th>Firstname</th>
					<th>Lastname</th>
					<th>Address</th>
				</tr>
			<tbody>
	";

	$query = mysqli_query($conn, "SELECT * FROM `member`") or die(mysqli_errno());
	while($fetch = mysqli_fetch_array($query)){

	$output .= "
				<tr>
					<td>".$fetch['mem_id']."</td>
					<td>".$fetch['firstname']."</td>
					<td>".$fetch['lastname']."</td>
					<td>".$fetch['address']."</td>
				</tr>
	";
	}

	$output .="
			</tbody>

		</table>
	";

	echo $output;
}
 
?>