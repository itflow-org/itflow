$(document).ready(function () {
    console.log('CONFIG_TICKET_MOVING_COLUMNS:', CONFIG_TICKET_MOVING_COLUMNS);
    console.log('CONFIG_TICKET_ORDERING:', CONFIG_TICKET_ORDERING);

    // -------------------------------
    // Drag: Kanban Columns (Statuses)
    // -------------------------------
    new Sortable(document.querySelector('#kanban-board'), {
        animation: 150,
        handle: '.panel-title',
        draggable: '.kanban-column',
        onEnd: function () {
            const columnPositions = Array.from(document.querySelectorAll('#kanban-board .kanban-column')).map((col, index) => ({
                status_id: $(col).data('status-id'),
                status_kanban: index
            }));

            if (CONFIG_TICKET_MOVING_COLUMNS === 1) {
                $.post('ajax.php', {
                    update_kanban_status_position: true,
                    positions: columnPositions
                }).done(() => {
                    console.log('Ticket status kanban orders updated.');
                }).fail((xhr) => {
                    console.error('Error updating status order:', xhr.responseText);
                });
            }
        }
    });

    // -------------------------------
    // Drag: Tasks within Columns
    // -------------------------------
    document.querySelectorAll('.kanban-status').forEach(statusCol => {
        new Sortable(statusCol, {
            group: 'tickets',
            animation: 150,
            handle: isTouchDevice() ? '.drag-handle-class' : undefined,
            onStart: () => hidePlaceholders(),
            onEnd: function (evt) {
                const target = evt.to;
                const movedEl = evt.item;

                // Disallow reordering in same column if config says so
                if (CONFIG_TICKET_ORDERING === 0 && evt.from === evt.to) {
                    evt.from.insertBefore(movedEl, evt.from.children[evt.oldIndex]);
                    showPlaceholders();
                    return;
                }

                const columnId = $(target).data('status-id');

                const positions = Array.from(target.querySelectorAll('.task')).map((card, index) => {
                    const ticketId = $(card).data('ticket-id');
                    const oldStatus = ticketId === $(movedEl).data('ticket-id')
                        ? $(movedEl).data('ticket-status-id')
                        : false;

                    $(card).data('ticket-status-id', columnId); // update DOM

                    return {
                        ticket_id: ticketId,
                        ticket_order: index,
                        ticket_oldStatus: oldStatus,
                        ticket_status: columnId
                    };
                });

                $.post('ajax.php', {
                    update_kanban_ticket: true,
                    positions: positions
                }).done(() => {
                    console.log('Updated kanban ticket positions.');
                }).fail((xhr) => {
                    console.error('Error updating ticket positions:', xhr.responseText);
                });

                // Refresh placeholders after update
                showPlaceholders();
            }
        });
    });

    // -------------------------------
    // 📱 Touch Support: Show drag handle on mobile
    // -------------------------------
    if (isTouchDevice()) {
        $('.drag-handle-class').css('display', 'inline');
    }

    // -------------------------------
    // Placeholder Management
    // -------------------------------
    function showPlaceholders() {
        document.querySelectorAll('.kanban-status').forEach(status => {
            const placeholderClass = 'empty-placeholder';

            // Remove existing placeholder
            const existing = status.querySelector(`.${placeholderClass}`);
            if (existing) existing.remove();

            // Only show if there are no tasks
            if (status.querySelectorAll('.task').length === 0) {
                const placeholder = document.createElement('div');
                placeholder.className = `${placeholderClass} text-muted text-center p-2`;
                placeholder.innerText = 'Drop ticket here';
                placeholder.style.pointerEvents = 'none';
                status.appendChild(placeholder);
            }
        });
    }

    function hidePlaceholders() {
        document.querySelectorAll('.empty-placeholder').forEach(el => el.remove());
    }

    // Run once on load
    showPlaceholders();

    // -------------------------------
    // Utility: Detect touch device
    // -------------------------------
    function isTouchDevice() {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }
});
