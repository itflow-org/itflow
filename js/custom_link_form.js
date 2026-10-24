// New Link and Edit Link (admin/modals/custom_link/custom_link_form.php).
// The preview draws the link the way its menu will - a line of the agent,
// admin or reports menu, an icon in the top bar, or an item of the client
// portal's menu - as its name, icon, menu and new-tab switch change. The
// search narrows the icons, and a whole Font Awesome icon name that isn't in
// the list can be used too. Until the switch is touched, the address decides
// it: another site, or something a program opens, in a new tab; a page of
// ITFlow in the same one. An address a link can't use (javascript: and the
// like) is flagged here; the server checks it all again.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    // The address schemes a link may use - customLinkUriProblem() in functions/custom_link.php
    const SCHEMES = ['http', 'https', 'mailto', 'tel', 'file', 'ftp', 'ftps', 'sftp', 'dav', 'webdav', 'caldav', 'carddav', 'ssh', 'telnet', 'smb', 'rdp', 'vnc',
        'rustdesk', 'anydesk', 'connectwise', 'splashtop', 'sip', 'sips', 'ldap', 'ldaps'];

    // What the preview says under itself, by menu
    const NOTES = {
        1: 'In the agent menu, under Tools',
        2: 'In the top bar - its name shows on hover',
        3: 'In the client portal\'s menu',
        4: 'In the admin menu',
        5: 'In the reports menu'
    };

    // Whether Font Awesome draws an icon by this name
    function iconExists(probe, name) {
        probe.className = 'fas fa-' + name;
        const content = window.getComputedStyle(probe, '::before').content;
        return content !== '' && content !== 'none' && content !== 'normal';
    }

    // What is wrong with an address, or ''
    function uriProblem(value) {
        const match = /^([a-z][a-z0-9+.-]*):/i.exec(value);
        if (match && SCHEMES.indexOf(match[1].toLowerCase()) === -1) {
            return 'A link can\'t start with "' + match[1] + ':" - use a web address (https://...), a page of ITFlow (/agent/...), mailto: or tel:.';
        }
        return '';
    }

    // Whether an address is another site, or something a program opens
    function isOutside(value) {
        const host = /^(?:[a-z][a-z0-9+.-]*:)?\/\/([^\/?#]+)/i.exec(value);
        if (host) {
            return host[1].toLowerCase() !== window.location.host.toLowerCase();
        }
        return /^[a-z][a-z0-9+.-]*:/i.test(value);
    }

    document.querySelectorAll('[data-custom-link-form]:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const preview = form.querySelector('[data-custom-link-preview]');
        const item = form.querySelector('[data-custom-link-preview-item]');
        const previewIcon = form.querySelector('[data-custom-link-preview-icon]');
        const previewName = form.querySelector('[data-custom-link-preview-name]');
        const previewTab = form.querySelector('[data-custom-link-preview-tab]');
        const previewNote = form.querySelector('[data-custom-link-preview-note]');
        const name = form.querySelector('[data-custom-link-name]');
        const uri = form.querySelector('[data-custom-link-uri]');
        const uriProblemNote = form.querySelector('[data-custom-link-uri-problem]');
        const newTab = form.querySelector('[data-custom-link-new-tab]');
        const location = form.querySelector('[data-custom-link-location]');
        const locationAbout = form.querySelector('[data-custom-link-location-about]');
        const description = form.querySelector('[data-custom-link-description]');
        const icon = form.querySelector('[data-custom-link-icon]');
        const search = form.querySelector('[data-custom-link-icon-search]');
        const choices = form.querySelectorAll('[data-custom-link-icon-choice]');
        const custom = form.querySelector('[data-custom-link-icon-custom]');
        const none = form.querySelector('[data-custom-link-icon-none]');
        const probe = document.createElement('i');
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        form.appendChild(probe);

        // Redraw the preview from the fields
        function draw() {
            const where = location.value;
            preview.dataset.location = where;
            previewName.textContent = name.value.trim() || 'New link';
            previewIcon.className = 'fas fa-fw fa-' + (icon.value || 'external-link-alt');
            previewTab.hidden = !newTab.checked;
            item.title = (where === '2' ? (name.value.trim() || 'New link') + (description.value.trim() ? ' - ' : '') : '') + description.value.trim();
            previewNote.textContent = NOTES[where] || '';
            const option = location.options[location.selectedIndex];
            locationAbout.textContent = option ? option.dataset.about : '';
        }

        function checkUri() {
            const problem = uriProblem(uri.value.trim());
            uri.setCustomValidity(problem);
            uri.classList.toggle('is-invalid', problem !== '');
            uriProblemNote.textContent = problem;
        }

        function setIcon(value) {
            icon.value = value;
            choices.forEach(function (choice) {
                const on = choice.dataset.customLinkIconChoice === value;
                choice.classList.toggle('active', on);
                choice.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            custom.classList.toggle('active', value !== '' && custom.dataset.icon === value);
            draw();
        }

        function filterIcons() {
            const term = search.value.trim().toLowerCase().replace(/^(fa[srb]?\s+)?fa-/, '').replace(/\s+/g, '-');
            let shown = 0;
            let exact = false;
            choices.forEach(function (choice) {
                const value = choice.dataset.customLinkIconChoice;
                // "None" stays while nothing is searched
                const match = term === '' || (value !== '' && value.indexOf(term) !== -1);
                choice.hidden = !match;
                shown += match ? 1 : 0;
                exact = exact || value === term;
            });
            const usable = term !== '' && !exact && /^[a-z0-9-]+$/.test(term) && iconExists(probe, term);
            custom.hidden = !usable;
            if (usable) {
                custom.dataset.icon = term;
                custom.querySelector('i').className = 'fas fa-fw fa-' + term + ' me-1';
                custom.querySelector('span').textContent = 'Use ' + term;
                custom.classList.toggle('active', icon.value === term);
            }
            none.hidden = shown > 0 || usable;
        }

        name.addEventListener('input', draw);
        description.addEventListener('input', draw);
        location.addEventListener('change', draw);
        newTab.addEventListener('change', function () {
            newTab.dataset.touched = '1';
            draw();
        });
        uri.addEventListener('input', function () {
            checkUri();
            // Until the switch is touched, the address decides it
            if (!newTab.dataset.touched && uri.value.trim() !== '') {
                newTab.checked = isOutside(uri.value.trim());
                draw();
            }
        });

        choices.forEach(function (choice) {
            choice.addEventListener('click', function () {
                setIcon(choice.dataset.customLinkIconChoice);
            });
        });
        custom.addEventListener('click', function () {
            setIcon(custom.dataset.icon);
        });
        search.addEventListener('input', filterIcons);
        // Enter in the search picks the first icon shown, rather than saving
        search.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            if (!custom.hidden) {
                setIcon(custom.dataset.icon);
                return;
            }
            const first = Array.prototype.find.call(choices, function (choice) {
                return !choice.hidden && choice.dataset.customLinkIconChoice !== '';
            });
            if (first) {
                setIcon(first.dataset.customLinkIconChoice);
            }
        });

        checkUri();
        draw();
    });
})();
