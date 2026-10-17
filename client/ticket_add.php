<?php
/*
 * Client Portal
 * New ticket - what's wrong, how much it is getting in the way, which device,
 * and any screenshots or files, with what happens next alongside. ?subject=
 * fills the subject in - the Ask us links on invoices and quotes.
 *
 * No Content-Security-Policy here: the editor needs inline styles.
 */

require_once 'includes/inc_all.php';

// The devices they can pick: their own, or the company's for a primary or
// technical contact - add_ticket in post.php holds the choice to the same rule
$sees_all = portalSeesAllTickets();
$assets = [];
$sql = mysqli_query($mysqli, "SELECT asset_id, asset_name, asset_type, asset_contact_id FROM assets
    WHERE asset_client_id = $session_client_id AND asset_archived_at IS NULL" . ($sees_all ? '' : " AND asset_contact_id = $session_contact_id") . "
    ORDER BY asset_contact_id = $session_contact_id DESC, asset_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $assets[] = $row;
}

$categories = mysqli_query($mysqli, "SELECT category_id, category_name, category_color, category_icon FROM categories
    WHERE category_type = 'Ticket' AND category_archived_at IS NULL ORDER BY category_name ASC");

$priorities = [
    'Low' => ['var(--bs-info)', 'Whenever you can get to it'],
    'Medium' => ['var(--bs-warning)', 'It is getting in the way'],
    'High' => ['var(--bs-danger)', 'It is stopping my work'],
    'Urgent' => ['var(--bs-emphasis-color)', 'It is stopping everyone'],
];

$company = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT company_phone, company_phone_country_code FROM companies WHERE company_id = 1"));
?>

<a class="itflow-portal-back" href="tickets.php"><i class="fas fa-chevron-left"></i>Tickets</a>

<div class="itflow-portal-page-head">
    <div>
        <h1>New ticket</h1>
        <p>Tell us what is wrong and we will get on it.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <form class="itflow-portal-panel itflow-portal-panel-body" action="post.php" method="post" enctype="multipart/form-data" data-portal-once>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label class="form-label" for="ticketSubject">Subject <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" id="ticketSubject" name="subject" placeholder="In a few words - &quot;Outlook keeps asking for my password&quot;" maxlength="500" required autofocus value="<?= escapeHtml(mb_substr(trim((string) ($_GET['subject'] ?? '')), 0, 500)) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">What is happening? <strong class="text-danger">*</strong></label>
                <textarea class="form-control tinymce" name="details" data-required placeholder="What you were doing, any error message, and when it started"></textarea>
                <div class="invalid-feedback d-block" data-editor-required-note hidden>Tell us what is happening.</div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">How much is it getting in the way?</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                        <select class="form-select select2" name="priority" required>
                            <?php foreach ($priorities as $priority => [$color, $hint]) { ?>
                                <option value="<?= $priority ?>" data-color="<?= $color ?>" data-description="<?= escapeHtml($hint) ?>"<?= $priority === 'Medium' ? ' selected' : '' ?>><?= $priority ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">What is it about?</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-select select2" name="category">
                            <option value="0">Not sure</option>
                            <?php while ($row = mysqli_fetch_assoc($categories)) { ?>
                                <option value="<?= intval($row['category_id']) ?>"<?= selectOptionColor($row['category_color'] ?? '', $row['category_icon'] ?? '') ?>><?= escapeHtml($row['category_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($assets) { ?>
                <div class="mb-3">
                    <label class="form-label">Which device?</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-desktop"></i></span>
                        <select class="form-select select2" name="asset">
                            <option value="0">None, or not sure</option>
                            <?php foreach ($assets as $asset) { ?>
                                <option value="<?= intval($asset['asset_id']) ?>" data-icon="fas fa-<?= getAssetIcon($asset['asset_type']) ?>"
                                    data-description="<?= escapeHtml($asset['asset_type'] . (intval($asset['asset_contact_id']) === $session_contact_id ? ' - yours' : '')) ?>"><?= escapeHtml($asset['asset_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>

            <div class="mb-4">
                <label class="form-label">Screenshots or files</label>
                <div class="itflow-portal-drop" data-portal-drop>
                    <input type="file" name="file[]" multiple id="ticketFiles" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                    <i class="fas fa-paperclip me-2"></i>Drop files here, or <label for="ticketFiles">choose files</label>
                    <div class="itflow-portal-files" data-portal-files hidden></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" name="add_ticket"><i class="fas fa-paper-plane me-2"></i>Send ticket</button>
        </form>
    </div>

    <div class="col-lg-4">
        <section class="itflow-portal-panel">
            <div class="itflow-portal-panel-head">
                <h2><i class="fas fa-route"></i>What happens next</h2>
            </div>
            <div class="itflow-portal-panel-body">
                <ol class="ps-3 mb-0 small">
                    <li class="mb-2">We get your ticket straight away and pick it up.</li>
                    <li class="mb-2">You hear from us by email, and every reply shows on the ticket here.</li>
                    <li class="mb-2">Answer us by replying to the email or on the ticket - whichever is easier.</li>
                    <li>When it is fixed we mark it resolved, and you close it - or reopen it if it is not.</li>
                </ol>
                <?php if (!empty($company['company_phone'])) { ?>
                    <hr>
                    <p class="small mb-1"><strong>Stopping everyone?</strong> Call us as well:</p>
                    <a href="tel:<?= escapeHtml(preg_replace('/[^0-9+]/', '', ($company['company_phone_country_code'] ? '+' . $company['company_phone_country_code'] : '') . $company['company_phone'])) ?>"><i class="fas fa-phone me-2"></i><?= escapeHtml(formatPhoneNumber($company['company_phone'], $company['company_phone_country_code'])) ?></a>
                <?php } ?>
            </div>
        </section>
    </div>
</div>

<?php
require_once 'includes/footer.php';
