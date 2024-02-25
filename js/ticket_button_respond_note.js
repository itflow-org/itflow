// Ticket.php - Changes the wording of the "Respond" button to "Add note" if reply is not a public update (based on checkbox)

// Get Internal/Public Checkbox
let checkbox = document.getElementById('ticket_reply_type_checkbox');

// Get Respond button
let respond = document.getElementById('ticket_add_reply');

// When checkbox is checked/unchecked, update button wording
checkbox.addEventListener('change', e => {
    if (e.target.checked) {
        // Public reply
        respond.innerHTML = "<i class=\"fas fa-paper-plane mr-2\"></i>Respond";

    } else {
        // Internal note
        respond.innerHTML = "<i class=\"fas fa-sticky-note mr-2\"></i>Add note";
    }
});
