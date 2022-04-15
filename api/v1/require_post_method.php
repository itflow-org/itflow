<?php
if($_SERVER['REQUEST_METHOD'] !== "POST"){
  header("HTTP/1.1 405 Method Not Allowed");
  $return_arr['success'] = "False";
  $return_arr['message'] = "Can only send POST requests to this endpoint.";
  echo json_encode($return_arr);
  exit();
}