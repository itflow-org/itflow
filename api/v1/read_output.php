<?php

// Output (to be included)
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
    $return_arr['message'] = "No resource for this company with the specified parameter(s).";
    echo json_encode($return_arr);
    exit();
}