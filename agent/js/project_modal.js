// New Project and Edit Project (agent/modals/project/project_form.php):
// the client contact picker follows the client picked, and an internal
// project has none; "assign the template's tickets" shows once a template
// is picked; the due date can't be before the start.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    const clientSelect = document.getElementById('projectClientSelect');
    const contactField = document.getElementById('projectContactField');
    const contactSelect = document.getElementById('projectContactSelect');
    const templateSelect = document.getElementById('projectTemplateSelect');
    const assignBox = document.getElementById('projectAssignToManager');
    const start = document.getElementById('projectStart');
    const due = document.getElementById('projectDue');

    // The client's contacts, primary first - the primary is picked for a new project
    function loadContacts(clientId) {
        if (!contactSelect) {
            return;
        }
        contactSelect.innerHTML = '';
        contactSelect.add(new Option('Nobody', '0'));
        const internal = !clientId || clientId === '0';
        if (contactField) {
            contactField.hidden = internal;
        }
        if (internal) {
            contactSelect.value = '0';
            return;
        }
        itflowGet('ajax.php', {get_client_contacts: 'true', client_id: clientId}, function (data) {
            let contacts = [];
            try {
                contacts = JSON.parse(data).contacts || [];
            } catch (e) {
                contacts = [];
            }
            let primary = '0';
            contacts.forEach(function (contact) {
                const details = [];
                if (contact.contact_title) {
                    details.push(contact.contact_title);
                }
                if (contact.contact_primary == '1') {
                    details.push('Primary');
                    if (primary === '0') {
                        primary = String(contact.contact_id);
                    }
                } else if (contact.contact_technical == '1') {
                    details.push('Technical');
                }
                const option = new Option(contact.contact_name, contact.contact_id);
                option.dataset.description = details.join(' · ');
                if (contact.contact_photo_url) {
                    option.dataset.image = contact.contact_photo_url;
                } else {
                    option.dataset.initials = contact.contact_initials;
                    option.dataset.shape = 'circle';
                    option.dataset.tone = contact.contact_tone || 'secondary';
                    option.dataset.soft = '1';
                }
                contactSelect.add(option);
            });
            contactSelect.value = primary;
        });
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', function () {
            loadContacts(clientSelect.value);
        });
        loadContacts(clientSelect.value);
    }

    function syncAssign() {
        if (templateSelect && assignBox) {
            assignBox.hidden = !templateSelect.value || templateSelect.value === '0';
        }
    }
    if (templateSelect) {
        templateSelect.addEventListener('change', syncAssign);
        syncAssign();
    }

    if (start && due) {
        start.addEventListener('change', function () {
            due.min = start.value;
            if (start.value && due.value && due.value < start.value) {
                due.value = '';
            }
        });
    }

})();
