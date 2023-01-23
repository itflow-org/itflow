// Testing this keep alive JS function due to issues reported with session cookies timing out too soon
function keep_session_alive() {
    //Send a GET request to ajax.php every. This should be enough for PHP to see the session is still active.
    jQuery.get(
        "ajax.php",
        {},
        function(nothing) {
            // Don't care
        }
    );
}

// Call function every 10 mins
setInterval(keep_session_alive, 600000);

