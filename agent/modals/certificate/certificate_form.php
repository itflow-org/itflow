<?php
/*
 * Certificate form fields - shared by certificate_add.php and
 * certificate_edit.php, which set $certificate_form_mode ('add' | 'edit'),
 * $client_id (0 = pick the client here), $certificate_id and the
 * $certificate_* values (escaped; empty for a new one) before including
 * this. Posts what certificateFormSet() (functions/certificate.php) reads.
 *
 * Tabs: Certificate (the host it is served on, with Check to see what it
 * serves now; the name, the star, what it is for and the client's domain it
 * belongs to), Details (paste the certificate, or type the issuer and expiry
 * when there is nothing to paste) and Notes. What a check finds - the
 * issuer, dates, names and fingerprint - is saved, never typed.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$client_options = $domain_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT domain_id, domain_name FROM domains
        WHERE domain_client_id = $client_id AND (domain_archived_at IS NULL OR domain_id = " . intval($certificate_domain_id) . ")
        ORDER BY domain_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $domain_options[] = $row;
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

$form_id = 'certificateForm' . ($certificate_form_mode === 'edit' ? $certificate_id : 'New');
$has_pem = trim(html_entity_decode($certificate_public_key, ENT_QUOTES, 'UTF-8')) !== '';

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Certificate"><i class="fas fa-fw fa-lock me-2 d-none d-lg-inline"></i>Certificate</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-file-code me-2 d-none d-lg-inline"></i>Paste or by hand</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- Where it is served, and what it is -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Certificate">
    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required data-certificate-client>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        <?php } ?>
        <div class="col-12">
            <label class="form-label">Host</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-server"></i></span>
                <input type="text" class="form-control" name="domain" placeholder="www.example.com, portal.example.com:8443, fw01.example.local" maxlength="200" value="<?= $certificate_domain ?>"
                    autocomplete="off" spellcheck="false" data-certificate-host <?= $certificate_form_mode === 'edit' ? '' : 'autofocus' ?>>
                <button type="button" class="btn btn-outline-secondary" data-certificate-check-button><i class="fas fa-fw fa-search me-1"></i>Check</button>
            </div>
            <div class="form-text" data-certificate-check>Where it is served - a web address is fine, and a port when it isn't 443. Check shows what it serves now; saving a new one checks it.</div>
            <div class="itflow-certificate-check-result mt-2 d-none" data-certificate-result></div>
        </div>
        <div class="col-md-8">
            <label class="form-label">Name</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                <input type="text" class="form-control" name="name" placeholder="<?= $certificate_domain !== '' ? $certificate_domain : 'The host, if left empty' ?>" maxlength="200" value="<?= $certificate_name ?>" data-certificate-name>
            </div>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input type="checkbox" class="form-check-input" name="favorite" value="1" id="<?= $form_id ?>Favorite" <?= $certificate_favorite ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Favorite"><i class="fas fa-star text-warning me-1"></i>Starred</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Client portal, VPN, firewall admin, Exchange" maxlength="500" value="<?= $certificate_description ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Domain</label>
            <?php if ($client_id) { ?>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                    <select class="form-select select2" name="domain_id" data-certificate-domain>
                        <option value="0">- Matched from the host -</option>
                        <?php foreach ($domain_options as $domain_option) {
                            $domain_option_id = intval($domain_option['domain_id']); ?>
                            <option value="<?= $domain_option_id ?>" <?= intval($certificate_domain_id) === $domain_option_id ? 'selected' : '' ?> data-icon="fas fa-globe"><?= escapeHtml($domain_option['domain_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The client's domain it is for - left to itself, the one the host is under.</div>
            <?php } else { ?>
                <div class="form-text mt-0">Matched to the client's domain the host is under when it's saved.</div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- The certificate itself, or the issuer and expiry by hand -->
<div class="tab-pane fade" id="<?= $form_id ?>Details">
    <label class="form-label">Certificate (PEM)</label>
    <textarea class="form-control font-monospace small" rows="9" name="public_key" spellcheck="false" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----" data-certificate-pem><?= $certificate_public_key ?></textarea>
    <div class="form-text mb-3">For a host ITFlow can't reach. The issuer, dates, names it covers and fingerprint are read from it - a whole chain is fine, only the first certificate is kept. Never paste the private key.</div>

    <fieldset data-certificate-by-hand>
        <div class="small text-body-secondary fw-semibold text-uppercase mb-2">Nothing to paste? By hand</div>
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Issuer</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                    <input type="text" class="form-control" name="issued_by" maxlength="200" placeholder="Let's Encrypt, DigiCert, Sectigo" value="<?= $certificate_issued_by ?>">
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label">Expires</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                    <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= $certificate_expire ?>">
                </div>
            </div>
        </div>
        <div class="form-text" data-certificate-by-hand-note<?= $has_pem ? '' : ' hidden' ?>>Read from the certificate above - clear it to type these.</div>
    </fieldset>
    <?php if ($certificate_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Certificate ID <span class="font-monospace"><?= intval($certificate_id) ?></span></p>
    <?php } ?>
</div>

<!-- Notes -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="10" name="notes" placeholder="Where it is installed, who renews it and how, the CSR and where the private key lives, anything to know before replacing it"><?= $certificate_notes ?></textarea>
</div>

</div>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');
        if (form.dataset.certificateFormWired) {
            return;
        }
        form.dataset.certificateFormWired = '1';

        // A required field on a tab that isn't showing: show that tab and point at it
        var reported = false;
        form.addEventListener('invalid', function (e) {
            var pane = e.target.closest('.tab-pane');
            var tab = pane && form.querySelector('[data-bs-toggle="pill"][href="#' + pane.id + '"]');
            if (!tab || pane.classList.contains('active') || reported) {
                return;
            }
            reported = true;
            tab.addEventListener('shown.bs.tab', function () {
                reported = false;
                e.target.reportValidity();
            }, { once: true });
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }, true);

        // A pasted certificate says the issuer and expiry - typing them is for when there is none
        var pem = form.querySelector('[data-certificate-pem]');
        var byHand = form.querySelector('[data-certificate-by-hand]');
        var byHandNote = form.querySelector('[data-certificate-by-hand-note]');
        function syncByHand() {
            var pasted = pem.value.trim() !== '';
            byHand.disabled = pasted;
            byHandNote.hidden = !pasted;
        }
        pem.addEventListener('input', syncByHand);
        syncByHand();

        // The host: the name it is saved under, and Check - what it serves now
        var host = form.querySelector('[data-certificate-host]');
        var name = form.querySelector('[data-certificate-name]');
        var button = form.querySelector('[data-certificate-check-button]');
        var message = form.querySelector('[data-certificate-check]');
        var result = form.querySelector('[data-certificate-result]');
        var domainSelect = form.querySelector('[data-certificate-domain]');
        var clientSelect = form.querySelector('[data-certificate-client]');
        var clientInput = form.querySelector('input[name="client_id"]');
        var messageDefault = message.textContent;
        var buttonDefault = button.innerHTML;
        var last = '';

        host.addEventListener('input', function () {
            name.placeholder = host.value.trim().replace(/^[a-z][a-z0-9+.\-]*:\/\//i, '').split(/[\/?#\s]/)[0].toLowerCase() || 'The host, if left empty';
        });

        function runCheck(force) {
            var value = host.value.trim();
            var client = clientSelect ? clientSelect.value : (clientInput ? clientInput.value : '');
            var key = value + '|' + client;
            if (!force && key === last) {
                return;
            }
            last = key;
            message.classList.remove('text-danger', 'text-warning', 'text-success');
            result.classList.add('d-none');
            result.innerHTML = '';
            if (!value) {
                message.textContent = messageDefault;
                return;
            }
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Checking';
            message.textContent = 'Connecting to ' + value + '...';
            itflowGet('ajax.php', { certificate_check: 'true', host: value, client_id: client, certificate_id: <?= intval($certificate_id) ?> }, function (data) {
                button.disabled = false;
                button.innerHTML = buttonDefault;
                var response = {};
                try { response = JSON.parse(data); } catch (e) {}
                if (!response.message) {
                    message.textContent = messageDefault;
                    return;
                }
                message.classList.add({ ok: 'text-success', warn: 'text-warning', bad: 'text-danger' }[response.state] || '');
                message.textContent = response.message;
                if (response.html) {
                    result.innerHTML = response.html;
                    result.classList.remove('d-none');
                }
                // The client's domain it is under, unless one is picked already
                if (domainSelect && response.domain_id && (domainSelect.value === '0' || domainSelect.value === '')) {
                    if (domainSelect.itflowSelect) {
                        domainSelect.itflowSelect.setValue(String(response.domain_id));
                    } else {
                        domainSelect.value = String(response.domain_id);
                    }
                }
            });
        }
        button.addEventListener('click', function () { runCheck(true); });
        // Enter in the host checks it, rather than saving half a form
        host.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runCheck(true);
            }
        });
        if (clientSelect) {
            clientSelect.addEventListener('change', function () { if (last) { runCheck(true); } });
        }
    });
</script>
