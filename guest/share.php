<?php

/*
 * A shared link, as the person it was sent to sees it (functions/share.php).
 *
 * The page itself holds nothing secret: the link's secret is after the # in
 * the address, which the browser never sends here, and js/guest_share.js
 * reads it and asks guest/share_ajax.php for everything else - what the link
 * is, the passphrase or emailed code it needs, and the content, which takes
 * a click on Reveal, so a mail filter or chat app fetching the link cannot
 * use it up. Every state the page can be in is here, hidden, and the script
 * shows one and fills it in as text.
 *
 * guest_view_item.php serves this page too, for links made before 2.9.0,
 * which carried their key (and ek) in the query string.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// The address holds the link's secret - it goes nowhere else, and the page into no frame
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/inc_all_guest.php';

$company = shareCompany();
$company_name = escapeHtml($company['name']);
$item_id = intval($_GET['id'] ?? 0);

?>

<div class="itflow-share-page mx-auto py-3 py-md-4" data-share-page data-id="<?= $item_id ?>">

    <div class="text-center mb-4">
        <?php if ($company['logo'] !== '' && is_file($_SERVER['DOCUMENT_ROOT'] . '/uploads/settings/' . basename($company['logo']))) { ?>
            <img class="itflow-share-logo" alt="<?= $company_name ?>" src="/uploads/settings/<?= escapeHtml(rawurlencode(basename($company['logo']))) ?>">
        <?php } else { ?>
            <div class="h4 mb-0 fw-semibold"><?= $company_name ?></div>
        <?php } ?>
    </div>

    <div class="card shadow-sm">

        <!-- Checking the link -->
        <div class="card-body p-4 text-center" data-share-state="loading">
            <div class="spinner-border text-secondary mb-3" role="status" aria-hidden="true"></div>
            <div class="text-body-secondary">Checking the link...</div>
        </div>

        <!-- It no longer works -->
        <div class="card-body p-4 text-center" data-share-state="gone" hidden>
            <div class="itflow-share-icon text-bg-secondary mx-auto mb-3"><i class="fas fa-unlink"></i></div>
            <h1 class="h4">This link no longer works</h1>
            <p class="text-body-secondary mb-0" data-share-gone-reason></p>
            <p class="text-body-secondary mt-3 mb-0">If you still need it, ask <?= $company_name ?> for a new link.</p>
        </div>

        <!-- Destroyed, by the person it was for -->
        <div class="card-body p-4 text-center" data-share-state="destroyed" hidden>
            <div class="itflow-share-icon text-bg-success mx-auto mb-3"><i class="fas fa-check"></i></div>
            <h1 class="h4">Destroyed</h1>
            <p class="text-body-secondary mb-0">The link no longer opens, for you or anyone else. You can close this page.</p>
        </div>

        <!-- A passphrase first -->
        <form class="card-body p-4" data-share-state="passphrase" hidden autocomplete="off">
            <div class="itflow-share-icon text-bg-primary mx-auto mb-3"><i class="fas fa-key"></i></div>
            <h1 class="h4 text-center">Enter the passphrase</h1>
            <p class="text-body-secondary text-center" data-share-intro></p>
            <p class="text-body-secondary text-center small">You should have been given it separately - by phone or text, not with the link.</p>
            <div class="alert alert-danger py-2 small" role="alert" data-share-error hidden></div>
            <label class="form-label" for="sharePassphrase">Passphrase</label>
            <div class="input-group mb-3">
                <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                <input type="password" class="form-control" id="sharePassphrase" name="passphrase" maxlength="200" required autocomplete="off" spellcheck="false" data-toggle="password">
                <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
            </div>
            <button type="submit" class="btn btn-primary w-100 text-bold"><i class="fas fa-unlock me-2"></i>Continue</button>
        </form>

        <!-- A code by email first -->
        <form class="card-body p-4" data-share-state="code" hidden autocomplete="off">
            <div class="itflow-share-icon text-bg-primary mx-auto mb-3"><i class="fas fa-envelope-open-text"></i></div>
            <h1 class="h4 text-center">Check it is you</h1>
            <p class="text-body-secondary text-center" data-share-intro></p>
            <p class="text-body-secondary text-center small">We will email a 6-digit code to <strong data-share-masked-email></strong>.</p>
            <div class="alert alert-danger py-2 small" role="alert" data-share-error hidden></div>
            <div class="alert alert-success py-2 small" role="status" data-share-code-sent hidden>Code sent - it can take a minute to arrive, and works for <?= SHARE_CODE_MINUTES ?> minutes.</div>
            <button type="button" class="btn btn-primary w-100 text-bold mb-3" data-share-send-code><i class="fas fa-paper-plane me-2"></i>Email me a code</button>
            <div data-share-code-entry hidden>
                <label class="form-label" for="shareCode">Code</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-fw fa-hashtag"></i></span>
                    <input type="text" class="form-control font-monospace" id="shareCode" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" placeholder="123456">
                </div>
                <button type="submit" class="btn btn-primary w-100 text-bold"><i class="fas fa-unlock me-2"></i>Continue</button>
                <button type="button" class="btn btn-link w-100 btn-sm mt-2" data-share-resend>Send another code</button>
            </div>
        </form>

        <!-- Ready - one click to open it -->
        <div class="card-body p-4 text-center" data-share-state="ready" hidden>
            <div class="itflow-share-icon text-bg-primary mx-auto mb-3"><i class="fas fa-key" data-share-type-icon></i></div>
            <h1 class="h4" data-share-title></h1>
            <p class="text-body-secondary" data-share-intro></p>
            <div class="itflow-share-note text-start mb-3" data-share-note hidden></div>
            <div class="small text-body-secondary mb-3" data-share-limits></div>
            <div class="alert alert-danger py-2 small text-start" role="alert" data-share-error hidden></div>
            <button type="button" class="btn btn-primary btn-lg text-bold px-4" data-share-reveal><i class="fas fa-eye me-2"></i><span data-share-reveal-label>Reveal</span></button>
            <div class="small text-body-secondary mt-3" data-share-reveal-hint></div>
        </div>

        <!-- A login -->
        <div class="card-body p-4" data-share-state="credential" hidden>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="itflow-share-icon itflow-share-icon-sm text-bg-primary"><i class="fas fa-key"></i></div>
                <h1 class="h5 mb-0 text-break" data-share-name></h1>
            </div>
            <div class="mb-3" data-share-uri-row hidden>
                <label class="form-label small text-body-secondary mb-1">Website</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                    <input type="text" class="form-control" readonly data-share-uri>
                    <a class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer" title="Open" data-share-uri-open hidden><i class="fas fa-fw fa-external-link-alt"></i></a>
                    <button type="button" class="btn btn-outline-secondary" title="Copy" data-share-copy="uri"><i class="far fa-fw fa-copy"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small text-body-secondary mb-1">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                    <input type="text" class="form-control font-monospace" readonly data-share-username>
                    <button type="button" class="btn btn-outline-secondary" title="Copy" data-share-copy="username"><i class="far fa-fw fa-copy"></i></button>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label small text-body-secondary mb-1">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                    <input type="password" class="form-control font-monospace" readonly data-share-password data-toggle="password" aria-label="Password">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                    <button type="button" class="btn btn-outline-secondary" title="Copy" data-share-copy="password"><i class="far fa-fw fa-copy"></i></button>
                </div>
            </div>
            <div class="mb-3" data-share-otp-row hidden>
                <label class="form-label small text-body-secondary mb-1">2FA code</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clock"></i></span>
                    <input type="text" class="form-control font-monospace fs-5 itflow-share-otp" readonly data-share-otp value="------">
                    <button type="button" class="btn btn-outline-secondary" title="Copy" data-share-copy="otp"><i class="far fa-fw fa-copy"></i></button>
                </div>
                <div class="progress mt-1 itflow-share-otp-bar" role="progressbar" aria-label="Time left on this code"><div class="progress-bar" data-share-otp-bar></div></div>
            </div>
            <div class="itflow-share-note mb-3" data-share-note hidden></div>
            <div class="small text-body-secondary mb-3" data-share-after></div>
            <button type="button" class="btn btn-outline-danger w-100" data-share-destroy><i class="fas fa-fire me-2"></i>I have what I need - destroy this link</button>
        </div>

        <!-- A secure note -->
        <div class="card-body p-4" data-share-state="note" hidden>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="itflow-share-icon itflow-share-icon-sm text-bg-primary"><i class="fas fa-user-secret"></i></div>
                <h1 class="h5 mb-0 text-break" data-share-name></h1>
            </div>
            <div class="position-relative mb-3">
                <pre class="itflow-share-secret mb-0" data-share-text></pre>
                <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" title="Copy" data-share-copy="text"><i class="far fa-fw fa-copy"></i></button>
            </div>
            <div class="itflow-share-note mb-3" data-share-note hidden></div>
            <div class="small text-body-secondary mb-3" data-share-after></div>
            <button type="button" class="btn btn-outline-danger w-100" data-share-destroy><i class="fas fa-fire me-2"></i>I have what I need - destroy this link</button>
        </div>

        <!-- A document -->
        <div class="card-body p-4" data-share-state="document" hidden>
            <div class="d-flex align-items-center gap-3 mb-3 d-print-none">
                <div class="itflow-share-icon itflow-share-icon-sm text-bg-primary"><i class="fas fa-file-alt"></i></div>
                <h1 class="h5 mb-0 text-break me-auto" data-share-name></h1>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();"><i class="fas fa-print me-2"></i>Print</button>
            </div>
            <div class="itflow-share-note mb-3 d-print-none" data-share-note hidden></div>
            <div class="prettyContent itflow-share-document" data-share-document></div>
            <div class="small text-body-secondary mt-3 d-print-none" data-share-after></div>
        </div>

        <!-- A file -->
        <div class="card-body p-4 text-center" data-share-state="downloaded" hidden>
            <div class="itflow-share-icon text-bg-success mx-auto mb-3"><i class="fas fa-download"></i></div>
            <h1 class="h4 text-break" data-share-name></h1>
            <p class="text-body-secondary mb-3">Your download has started. Check your downloads folder.</p>
            <div class="small text-body-secondary" data-share-after></div>
        </div>

        <!-- A request - they send us a login -->
        <form class="card-body p-4" data-share-state="request" hidden autocomplete="off">
            <div class="itflow-share-icon text-bg-primary mx-auto mb-3"><i class="fas fa-paper-plane"></i></div>
            <h1 class="h4 text-center" data-share-title></h1>
            <p class="text-body-secondary text-center" data-share-intro></p>
            <div class="itflow-share-note mb-3" data-share-note hidden></div>
            <div class="alert alert-danger py-2 small" role="alert" data-share-error hidden></div>
            <div class="mb-3">
                <label class="form-label" for="shareRequestUri">Website or address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-globe"></i></span>
                    <input type="text" class="form-control" id="shareRequestUri" name="uri" maxlength="500" placeholder="https://portal.example.com" autocomplete="off">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="shareRequestUsername">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                    <input type="text" class="form-control" id="shareRequestUsername" name="username" maxlength="350" autocomplete="off" spellcheck="false">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="shareRequestPassword">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-lock"></i></span>
                    <input type="password" class="form-control font-monospace" id="shareRequestPassword" name="password" maxlength="350" autocomplete="new-password" spellcheck="false" data-toggle="password">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="shareRequestOtp">2FA secret <span class="small text-body-secondary">- if it has one, and you can</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-fw fa-clock"></i></span>
                    <input type="password" class="form-control font-monospace" id="shareRequestOtp" name="otp" maxlength="200" autocomplete="off" spellcheck="false" data-toggle="password" placeholder="The key under &quot;can't scan the code&quot;">
                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="shareRequestNote">Anything we should know</label>
                <textarea class="form-control" id="shareRequestNote" name="note" rows="2" maxlength="2000"></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100 text-bold"><i class="fas fa-lock me-2"></i>Send securely</button>
            <div class="small text-body-secondary text-center mt-3">Encrypted on the way and when stored. It goes to <?= $company_name ?> only, and this link then stops working.</div>
        </form>

        <!-- Sent -->
        <div class="card-body p-4 text-center" data-share-state="sent" hidden>
            <div class="itflow-share-icon text-bg-success mx-auto mb-3"><i class="fas fa-check"></i></div>
            <h1 class="h4">Sent - thank you</h1>
            <p class="text-body-secondary mb-0"><?= $company_name ?> has it. You can close this page.</p>
        </div>

    </div>

    <div class="text-center small text-body-secondary mt-3 d-print-none">
        <i class="fas fa-shield-alt me-1"></i>A private link from <?= $company_name ?><?= $company['phone'] !== '' ? ' &middot; <i class="fas fa-phone ms-1 me-1"></i>' . escapeHtml($company['phone']) : '' ?><?= $company['website'] !== '' ? ' &middot; ' . escapeHtml($company['website']) : '' ?>
        <div class="mt-1">If this reached you by mistake, close it and let us know.</div>
    </div>
</div>

<script src="/js/pretty_content.js"></script>
<script src="/js/guest_share.js"></script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
