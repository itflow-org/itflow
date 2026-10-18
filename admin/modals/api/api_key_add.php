<?php

/*
 * New API Key - its name, the day it stops working and the agent it acts as
 * (with what they can open, shown as they are picked), then the key and the
 * credential decryption password to copy - shown only here.
 */

require_once '../../includes/modal_header.php';

$key = randomString(32);
$decryptPW = randomString(32);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i>New API Key</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="key" value="<?= $key ?>">
    <input type="hidden" name="password" value="<?= $decryptPW ?>">
    <div class="modal-body">

        <ul class="nav nav-pills nav-justified mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" href="#pills-api-details"><i class="fas fa-fw fa-sliders-h me-1"></i>Details</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" href="#pills-api-keys"><i class="fas fa-fw fa-key me-1"></i>Key</a>
            </li>
        </ul>

        <div class="tab-content">

            <div class="tab-pane fade show active" id="pills-api-details">
                <div class="mb-3">
                    <label>Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-sticky-note"></i></span>
                        <input type="text" class="form-control" name="name" placeholder="What uses it - the RMM, a PSA sync" maxlength="255" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Expires <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="date" class="form-control" name="expire" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" max="2999-12-31" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                    </div>
                    <div class="form-text">It works until the start of that day.</div>
                </div>

                <?= apiKeyAgentFieldHtml() ?>
            </div>

            <div class="tab-pane fade" id="pills-api-keys">
                <div class="alert alert-warning small py-2"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Copy both now - they aren't shown again.</div>
                <div class="mb-3">
                    <label>API key</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        <input type="text" class="form-control font-monospace" value="<?= $key ?>" readonly>
                        <button class="btn btn-outline-secondary clipboardjs" title="Copy API key" type="button" data-clipboard-text="<?= $key ?>"><i class="far fa-fw fa-copy"></i></button>
                    </div>
                </div>

                <div class="mb-3">
                    <label>Credential decryption password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-unlock-alt"></i></span>
                        <input type="text" class="form-control font-monospace" value="<?= $decryptPW ?>" readonly>
                        <button class="btn btn-outline-secondary clipboardjs" title="Copy decryption password" type="button" data-clipboard-text="<?= $decryptPW ?>"><i class="far fa-fw fa-copy"></i></button>
                    </div>
                    <div class="form-text">Sent with calls that read or write credentials.</div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ack" value="1" id="apiKeyAck" required>
                    <label class="form-check-label" for="apiKeyAck">I have copied the key and the password <strong class="text-danger">*</strong></label>
                </div>
            </div>

        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="add_api_key" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    </div>
    <script>
        // Create on the Details tab with the copied box not ticked: show the Key tab, so the browser can point at it
        (function () {
            const form = document.currentScript.closest('form');
            form.addEventListener('invalid', function (event) {
                const pane = event.target.closest('.tab-pane');
                if (pane && !pane.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(form.querySelector('[href="#' + pane.id + '"]')).show();
                }
            }, true);
        })();
    </script>
</form>

<?php
require_once '../../../includes/modal_footer.php';
