/*
 * Ticket board - agent/ticket_kanban.php
 *
 * Drag a card to another column to change its status: agent/ajax.php
 * (update_kanban_ticket) saves it the way the ticket page would - into
 * Resolved resolves it and tells the client, out of Resolved reopens it - and
 * says no when it can't be done (open tasks, a status set by the system). On
 * a no, or a failed save, the card goes back where it was. Within a column,
 * cards keep the order they are dropped in when the admin has turned ticket
 * ordering on; otherwise they spring back.
 *
 * With "moving columns" on, dragging a column header reorders the statuses.
 * On a touch screen a card or column is held for a moment before it moves,
 * so the board still scrolls.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var board = document.getElementById('kanban-board');
    if (!board || typeof Sortable === 'undefined') {
        return;
    }

    var csrf = board.dataset.csrf;
    var ordering = board.dataset.ordering === '1';

    function post(data) {
        data.csrf_token = csrf;
        return fetch('ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: itflowSerialize(data)
        }).then(function (res) {
            return res.json().catch(function () {
                return { error: 'Could not save that - reload and try again.' };
            });
        });
    }

    function recount() {
        board.querySelectorAll('.kanban-column').forEach(function (column) {
            var count = column.querySelector('.kanban-column-count');
            if (count) {
                count.textContent = column.querySelectorAll('.kanban-card').length;
            }
        });
    }

    // Columns
    if (board.dataset.movingColumns === '1') {
        new Sortable(board, {
            animation: 150,
            handle: '.kanban-column-header',
            draggable: '.kanban-column',
            delay: 250,
            delayOnTouchOnly: true,
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex) {
                    return;
                }
                var positions = Array.prototype.map.call(board.querySelectorAll('.kanban-column'), function (column, index) {
                    return { status_id: column.dataset.statusId, status_kanban: index };
                });
                post({ update_kanban_status_position: 1, positions: positions }).then(function (reply) {
                    if (reply.error) {
                        itflowToast(reply.error, 'error');
                    }
                });
            }
        });
    }

    // Cards
    board.querySelectorAll('.kanban-status').forEach(function (list) {
        new Sortable(list, {
            group: 'tickets',
            animation: 150,
            draggable: '.kanban-card',
            // Links, the agent picker and the menu still work as themselves
            filter: 'a, button, .dropdown-menu',
            preventOnFilter: false,
            delay: 250,
            delayOnTouchOnly: true,
            onEnd: function (evt) {
                var card = evt.item;
                var from = evt.from;
                var to = evt.to;
                var moved = from !== to;

                function putBack() {
                    // Within one column the card still counts among its siblings
                    var index = evt.oldIndex + (!moved && evt.newIndex < evt.oldIndex ? 1 : 0);
                    from.insertBefore(card, from.children[index] || null);
                    recount();
                }

                if (!moved && (!ordering || evt.oldIndex === evt.newIndex)) {
                    if (!moved && !ordering) {
                        putBack();
                    }
                    return;
                }

                recount();
                card.classList.add('is-saving');

                var data = { update_kanban_ticket: 1, ticket_id: card.dataset.ticketId, status_id: to.dataset.statusId };
                if (ordering) {
                    data.order = Array.prototype.map.call(to.querySelectorAll('.kanban-card'), function (c) {
                        return c.dataset.ticketId;
                    });
                }

                post(data).then(function (reply) {
                    card.classList.remove('is-saving');
                    if (reply.error) {
                        putBack();
                        itflowToast(reply.error, 'error');
                        return;
                    }
                    card.dataset.ticketStatusId = to.dataset.statusId;
                    if (reply.message) {
                        itflowToast(reply.message, 'success');
                    }
                }).catch(function () {
                    card.classList.remove('is-saving');
                    putBack();
                    itflowToast('Could not save that - check your connection and try again.', 'error');
                });
            }
        });
    });
});
