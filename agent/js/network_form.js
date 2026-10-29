// New Network and Edit Network (agent/modals/network/network_form.php):
// the subnet is read as it is typed - its size, mask and first and last
// host, the client's networks it overlaps, and a gateway and DHCP range to
// use when those are empty (ajax.php ?network_subnet_info). A new network's
// type follows its name ("Guest Wireless" is Guest) until one is picked;
// the provider picker shows for an internet connection.
//
// Wrapped in an IIFE because a modal can be opened, closed and opened again
// in one page load, which re-runs this file.

(function () {

    document.querySelectorAll('.itflow-network-form:not([data-wired])').forEach(function (form) {
        form.setAttribute('data-wired', '1');

        const clientId = form.dataset.clientId || '0';
        const networkId = form.dataset.networkId || '0';
        const nameInput = form.querySelector('[data-network-name]');
        const typeSelect = form.querySelector('[data-network-type]');
        const provider = form.querySelector('[data-network-provider]');
        const cidrInput = form.querySelector('[data-network-cidr]');
        const info = form.querySelector('[data-network-cidr-info]');
        const gatewayInput = form.querySelector('[data-network-gateway]');
        const rangeInput = form.querySelector('[data-network-range]');
        const hint = info ? info.innerHTML : '';

        function setType(type) {
            if (typeSelect.value === type) {
                return;
            }
            if (typeSelect.itflowSelect) {
                typeSelect.itflowSelect.setValue(type);
            } else {
                typeSelect.value = type;
            }
            typeSelect.dispatchEvent(new Event('change', {bubbles: true}));
        }

        // The provider is for an internet connection - or anything that has one already
        function showProvider() {
            if (!provider) {
                return;
            }
            const vendor = provider.querySelector('select');
            provider.hidden = typeSelect.value !== 'WAN' && (!vendor || vendor.value === '0');
        }

        if (typeSelect) {
            let picking = false;
            const rules = JSON.parse(typeSelect.dataset.typeRules || '{}');
            typeSelect.addEventListener('change', function () {
                if (!picking) {
                    typeSelect.dataset.typePicked = '1';
                }
                showProvider();
            });
            if (nameInput) {
                nameInput.addEventListener('input', function () {
                    if (typeSelect.dataset.typePicked === '1') {
                        return;
                    }
                    const name = nameInput.value.toLowerCase();
                    let type = 'LAN';
                    for (const key in rules) {
                        if (name && new RegExp(rules[key]).test(name)) {
                            type = key;
                            break;
                        }
                    }
                    picking = true;
                    setType(type);
                    picking = false;
                });
            }
        }

        // A suggestion under a field that is empty - "Use 192.168.1.1"
        function suggest(which, input, value) {
            const box = form.querySelector('[data-network-suggest="' + which + '"]');
            if (!box) {
                return;
            }
            box.innerHTML = '';
            box.hidden = !value || input.value.trim() !== '';
            if (box.hidden) {
                return;
            }
            const link = document.createElement('a');
            link.href = '#';
            link.className = 'font-monospace';
            link.textContent = value;
            link.addEventListener('click', function (e) {
                e.preventDefault();
                input.value = value;
                box.hidden = true;
            });
            box.append('Use ', link);
        }

        let timer = null;
        let asked = null;
        function check() {
            const cidr = cidrInput.value.trim();
            if (cidr === asked) {
                return;
            }
            asked = cidr;
            if (cidr === '') {
                info.innerHTML = hint;
                cidrInput.classList.remove('is-valid', 'is-invalid');
                suggest('gateway', gatewayInput, '');
                suggest('range', rangeInput, '');
                return;
            }
            itflowGet('ajax.php', {network_subnet_info: 1, cidr: cidr, client_id: clientId, network_id: networkId}, function (text) {
                if (cidrInput.value.trim() !== cidr) {
                    return;
                }
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    return;
                }
                info.innerHTML = '';
                cidrInput.classList.toggle('is-invalid', !data.ok);
                cidrInput.classList.toggle('is-valid', !!data.ok);
                if (!data.ok) {
                    const line = document.createElement('span');
                    line.className = 'text-danger';
                    line.textContent = cidr.indexOf('/') === -1 ? 'Add the prefix - /24 for 254 hosts, /16 for 65,534.' : 'Not a subnet yet.';
                    info.append(line);
                    suggest('gateway', gatewayInput, '');
                    suggest('range', rangeInput, '');
                    return;
                }
                const line = document.createElement('div');
                line.className = 'text-body-secondary';
                const icon = document.createElement('i');
                icon.className = 'fas fa-fw fa-check text-success me-1';
                line.append(icon, data.size + ' · ' + data.first + ' - ' + data.last);
                info.append(line);
                if (data.cidr !== cidr) {
                    const saved = document.createElement('div');
                    saved.className = 'text-body-secondary';
                    saved.textContent = 'Saved as ' + data.cidr;
                    info.append(saved);
                }
                if (data.overlaps && data.overlaps.length) {
                    const overlap = document.createElement('div');
                    overlap.className = 'text-warning-emphasis';
                    const warn = document.createElement('i');
                    warn.className = 'fas fa-fw fa-clone me-1';
                    overlap.append(warn, 'Overlaps ' + data.overlaps.join(', '));
                    info.append(overlap);
                }
                suggest('gateway', gatewayInput, data.gateway);
                suggest('range', rangeInput, data.range);
            });
        }

        if (cidrInput && info) {
            cidrInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(check, 300);
            });
            [gatewayInput, rangeInput].forEach(function (input) {
                if (input) {
                    input.addEventListener('input', function () {
                        asked = null;
                        clearTimeout(timer);
                        timer = setTimeout(check, 300);
                    });
                }
            });
            if (cidrInput.value.trim() !== '') {
                check();
            }
        }
    });

})();
