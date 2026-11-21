// The client's name typed exactly turns on Delete (agent/modals/client/client_delete.php)
function validateClientNameDelete(client_id) {
    const typed = document.getElementById("clientNameProvided" + client_id).value;
    const name = document.getElementById("clientName" + client_id).value;
    document.getElementById("clientDeleteButton" + client_id).classList.toggle("disabled", typed !== name);
}
