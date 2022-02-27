<?php
/*
 * API - create_output.php
 * Included on calls to create.php endpoints
 * Checks the status of the insert SQL query ($insert_sql)
 * Returns success data / fail messages
 */

// Check if the insert query was successful
if($insert_sql){
  $insert_id = $mysqli->insert_id;
  if(isset($insert_id) && is_numeric($insert_id)){
    // Insert successful
    $return_arr['success'] = "True";
    $return_arr['count'] = '1';
    $return_arr['data'][] = [
      'insert_id' => $insert_id
    ];
  }
  // We shouldn't get here
  else{
    $return_arr['success'] = "False";
    $return_arr['message'] = "Auth success but insert failed, possibly database connection. Seek support if this error continues.";
  }
}

// Query returned false, something went wrong or it was declined due to required variables missing
else{
  $return_arr['success'] = "False";
  $return_arr['message'] = "Auth success but insert query failed, ensure required variables are provided and database schema is up-to-date.";
}

echo json_encode($return_arr);
exit();