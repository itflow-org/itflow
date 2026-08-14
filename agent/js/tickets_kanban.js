document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------
    // Drag: Kanban Columns (Statuses)
    // -------------------------------
    new Sortable(document.querySelector('#kanban-board'), {
        animation: 150,
        handle: '.kanban-column-header',
        draggable: '.kanban-column',
        onEnd: function () {
            const columnPositions = Array.from(document.querySelectorAll('#kanban-board .kanban-column')).map((col, index) => ({
                status_id: col.dataset.statusId,
                status_kanban: index
            }));

            if (CONFIG_TICKET_MOVING_COLUMNS === 1) {
                itflowPostForm('ajax.php', {
                    update_kanban_status_position: true,
                    positions: columnPositions
                }).catch((err) => {
                    console.error('Error updating status order:', err);
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
            // Let the ticket number / subject links work without starting a drag
            filter: 'a',
            preventOnFilter: false,
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

                const columnId = target.dataset.statusId;

                const positions = Array.from(target.querySelectorAll('.task')).map((card, index) => {
                    const ticketId = card.dataset.ticketId;
                    const oldStatus = ticketId === movedEl.dataset.ticketId
                        ? movedEl.dataset.ticketStatusId
                        : false;

                    card.dataset.ticketStatusId = columnId; // update DOM

                    return {
                        ticket_id: ticketId,
                        ticket_order: index,
                        ticket_oldStatus: oldStatus,
                        ticket_status: columnId
                    };
                });

                itflowPostForm('ajax.php', {
                    update_kanban_ticket: true,
                    positions: positions
                }).catch((err) => {
                    console.error('Error updating ticket positions:', err);
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
        document.querySelectorAll('.drag-handle-class').forEach(function (el) {
            el.style.display = 'inline';
        });
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
