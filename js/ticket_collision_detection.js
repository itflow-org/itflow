// Collision detection
// Adds a "view" entry of the current ticket every 2 mins into the database
// Updates the currently viewing (ticket_collision_viewing) element with anyone that's looked at this ticket in the last two mins
function ticket_collision_detection() {

    // Get the page ticket id
    var ticket_id = document.getElementById("ticket_id").value;

    //Send a GET request to ajax.php as ajax.php?ticket_add_view=true&ticket_id=NUMBER
    jQuery.get(
        "ajax.php",
        {ticket_add_view: 'true', ticket_id: ticket_id},
        function(data) {
            // We don't care about a response
        }
    );

    //Send a GET request to ajax.php as ajax.php?ticket_query_views=true&ticket_id=NUMBER
    jQuery.get(
        "ajax.php",
        {ticket_query_views: 'true', ticket_id: ticket_id},
        function(data) {
            //If we get a response from ajax.php, parse it as JSON
            const ticket_view_data = JSON.parse(data);
            document.getElementById("ticket_collision_viewing").innerHTML = ticket_view_data.message;
        }
    );
}
// Call on page load
ticket_collision_detection();

// Run every 2 mins
setInterval(ticket_collision_detection, 120*1000);