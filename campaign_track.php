<?php

include("config.php");
include("functions.php");

// Create an image, 1x1 pixel in size
$im = imagecreate(1,1);

// Set the background colour
$white = imagecolorallocate($im,255,255,255);

// Allocate the background colour
imagesetpixel($im,1,1,$white);

// Set the image type
header("content-type:image/jpg");

// Create a JPEG file from the image
imagejpeg($im);

// Free memory associated with the image
imagedestroy($im);

if(isset($_GET['message_id'])){

    $message_id = intval($_GET['message_id']);
    $message_hash = trim(strip_tags(mysqli_real_escape_string($mysqli,$_GET['message_hash'])));

    $sql = mysqli_query($mysqli,"SELECT message_id FROM campaign_messages WHERE message_id = $message_id AND message_hash = '$message_hash'");
    if(mysqli_num_rows($sql) == 1){
        // Server variables
        $ip = trim(strip_tags(mysqli_real_escape_string($mysqli,get_ip())));
        mysqli_query($mysqli,"UPDATE campaign_messages SET message_ip = '$ip', message_opened_at = NOW() WHERE message_id = $message_id");
    }
}

?>  