<?php include("header.php"); 
 

$sql = mysqli_query($mysqli,"SELECT * FROM transfers");

?>

<?php
      
    while($row = mysqli_fetch_array($sql)){
      $revenue_id = $row['revenue_id'];
      
      mysqli_query($mysqli,"UPDATE revenues SET category_id = 0 WHERE revenue_id = $revenue_id");
    }
    
    ?>

<?php include("footer.php");