<?php

/*
 * API - read_output.php
 * Included on calls to read.php endpoints
 * Returns success & data messages
 */

if($sql && mysqli_num_rows($sql) > 0){
  $return_arr['success'] = "True";
  $return_arr['count'] = mysqli_num_rows($sql);

  $row = array();
  while($row = mysqli_fetch_array($sql)){
    $return_arr['data'][] = $row;
  }

  echo json_encode($return_arr);
  exit();
}
else{
  $return_arr['success'] = "False";
  $return_arr['message'] = "No resource (for this client and company) with the specified parameter(s).";
  echo json_encode($return_arr);
  exit();
}