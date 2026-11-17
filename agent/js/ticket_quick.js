/*
 * Quick ticket's window (agent/modals/ticket/ticket_quick.php): the client's
 * contacts once it is picked - its primary to start with, when the setting
 * says so - and the contact's tickets still open, with Work on this one to go
 * to one instead. Make ticket sends when the new ticket's timer counts from,
 * by this browser's clock, as the ticket page's timer is this browser's
 * (agent/js/ticket_time_tracking.js). Run again each time the window opens.
 */
(function () {
    'use strict';

    const form = document.getElementById('quickTicketForm');
    if (!form || form.dataset.ready) {
        return;
    }
    form.dataset.ready = '1';

    const openedAt = Date.now();
    const timerMode = form.dataset.timer;
    const client = document.getElementById('quickTicketClient');
    const contact = document.getElementById('quickTicketContact');
    const openBox = document.getElementById('quickTicketOpen');
    const subject = document.getElementById('quickTicketSubject');
    let contactRequest = 0;
    let openRequest = 0;

    function timerStart() {
        return timerMode === 'open' ? openedAt : Date.now();
    }

    // The ticket's timer as the ticket page keeps it - only one that isn't there already
    function startTimer(ticketId, from) {
        try {
            if (!localStorage.getItem(ticketId + '-startTime') && !localStorage.getItem(ticketId + '-pausedTime')) {
                localStorage.setItem(ticketId + '-startTime', String(from));
                localStorage.setItem('ticket-timer-running-' + ticketId, 'true');
            }
        } catch (e) {
            // No storage - the ticket opens without a running timer
        }
    }

    function contactsOnly(label) {
        contact.innerHTML = '';
        contact.add(new Option(label, '0'));
    }

    // The contact's tickets still open
    function loadOpen() {
        const mine = ++openRequest;
        openBox.innerHTML = '';
        if (form.dataset.openTickets !== '1' || !client.value || contact.value === '0') {
            return;
        }
        itflowGet('/agent/ajax.php', { ticket_also_open: 1, work: 1, client_id: client.value, contact_id: contact.value }, function (data) {
            if (mine !== openRequest) {
                return;
            }
            try {
                openBox.innerHTML = JSON.parse(data).html || '';
            } catch (e) {
                openBox.innerHTML = '';
            }
        });
    }

    // The client's contacts - found by name, email address or phone number
    function loadContacts() {
        const mine = ++contactRequest;
        openBox.innerHTML = '';
        contact.disabled = true;
        contactsOnly(client.value ? 'Loading...' : 'Pick the client first');
        if (!client.value) {
            return;
        }
        itflowGet('/agent/ajax.php', { get_client_contacts: 'true', client_id: client.value }, function (data) {
            if (mine !== contactRequest) {
                return;
            }
            let contacts = [];
            try {
                contacts = JSON.parse(data).contacts || [];
            } catch (e) {
                contacts = [];
            }
            contactsOnly('- No One -');
            contacts.forEach(function (row) {
                const option = new Option(row.contact_name, row.contact_id);
                const details = [];
                if (row.contact_title) {
                    details.push(row.contact_title);
                }
                if (row.contact_primary == '1') {
                    details.push('Primary');
                } else if (row.contact_technical == '1') {
                    details.push('Technical');
                }
                option.dataset.description = details.join(' · ');
                option.dataset.keywords = [row.contact_email, row.contact_phone, row.contact_mobile]
                    .concat([row.contact_phone, row.contact_mobile].map(function (number) { return String(number || '').replace(/\D/g, ''); }))
                    .filter(Boolean).join(' ');
                option.dataset.initials = row.contact_initials;
                option.dataset.shape = 'circle';
                option.dataset.tone = row.contact_tone || 'secondary';
                option.dataset.soft = '1';
                if (row.contact_photo_url) {
                    option.dataset.image = row.contact_photo_url;
                }
                contact.add(option);
            });
            contact.disabled = false;
            const primary = form.dataset.primary === '1' ? contacts.find(function (row) { return row.contact_primary == '1'; }) : null;
            contact.value = primary ? String(primary.contact_id) : '0';
            loadOpen();
        });
    }

    client.addEventListener('change', loadContacts);
    contact.addEventListener('change', loadOpen);

    // Work on this one - its timer started, as a new one's would be
    openBox.addEventListener('click', function (e) {
        const button = e.target.closest('[data-ticket-work]');
        if (!button) {
            return;
        }
        if (timerMode !== 'off') {
            startTimer(button.dataset.ticketWork, timerStart());
        }
        window.location.href = button.dataset.url;
    });

    form.addEventListener('submit', function () {
        document.getElementById('quickTicketTimer').value = timerMode === 'off' ? '' : String(timerStart());
    });

    // Ready to type - the issue, or the client first. After the window's own focus (js/app.js), which parks it on the dialog
    const modal = form.closest('.modal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            setTimeout(function () {
                if (client.value) {
                    subject.focus();
                } else if (client.itflowSelect) {
                    client.itflowSelect.control.focus();
                }
            }, 0);
        });
    }

    if (client.value) {
        loadContacts();
    }
})();
