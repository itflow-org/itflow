// Force the user to correctly type the client name to be deleted before activating the button to delete a client
function validateClientNameDelete(client_id) {
    if (document.getElementById("clientNameProvided" + client_id).value === document.getElementById("clientName" + client_id).value) {
        document.getElementById("clientDeleteButton" + client_id).className = "btn btn-danger btn-lg px-5";
    }
    else{
        document.getElementById("clientDeleteButton" + client_id).className = "btn btn-danger btn-lg px-5 disabled";
    }
}
