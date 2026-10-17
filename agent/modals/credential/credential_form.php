<?php
/*
 * Credential form fields - shared by credential_add.php and
 * credential_edit.php, which set $credential_form_mode ('add' | 'edit'),
 * $client_id (0 = pick the client here), $credential_id and the
 * $credential_* values (escaped; the username, password and 2FA seed already
 * decrypted for an edit, empty for a new one), $credential_tag_ids,
 * $credential_contact_id and $credential_asset_id, before including this.
 * Posts what credentialFormSet() (functions/credential.php) reads.
 *
 * Tabs: Login (name, star, username, password with a generator and how
 * strong it is, the 2FA secret with the code it makes, where it opens),
 * Belongs to (the asset and contact it is for), Details (what it is, tags)
 * and Notes.
 *
 * Should not be accessed directly.
 */

isset($mysqli) || die("Direct file access is not allowed");

$client_options = $contact_options = $asset_options = [];
if ($client_id) {
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_photo, contact_client_id, contact_archived_at FROM contacts
        WHERE contact_client_id = $client_id AND (contact_archived_at IS NULL OR contact_id = " . intval($credential_contact_id) . ")
        ORDER BY contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $contact_options[] = $row;
    }
    $sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_make, asset_model, asset_archived_at, location_name FROM assets
        LEFT JOIN locations ON location_id = asset_location_id
        WHERE asset_client_id = $client_id AND (asset_archived_at IS NULL OR asset_id = " . intval($credential_asset_id) . ")
        ORDER BY asset_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $asset_options[] = $row;
    }
} else {
    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $client_options[] = $row;
    }
}

$form_id = 'credentialForm' . ($credential_form_mode === 'edit' ? $credential_id : 'New');

?>

<ul class="nav nav-pills nav-fill flex-nowrap overflow-auto mb-3 itflow-asset-form-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#<?= $form_id ?>Login"><i class="fas fa-fw fa-key me-2 d-none d-lg-inline"></i>Login</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Belongs"><i class="fas fa-fw fa-link me-2 d-none d-lg-inline"></i>Belongs to</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Details"><i class="fas fa-fw fa-tags me-2 d-none d-lg-inline"></i>Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#<?= $form_id ?>Notes"><i class="fas fa-fw fa-sticky-note me-2 d-none d-lg-inline"></i>Notes</a>
    </li>
</ul>

<div class="tab-content" id="<?= $form_id ?>">

<!-- The login itself -->
<div class="tab-pane fade show active" id="<?= $form_id ?>Login">
    <div class="row g-3">
        <?php if (!$client_id) { ?>
            <div class="col-12">
                <label class="form-label">Client <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="client_id" required>
                        <option value="">- Select Client -</option>
                        <?php foreach ($client_options as $client_option) { ?>
                            <option value="<?= intval($client_option['client_id']) ?>"<?= selectOptionClient($client_option['client_id'], $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Pick the asset and contact it belongs to once it's saved.</div>
            </div>
        <?php } ?>
        <div class="col-md-8">
            <label class="form-label">Name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-key"></i></span>
                <input type="text" class="form-control" name="name" placeholder="Firewall admin, Microsoft 365 break glass, Guest Wi-Fi" maxlength="200" value="<?= $credential_name ?>" required <?= $credential_form_mode === 'edit' ? '' : 'autofocus' ?>>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <div class="form-check form-switch mt-md-2">
                <input class="form-check-input" type="checkbox" role="switch" name="favorite" value="1" id="<?= $form_id ?>Favorite" <?= $credential_favorite ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $form_id ?>Favorite"><i class="fas fa-star text-warning me-1"></i>Starred</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Username / ID</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <input type="text" class="form-control" name="username" placeholder="Username, email or ID" maxlength="350" value="<?= $credential_username ?>" autocomplete="off">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Password / key</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                <input type="password" class="form-control font-monospace" data-toggle="password" name="password" placeholder="Password, key or PSK" maxlength="350" value="<?= $credential_password ?>" autocomplete="new-password" data-credential-password>
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                <button class="btn btn-outline-secondary clipboardjs" title="Copy password" type="button" data-clipboard-target="input[name=password]"><i class="far fa-fw fa-copy"></i></button>
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Make one up"><i class="fas fa-fw fa-magic"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="#" data-credential-generate="passphrase"><i class="fas fa-fw fa-font text-secondary me-2"></i>Passphrase <small class="text-body-secondary">easy to read out</small></a>
                    <a class="dropdown-item" href="#" data-credential-generate="20"><i class="fas fa-fw fa-random text-secondary me-2"></i>Random, 20 characters</a>
                    <a class="dropdown-item" href="#" data-credential-generate="32"><i class="fas fa-fw fa-random text-secondary me-2"></i>Random, 32 characters</a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-1 small" data-credential-strength hidden>
                <div class="progress flex-grow-1" style="height: .35rem;"><div class="progress-bar" role="progressbar"></div></div>
                <span class="text-body-secondary text-nowrap" data-credential-strength-label></span>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">2FA secret</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="password" class="form-control font-monospace" data-toggle="password" name="otp_secret" placeholder="The key behind the QR code, or the otpauth:// link" maxlength="200" value="<?= $credential_otp_secret ?>" autocomplete="off" data-credential-otp-input>
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
            </div>
            <div class="form-text" data-credential-otp-check>Every agent who can see this credential gets the current code, so nobody needs the phone.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Opens at</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                <input type="text" class="form-control" name="uri" placeholder="https://192.168.1.1, rdp://server" maxlength="500" value="<?= $credential_uri ?>">
                <button class="btn btn-outline-secondary clipboardjs" title="Copy URI" type="button" data-clipboard-target="input[name=uri]"><i class="far fa-fw fa-copy"></i></button>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Also at</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-link"></i></span>
                <input type="text" class="form-control" name="uri_2" placeholder="https://server.company.com:5001" maxlength="500" value="<?= $credential_uri_2 ?>">
                <button class="btn btn-outline-secondary clipboardjs" title="Copy URI 2" type="button" data-clipboard-target="input[name=uri_2]"><i class="far fa-fw fa-copy"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- What it is for -->
<div class="tab-pane fade" id="<?= $form_id ?>Belongs">
    <?php if ($client_id) { ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Asset</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                    <select class="form-select select2" name="asset">
                        <option value="0">- No asset -</option>
                        <?php foreach ($asset_options as $asset_option) {
                            $asset_option_id = intval($asset_option['asset_id']); ?>
                            <option value="<?= $asset_option_id ?>" <?= intval($credential_asset_id) === $asset_option_id ? 'selected' : '' ?><?= selectOptionAsset($asset_option['asset_type'], implode(' · ', array_filter([trim($asset_option['asset_make'] . ' ' . $asset_option['asset_model']), $asset_option['location_name']]))) ?>><?= escapeHtml($asset_option['asset_name']) ?><?= $asset_option['asset_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">The device this login is for - it shows on the asset's Credentials tab.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Contact</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                    <select class="form-select select2" name="contact">
                        <option value="0">- Nobody -</option>
                        <?php foreach ($contact_options as $contact_option) {
                            $contact_option_id = intval($contact_option['contact_id']); ?>
                            <option value="<?= $contact_option_id ?>" <?= intval($credential_contact_id) === $contact_option_id ? 'selected' : '' ?><?= selectOptionContact($contact_option['contact_client_id'], $contact_option['contact_name'], $contact_option['contact_photo'], $contact_option['contact_title'], $contact_option_id) ?>><?= escapeHtml($contact_option['contact_name']) ?><?= $contact_option['contact_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-text">Whose login it is - it shows on their contact page.</div>
            </div>
            <div class="col-12">
                <div class="form-text mt-0"><i class="fas fa-info-circle me-1"></i>Vendors and licenses link from the credential's quick look, or from the vendor or license itself.</div>
            </div>
        </div>
    <?php } else { ?>
        <div class="itflow-asset-empty">Pick the client first - the asset and contact are theirs.</div>
    <?php } ?>
</div>

<!-- What it is -->
<div class="tab-pane fade" id="<?= $form_id ?>Details">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" placeholder="What it opens, and who uses it" maxlength="500" value="<?= $credential_description ?>">
            </div>
        </div>
        <div class="col-12">
            <label class="form-label">Tags</label>
            <input type="hidden" name="tags_form" value="1">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" data-placeholder="Domain Admin, Break Glass, Vendor Portal" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
                    <?= tagOptionsHtml('credential', $credential_tag_ids) ?>
                </select>
            </div>
        </div>
    </div>
    <?php if ($credential_form_mode === 'edit') { ?>
        <p class="text-body-secondary small text-end mt-3 mb-0">Credential ID <span class="font-monospace"><?= $credential_id ?></span></p>
    <?php } ?>
</div>

<!-- Everything else -->
<div class="tab-pane fade" id="<?= $form_id ?>Notes">
    <label class="form-label">Notes</label>
    <textarea class="form-control" rows="10" name="note" placeholder="What it is for, who may use it, when it was last rotated and by whom"><?= $credential_note ?></textarea>
    <div class="form-text">Notes are not encrypted - keep recovery codes and second passwords in their own credential.</div>
</div>

</div>

<script>
    itflowReady(function () {
        var form = document.getElementById(<?= json_encode($form_id) ?>).closest('form');
        if (form.dataset.credentialFormWired) {
            return;
        }
        form.dataset.credentialFormWired = '1';

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

        var password = form.querySelector('[data-credential-password]');
        var meter = form.querySelector('[data-credential-strength]');
        var bar = meter.querySelector('.progress-bar');
        var label = meter.querySelector('[data-credential-strength-label]');

        // How strong it is - length and the kinds of character in it, roughly in bits
        function strength() {
            var value = password.value;
            meter.hidden = value === '';
            if (!value) {
                return;
            }
            var pool = 0;
            if (/[a-z]/.test(value)) pool += 26;
            if (/[A-Z]/.test(value)) pool += 26;
            if (/[0-9]/.test(value)) pool += 10;
            if (/[^a-zA-Z0-9]/.test(value)) pool += 32;
            var bits = Math.round(value.length * Math.log2(Math.max(pool, 2)));
            var levels = [[40, 'Weak', 'danger', 25], [60, 'Fair', 'warning', 50], [80, 'Good', 'info', 75], [Infinity, 'Strong', 'success', 100]];
            var level = levels.find(function (l) { return bits < l[0]; });
            bar.className = 'progress-bar bg-' + level[2];
            bar.style.width = level[3] + '%';
            label.textContent = level[1] + ' · ' + value.length + ' characters';
        }
        password.addEventListener('input', strength);
        strength();

        // Make one up - a passphrase from the server, or random characters made here
        function randomPassword(length) {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*-_=+?';
            var bytes = new Uint32Array(length);
            window.crypto.getRandomValues(bytes);
            return Array.prototype.map.call(bytes, function (b) { return chars[b % chars.length]; }).join('');
        }
        form.querySelectorAll('[data-credential-generate]').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                var kind = item.dataset.credentialGenerate;
                function set(value) {
                    password.value = value;
                    password.type = 'text';
                    password.dispatchEvent(new Event('input'));
                }
                if (kind === 'passphrase') {
                    itflowGet('ajax.php', { get_readable_pass: 'true' }, function (data) {
                        set(JSON.parse(data));
                    });
                } else {
                    set(randomPassword(parseInt(kind, 10)));
                }
            });
        });

        // The 2FA secret: say whether it is one, and show the code it makes now - worked
        // out here (where the browser allows it), so a mistyped secret is caught before saving
        var otp = form.querySelector('[data-credential-otp-input]');
        var check = form.querySelector('[data-credential-otp-check]');
        var checkDefault = check.textContent;
        function seed(value) {
            value = value.trim();
            if (/^otpauth:\/\//i.test(value)) {
                var match = value.match(/[?&]secret=([^&]+)/i);
                value = match ? decodeURIComponent(match[1]) : '';
            }
            value = value.replace(/[\s-]+/g, '').toUpperCase().replace(/=+$/, '');
            return /^[A-Z2-7]{16,}$/.test(value) ? value : null;
        }
        function base32(value) {
            var alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', bits = '', bytes = [];
            value.split('').forEach(function (c) { bits += alphabet.indexOf(c).toString(2).padStart(5, '0'); });
            for (var i = 0; i + 8 <= bits.length; i += 8) {
                bytes.push(parseInt(bits.substr(i, 8), 2));
            }
            return new Uint8Array(bytes);
        }
        function totp(secret) {
            var counter = Math.floor(Date.now() / 30000);
            var message = new Uint8Array(8);
            for (var i = 7; i >= 0; i--) {
                message[i] = counter & 0xff;
                counter = Math.floor(counter / 256);
            }
            return crypto.subtle.importKey('raw', base32(secret), { name: 'HMAC', hash: 'SHA-1' }, false, ['sign'])
                .then(function (key) { return crypto.subtle.sign('HMAC', key, message); })
                .then(function (signature) {
                    var h = new Uint8Array(signature), o = h[h.length - 1] & 0xf;
                    var code = ((h[o] & 0x7f) << 24 | h[o + 1] << 16 | h[o + 2] << 8 | h[o + 3]) % 1000000;
                    return String(code).padStart(6, '0');
                });
        }
        function checkOtp() {
            var value = otp.value.trim();
            check.classList.remove('text-danger', 'text-success');
            if (!value) {
                check.textContent = checkDefault;
                return;
            }
            var secret = seed(value);
            if (!secret) {
                check.classList.add('text-danger');
                check.textContent = 'That is not a 2FA secret - it is the long key shown under "can\'t scan the code", or the otpauth:// link.';
                return;
            }
            if (!window.crypto || !crypto.subtle) {
                check.textContent = 'Looks like a 2FA secret.';
                return;
            }
            totp(secret).then(function (code) {
                check.classList.add('text-success');
                check.textContent = 'The code now is ' + code.slice(0, 3) + ' ' + code.slice(3) + ' - check it matches the app.';
            });
        }
        otp.addEventListener('input', checkOtp);
        checkOtp();
    });
</script>
