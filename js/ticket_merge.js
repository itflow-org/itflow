// Ticket merging

// Gets details of the ticket we're going to merge this ticket into
// Shows the details under the comments box & enables the merge button if the status of the merge into ticket is not closed
function merge_into_number_get_details() {

    // Get the ticket number to merge into
    var merge_into_ticket_number = document.getElementById("merge_into_ticket_number").value;

    // Reset the form
    document.getElementById("merge_ticket_btn").disabled = true;
    document.getElementById("merge_into_details_div").hidden = true;

    // Send a GET request to post.php as post.php?merge_ticket_get_json_details=true&merge_into_ticket_number=NUMBER
    jQuery.get(
        "ajax.php",
        {merge_ticket_get_json_details: 'true', merge_into_ticket_number: merge_into_ticket_number},
        function(data){
            // If we get a response from post.php, parse it as JSON
            const merge_into_ticket_info = JSON.parse(data);

            // Check that the current ticket ID isn't also the new/merge ticket ID
            if(parseInt(merge_into_ticket_info.ticket_id) !== parseInt(document.getElementById("current_ticket_id").value)){

                // Show the div with the parent ("master") ticket details, populate
                document.getElementById("merge_into_details_div").hidden = false;
                document.getElementById("merge_into_details_number").innerText = "Parent ticket details: " + merge_into_ticket_info.ticket_prefix + merge_into_ticket_info.ticket_number;
                document.getElementById("merge_into_details_client").innerText = "Client Contact: " + merge_into_ticket_info.client_name + " / " + merge_into_ticket_info.contact_name;
                document.getElementById("merge_into_details_subject").innerText = "Subject: " + merge_into_ticket_info.ticket_subject;
                document.getElementById("merge_into_details_priority").innerText = "Priority: " + merge_into_ticket_info.ticket_priority;
                document.getElementById("merge_into_details_status").innerText = "Status: " + merge_into_ticket_info.ticket_status_name;

                // Enable the merge button if the merge into ticket isn't in a closed state
                if(merge_into_ticket_info.ticket_status_name.toLowerCase() != "closed"){
                    document.getElementById("merge_ticket_btn").disabled = false;
                }
            }
        }
    );
}
