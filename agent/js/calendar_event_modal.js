// New Event and Edit Event (agent/modals/calendar/calendar_event_form.php):
// All day hides the times, Repeats shows "every N weeks, until", and the
// client's sites and contacts follow the client picked - an internal event
// has neither. The end date and time keep up with the start (js/app.js).
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    const allDay = document.getElementById('event_form_all_day');
    const repeat = document.getElementById('eventRepeat');
    const interval = document.getElementById('eventRepeatInterval');
    const unit = document.getElementById('eventRepeatUnit');
    const clientSelect = document.getElementById('eventClientSelect');
    const siteField = document.getElementById('eventSiteField');
    const siteSelect = document.getElementById('eventSiteSelect');
    const contactsField = document.getElementById('eventContactsField');
    const contactsSelect = document.getElementById('eventContactsSelect');

    function syncAllDay() {
        if (!allDay) {
            return;
        }
        document.querySelectorAll('.event_form_time_fields').forEach(function (field) {
            field.classList.toggle('d-none', allDay.checked);
            field.querySelectorAll('input').forEach(function (input) {
                input.required = !allDay.checked;
            });
        });
    }
    if (allDay) {
        allDay.addEventListener('change', syncAllDay);
        syncAllDay();
    }

    function syncRepeat() {
        if (!repeat) {
            return;
        }
        const option = repeat.options[repeat.selectedIndex];
        const on = repeat.value !== '';
        document.getElementById('eventRepeatEvery').hidden = !on;
        document.getElementById('eventRepeatUntilField').hidden = !on;
        if (on && unit) {
            unit.textContent = Number(interval.value) === 1 ? option.dataset.one : option.dataset.many;
        }
    }
    if (repeat) {
        repeat.addEventListener('change', syncRepeat);
        interval.addEventListener('input', syncRepeat);
        syncRepeat();
    }

    // The sites and contacts of the client picked
    function loadClient(clientId) {
        const internal = !clientId || clientId === '0';
        if (siteField) {
            siteField.hidden = internal;
        }
        if (contactsField) {
            contactsField.hidden = internal;
        }
        if (siteSelect) {
            siteSelect.innerHTML = '';
            siteSelect.add(new Option('- No site -', '0'));
        }
        if (contactsSelect) {
            contactsSelect.innerHTML = '';
        }
        if (internal) {
            return;
        }
        itflowGet('ajax.php', {get_client_locations: 'true', client_id: clientId}, function (data) {
            let locations = [];
            try {
                locations = JSON.parse(data).locations || [];
            } catch (e) {
                locations = [];
            }
            locations.forEach(function (location) {
                const option = new Option(location.location_name, location.location_id);
                option.dataset.icon = 'fas fa-map-marker-alt';
                siteSelect.add(option);
            });
        });
        itflowGet('ajax.php', {get_client_contacts: 'true', client_id: clientId}, function (data) {
            let contacts = [];
            try {
                contacts = JSON.parse(data).contacts || [];
            } catch (e) {
                contacts = [];
            }
            contacts.forEach(function (contact) {
                const option = new Option(contact.contact_name, contact.contact_id);
                option.dataset.description = contact.contact_email || 'No email';
                if (contact.contact_photo_url) {
                    option.dataset.image = contact.contact_photo_url;
                } else {
                    option.dataset.initials = contact.contact_initials;
                    option.dataset.shape = 'circle';
                    option.dataset.tone = contact.contact_tone || 'secondary';
                    option.dataset.soft = '1';
                }
                contactsSelect.add(option);
            });
        });
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            loadClient(clientSelect.value);
        });
    }

})();
