<?php

include("config.php");

if($_GET['api_key'] == $config_api_key){

    if(isset($_GET['cid'])){

        $cid = intval($_GET['cid']);

        $sql = mysqli_query($mysqli,"SELECT * FROM clients WHERE client_phone = $cid");

        $row = mysqli_fetch_array($sql);
        $client_name = $row['client_name'];

        echo $client_name;

    }

    if(isset($_GET['client_numbers'])){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_phone = $row['client_phone'];

            echo "$client_name - $client_phone<br>";
        }

    }

    if(isset($_GET['client_emails'])){

        $sql = mysqli_query($mysqli,"SELECT * FROM clients;");

        while($row = mysqli_fetch_array($sql)){
            $client_name = $row['client_name'];
            $client_email = $row['client_email'];

            echo "$client_name - $client_email<br>";
        }

    }
}else{
    echo "<h1> Ma!! You've been BAAAAADDDDD!! </h1>";
}

?>