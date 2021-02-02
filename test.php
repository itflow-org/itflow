<?php

$phone = ",above// \\5";


$stripped_phone = preg_replace("/[^0-9]/", '',$phone);

echo $phone;
echo "<br>";
echo $stripped_phone;

?>