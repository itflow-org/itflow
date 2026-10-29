// New Interface and Edit Interface (agent/modals/asset/asset_interface_form.php):
// a fixed address, DHCP or none; the address is checked as it is typed
// against the network picked - outside it, on another device, held back,
// inside the DHCP range (ajax.php ?interface_ip_check) - and Next free fills
// in the first address nothing has (?network_next_free_ip). The server runs
// the same checks on saving; these are advisory.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    document.querySelectorAll('.itflow-interface-form:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const network = form.querySelector('[data-interface-network]');
        const ipInput = form.querySelector('[data-interface-ip]');
        const macInput = form.querySelector('[data-interface-mac]');
        const nextButton = form.querySelector('[data-interface-next]');
        const feedback = form.querySelector('[data-interface-feedback]');
        const staticBox = form.querySelector('[data-interface-static]');
        const dhcpNote = form.querySelector('[data-interface-dhcp]');
        const tones = {ok: 'text-success', warning: 'text-warning-emphasis', error: 'text-danger'};
        const icons = {ok: 'fa-check', warning: 'fa-exclamation-triangle', error: 'fa-times-circle'};
        let timer = null;
        let asked = null;

        function mode() {
            const checked = form.querySelector('[data-interface-mode]:checked');
            return checked ? checked.value : 'static';
        }

        function render(state, message) {
            feedback.className = 'small mt-1 ' + (tones[state] || '');
            feedback.innerHTML = '';
            ipInput.classList.toggle('is-invalid', state === 'error');
            ipInput.classList.toggle('is-valid', state === 'ok');
            if (!message) {
                return;
            }
            const icon = document.createElement('i');
            icon.className = 'fas fa-fw me-1 ' + (icons[state] || 'fa-info-circle');
            const text = document.createElement('span');
            // The server escapes what it sends back
            text.innerHTML = message;
            feedback.append(icon, text);
        }

        function check(force) {
            const ip = ipInput.value.trim();
            const key = ip + '|' + network.value + '|' + (macInput ? macInput.value.trim() : '');
            if (!force && key === asked) {
                return;
            }
            asked = key;
            if (ip === '' || mode() !== 'static') {
                render('', '');
                return;
            }
            itflowGet('ajax.php', {
                interface_ip_check: 1, ip: ip, network_id: network.value,
                interface_id: form.dataset.interfaceId, asset_id: form.dataset.assetId,
                mac: macInput ? macInput.value.trim() : ''
            }, function (text) {
                if (ipInput.value.trim() !== ip) {
                    return;
                }
                try {
                    const data = JSON.parse(text);
                    render(data.state, data.message);
                } catch (e) {
                    render('', '');
                }
            });
        }

        function later() {
            clearTimeout(timer);
            timer = setTimeout(check, 350);
        }

        form.querySelectorAll('[data-interface-mode]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                staticBox.hidden = mode() !== 'static';
                dhcpNote.hidden = mode() !== 'dhcp';
                if (mode() === 'static') {
                    ipInput.focus();
                }
                check(true);
            });
        });

        network.addEventListener('change', function () {
            nextButton.disabled = !network.value || network.value === '0';
            check(true);
        });
        ipInput.addEventListener('input', later);
        ipInput.addEventListener('blur', function () {
            check();
        });
        if (macInput) {
            macInput.addEventListener('change', function () {
                check(true);
            });
        }

        nextButton.addEventListener('click', function () {
            itflowGet('ajax.php', {network_next_free_ip: 1, network_id: network.value}, function (text) {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    return;
                }
                if (data.ip) {
                    ipInput.value = data.ip;
                    check(true);
                } else {
                    render('warning', data.message || 'No free address.');
                }
            });
        });

        if (ipInput.value.trim() !== '') {
            check(true);
        }
    });

})();
