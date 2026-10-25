<?php

/*
 * The signing fields - full name, job title, the signature drawn or typed,
 * and the box to tick - for the guest signing page, the client portal and
 * the agent's Countersign window. Set $signature_pad before including:
 *
 *   id     a prefix for the fields' ids, unique on the page
 *   name   the name to start with
 *   title  the job title to start with
 *   agree  what ticking the box says, raw
 *
 * js/signature_pad.js draws on the pad and puts the picture in the form as
 * it is sent; the server checks it all again (contractSignatureFromInput()).
 * Classes and data- attributes only - the client portal allows no inline
 * styles or scripts.
 */

isset($signature_pad) || die("Direct file access is not allowed");

$signature_pad_id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($signature_pad['id'] ?? 'sign'));

?>
<div data-signature-pad>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <label class="form-label" for="<?= $signature_pad_id ?>Name">Full name <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                <input type="text" class="form-control" id="<?= $signature_pad_id ?>Name" name="signer_name" maxlength="200" autocomplete="name" required
                    value="<?= escapeHtml((string) ($signature_pad['name'] ?? '')) ?>" data-signature-name>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="<?= $signature_pad_id ?>Title">Job title</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span>
                <input type="text" class="form-control" id="<?= $signature_pad_id ?>Title" name="signer_title" maxlength="200" autocomplete="organization-title"
                    value="<?= escapeHtml((string) ($signature_pad['title'] ?? '')) ?>">
            </div>
        </div>
    </div>

    <input type="hidden" name="signature_mode" value="draw" data-signature-mode>
    <input type="hidden" name="signature" value="" data-signature-data>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <span class="form-label mb-0">Signature <strong class="text-danger">*</strong></span>
        <div class="btn-group btn-group-sm" role="group" aria-label="How to sign">
            <button type="button" class="btn btn-outline-secondary active" data-signature-tab="draw" aria-pressed="true"><i class="fas fa-pen-nib me-1"></i>Draw</button>
            <button type="button" class="btn btn-outline-secondary" data-signature-tab="type" aria-pressed="false"><i class="fas fa-keyboard me-1"></i>Type</button>
        </div>
    </div>
    <div data-signature-panel="draw">
        <div class="itflow-signature-canvas-wrap">
            <canvas class="itflow-signature-canvas" data-signature-canvas role="img" aria-label="Draw your signature here"></canvas>
            <div class="itflow-signature-line" aria-hidden="true"></div>
            <span class="itflow-signature-hint" data-signature-hint>Sign here with your mouse, finger or pen</span>
        </div>
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-link btn-sm px-0" data-signature-clear><i class="fas fa-eraser me-1"></i>Clear</button>
        </div>
    </div>
    <div data-signature-panel="type" hidden>
        <div class="itflow-signature-typed-preview is-empty" data-signature-typed>Write your full name above</div>
        <div class="form-text mb-2">Your name, typed, is your signature.</div>
    </div>

    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="<?= $signature_pad_id ?>Agree" name="agree" value="1" required>
        <label class="form-check-label" for="<?= $signature_pad_id ?>Agree"><?= escapeHtml((string) ($signature_pad['agree'] ?? 'I agree to sign this electronically')) ?></label>
    </div>
</div>
