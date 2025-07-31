// Keep PHP sessions alive
// Sends requests to keepalive.php in the background every 10 mins to prevent PHP garbage collection ending sessions

function keep_alive() {

    //Send a GET request to keepalive.php as keepalive.php?keepalive
    jQuery.get(
        "../keepalive.php",
        {keepalive: 'true'},
        function(data) {
            // Don't care about a response
        }
    );

}

// Run every 10 mins
setInterval(keep_alive, 600000);
