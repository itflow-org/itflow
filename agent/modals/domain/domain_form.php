<?php
/*
 * Domain form fields - shared by domain_add.php and domain_edit.php, which
 * set $domain_form_mode ('add' | 'edit'), $client_id (0 = pick the client
 * here), $domain_id and the $domain_* values (escaped; empty for a new one),
 * $domain_tag_ids and $domain_vendor_ids (role => vendor id) before
 * including this. Posts what domainFormSet() (functions/domain.php) reads.
 *
 * Tabs: Domain (the name, checked as it is typed - or several pasted at once
 * when adding - what it is for, the star, the expiry and auto-renew), Who
 * (the registrar and who hosts DNS, the website and email), Details (tags)
 * and Notes. The records themselves are looked up, never typed, and show in
 * the domain's quick look.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$client_options = $vendor_options = [];
if ($client_id) {
    $vendor_options = domainVendorChoices($client_id, array_values($domain_vendor_ids));
    $client_name_for_vendors = getFieldById('clients', $client_id, 'client_name');
} else {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

$form_id = 'domainForm' . ($domain_form_mode === 'edit' ? $domain_id : 'New');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Domain"><i class="fas fa-fw fa-globe me-2 d-none d-lg-inline"></i>Domain</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Who"><i class="fas fa-fw fa-building me-2 d-none d-lg-inline"></i>Who</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-tags me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- The domain -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Domain">
    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required data-domain-client>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Set the registrar and hosts from the domain once it's saved.</div>
            </div>
        <?php } ?>
        <?php if ($domain_form_mode === 'add') { ?>
            <div class="col-12">
                <div class="btn-group btn-group-sm" role="group" aria-label="How many">
                    <input type="radio" class="btn-check" name="domain_how_many" id="<?= $form_id ?>One" value="one" checked data-domain-mode>
                    <label class="btn btn-outline-secondary" for="<?= $form_id ?>One"><i class="fas fa-globe me-1"></i>One domain</label>
                    <input type="radio" class="btn-check" name="domain_how_many" id="<?= $form_id ?>Several" value="several" data-domain-mode>
                    <label class="btn btn-outline-secondary" for="<?= $form_id ?>Several"><i class="fas fa-list me-1"></i>Several at once</label>
                </div>
            </div>
        <?php } ?>
        <div class="col-md-8" data-domain-one>
            <label class="form-label">Domain name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                <input type="text" class="form-control" name="name" placeholder="example.com" maxlength="200" value="<?= $domain_name ?>" required
                    autocomplete="off" spellcheck="false" data-domain-name <?= $domain_form_mode === 'edit' ? '' : 'autofocus' ?>>
            </div>
            <div class="form-text" data-domain-check>Paste a web address if that is easier - it is tidied to the domain.</div>
        </div>
        <div class="col-md-4 d-flex align-items-end" data-domain-one>
            <div class="form-check form-switch mb-2">
                <input type="checkbox" class="form-check-input" name="favorite" value="1" id="<?= $form_id ?>Favorite" <?= $domain_favorite ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Favorite"><i class="fas fa-star text-warning me-1"></i>Starred</label>
            </div>
        </div>
        <?php if ($domain_form_mode === 'add') { ?>
            <div class="col-12 d-none" data-domain-several>
                <label class="form-label">Domain names <strong class="text-danger">*</strong></label>
                <textarea class="form-control font-monospace" name="names" rows="8" placeholder="example.com&#10;example.net&#10;shop.example.org" disabled data-domain-names></textarea>
                <div class="form-text">One per line - or separated by spaces or commas. Each is looked up as it is added; ones already on the client are skipped. The registrar, hosts, auto-renew and tags you set here go on all of them.</div>
            </div>
        <?php } ?>
        <div class="col-12" data-domain-one>
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="Primary domain - website and email" maxlength="500" value="<?= $domain_description ?>">
            </div>
        </div>
        <div class="col-md-6" data-domain-one>
            <label class="form-label">Expires</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-calendar-times"></i></span>
                <input type="date" class="form-control" name="expire" max="2999-12-31" value="<?= $domain_expire ?>">
            </div>
            <div class="form-text">Leave it empty - it is looked up from the registry. Set it by hand for registries that don't publish it.</div>
        </div>
        <div class="col-md-6 d-flex align-items-center">
            <div class="form-check form-switch mt-md-3">
                <input type="checkbox" class="form-check-input" name="auto_renew" value="1" id="<?= $form_id ?>AutoRenew" <?= $domain_auto_renew ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>AutoRenew"><i class="fas fa-sync-alt text-info me-1"></i>Auto-renews at the registrar</label>
            </div>
        </div>
    </div>
</div>

<!-- Who registers and hosts it -->
<div class="tab-pane fade" id="<?= $form_id ?>Who">
    <?php if ($client_id) { ?>
        <div class="row g-3">
            <?php foreach (DOMAIN_ROLES as $role => $info) {
                $picked = intval($domain_vendor_ids[$role] ?? 0); ?>
                <div class="col-md-6">
                    <label class="form-label"><?= $role === 'registrar' ? 'Registrar' : $info['label'] . ' host' ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw <?= $info['icon'] ?>"></i></span>
                        <select class="form-select select2" name="<?= $role ?>" data-domain-vendor="<?= $role ?>">
                            <option value="0">- Nobody -</option>
                            <?php foreach ([1, 0] as $theirs) {
                                $group = array_filter($vendor_options, function ($vendor) use ($theirs) { return (intval($vendor['vendor_client_id']) !== 0) === (bool) $theirs; });
                                if (!$group) {
                                    continue;
                                } ?>
                                <optgroup label="<?= $theirs ? escapeHtml($client_name_for_vendors) . "'s vendors" : 'Ours' ?>">
                                    <?php foreach ($group as $vendor) {
                                        $vendor_id = intval($vendor['vendor_id']); ?>
                                        <option value="<?= $vendor_id ?>" <?= $picked === $vendor_id ? 'selected' : '' ?><?= selectOptionVendor($vendor['vendor_name'], $vendor['vendor_description']) ?>><?= escapeHtml($vendor['vendor_name']) ?></option>
                                    <?php } ?>
                                </optgroup>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-domain-same><i class="fas fa-clone me-2"></i>Registrar does it all</button>
                <span class="form-text mt-0">Someone missing? Add them as a vendor - the client's own, or one of ours if we host it.</span>
            </div>
        </div>
    <?php } else { ?>
        <div class="itflow-asset-empty">Pick the client first - the registrar and hosts are their vendors, or ours.</div>
    <?php } ?>
</div>

<!-- Tags -->
<div class="tab-pane fade" id="<?= $form_id ?>Details">
    <label class="form-label">Tags</label>
    <input type="hidden" name="tags_form" value="1">
    <div class="input-group">
        <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
        <select class="form-select select2" name="tags[]" data-placeholder="Primary, Parked, Client-owned, Managed DNS" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
            <?= tagOptionsHtml('domain', $domain_tag_ids) ?>
        </select>
    </div>
    <?php if ($domain_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Domain ID <span class="font-monospace"><?= $domain_id ?></span></p>
    <?php } ?>
</div>

<!-- Notes -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="10" name="notes" placeholder="Who owns the registrar account, how renewal is paid, transfer locks, anything to know before touching DNS"><?= $domain_notes ?></textarea>
</div>

</div>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');
        if (form.dataset.domainFormWired) {
            return;
        }
        form.dataset.domainFormWired = '1';

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

        // One domain, or several pasted at once - only the one in use posts and is required
        var nameInput = form.querySelector('[data-domain-name]');
        var namesInput = form.querySelector('[data-domain-names]');
        form.querySelectorAll('[data-domain-mode]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var several = radio.value === 'several' && radio.checked;
                // A class, not [hidden] - d-flex on some of them would win over it
                form.querySelectorAll('[data-domain-one]').forEach(function (el) { el.classList.toggle('d-none', several); });
                form.querySelectorAll('[data-domain-several]').forEach(function (el) { el.classList.toggle('d-none', !several); });
                nameInput.disabled = several;
                nameInput.required = !several;
                namesInput.disabled = !several;
                namesInput.required = several;
                (several ? namesInput : nameInput).focus();
            });
        });

        // The name, checked as it is typed: what it is saved as, whether it answers,
        // whether it is a subdomain, whether the client has it already
        var check = form.querySelector('[data-domain-check]');
        var checkDefault = check.textContent;
        var clientSelect = form.querySelector('[data-domain-client]');
        var clientInput = form.querySelector('input[name="client_id"]');
        var timer = null;
        var last = '';
        function runCheck() {
            var value = nameInput.value.trim();
            var client = clientSelect ? clientSelect.value : (clientInput ? clientInput.value : '');
            var key = value + '|' + client;
            if (key === last) {
                return;
            }
            last = key;
            check.classList.remove('text-danger', 'text-warning', 'text-success');
            if (!value) {
                check.textContent = checkDefault;
                return;
            }
            itflowGet('ajax.php', { domain_check: 'true', domain: value, client_id: client, domain_id: <?= intval($domain_id) ?> }, function (data) {
                var result = {};
                try { result = JSON.parse(data); } catch (e) {}
                if (!result.message) {
                    check.textContent = checkDefault;
                    return;
                }
                check.classList.add({ ok: 'text-success', warn: 'text-warning', bad: 'text-danger' }[result.state] || '');
                check.textContent = result.message;
            });
        }
        nameInput.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(runCheck, 600);
        });
        nameInput.addEventListener('blur', runCheck);
        if (clientSelect) {
            clientSelect.addEventListener('change', function () { last = ''; runCheck(); });
        }

        // The registrar does it all - DNS, the website and email with them too
        var same = form.querySelector('[data-domain-same]');
        if (same) {
            same.addEventListener('click', function () {
                var registrar = form.querySelector('[data-domain-vendor="registrar"]');
                form.querySelectorAll('[data-domain-vendor]').forEach(function (select) {
                    if (select === registrar) {
                        return;
                    }
                    if (select.itflowSelect) {
                        select.itflowSelect.setValue(registrar.value);
                    } else {
                        select.value = registrar.value;
                    }
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        }
    });
</script>
