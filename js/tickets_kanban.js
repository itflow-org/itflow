$(document).ready(function() {
    console.log('CONFIG_TICKET_MOVING_COLUMNS: ' + CONFIG_TICKET_MOVING_COLUMNS);
    console.log('CONFIG_TICKET_ORDERING: ' + CONFIG_TICKET_ORDERING);
    // Initialize Dragula for the Kanban board
    let boardDrake = dragula([
        document.querySelector('#kanban-board')
    ], {
        moves: function(el, container, handle) {
            return handle.classList.contains('panel-title');
        },
        accepts: function(el, target, source, sibling) {
            return CONFIG_TICKET_MOVING_COLUMNS === 1;
        }
    });

    // Log the event of moving the column panel-title
    boardDrake.on('drag', function(el) {
        //console.log('Dragging column:', el.querySelector('.panel-title').innerText);
    });

    boardDrake.on('drop', function(el, target, source, sibling) {
        //console.log('Dropped column:', el.querySelector('.panel-title').innerText);

        // Get all columns and their positions
        let columns = document.querySelectorAll('#kanban-board .kanban-column');
        let columnPositions = [];

        columns.forEach(function(column, index) {
            let statusId = $(column).data('status-id'); // Assuming you have a data attribute for status ID
            columnPositions.push({
                status_id: statusId,
                status_kanban: index
            });
        });

        // Send AJAX request to update all column positions
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                update_kanban_status_position: true,
                positions: columnPositions
            },
            success: function(response) {
                console.log('Ticket status kanban orders updated successfully.');
                // Optionally, you can refresh the page or update the UI here
            },
            error: function(xhr, status, error) {
                console.error('Error updating ticket status kanban orders:', error);
            }
        });
    });

    // Initialize Dragula for the Kanban Cards
    let drake = dragula([
        ...document.querySelectorAll('#status')
    ]);

    // Add event listener for the drop event
    drake.on('drop', function (el, target, source, sibling) {
        // Log the target ID to the console
        //console.log('Dropped into:', target.getAttribute('data-column-name'));

        if (CONFIG_TICKET_ORDERING === 0 && source == target) {
            drake.cancel(true); // Move the card back to its original position
            return;
        }
        
        // Get all cards in the target column and their positions
        let cards = $(target).children('.task');
        let positions = [];

        //id of current status / column
        let columnId = $(target).data('status-id');

        let movedTicketId = $(el).data('ticket-id');
        let movedTicketStatusId = $(el).data('ticket-status-id');

        cards.each(function(index, card) {
            let ticketId = $(card).data('ticket-id');
            let statusId = $(card).data('ticket-status-id');
            
            let oldStatus = false;     
            if (ticketId == movedTicketId) {
                oldStatus = movedTicketStatusId;
            }

            //update the status id of the card if needed
            if (statusId != columnId) {
                 $(card).data('ticket-status-id', columnId);
                 statusId = columnId;
            }
            positions.push({
                ticket_id: ticketId,
                ticket_order: index,
                ticket_oldStatus: oldStatus,
                ticket_status: statusId ??  null// Get the new status ID from the target column
            });
        });

        //console.log(positions);
        // Send AJAX request to update all ticket kanban orders and statuses
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                update_kanban_ticket: true,
                positions: positions
            },
            success: function(response) {
                //console.log('Ticket kanban orders and statuses updated successfully.');
            },
            error: function(xhr, status, error) {
                console.error('Error updating ticket kanban orders and statuses:', error);
            }
        });
    });
});