<?php
/*
 * Client Portal
 * New ticket - what's wrong, how much it is getting in the way, which device,
 * and any screenshots or files, with what happens next alongside. ?subject=
 * fills the subject in - the Ask us links on invoices and quotes - and ?asset=
 * picks the device, from Report a problem on a device's page.
 *
 * With request forms offered to this contact (ticket templates offered in the
 * portal - ticketTemplatePortalForms()) it starts with a choice: report a
 * problem (?form=problem, the form above) or one of the forms (?form=ID):
 * what it asks the client - its fill-ins - and anything else they want to
 * add. add_ticket_from_form in post.php raises it as the template says.
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

// The request forms they may use, and which one - if any - is open
$forms = ticketTemplatePortalForms($session_client_id, $sees_all);
$form_key = (string) ($_GET['form'] ?? '');
$form = $form_key !== '' && $form_key !== 'problem' ? ticketTemplatePortalForm(intval($form_key), $session_client_id, $sees_all) : null;
$choosing = $forms && $form_key === '' && !isset($_GET['subject']) && !isset($_GET['asset']);
$back = $forms && !$choosing ? ['ticket_add.php', 'New ticket'] : ['tickets.php', 'Tickets'];

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

<a class="itflow-portal-back" href="<?= $back[0] ?>"><i class="fas fa-chevron-left"></i><?= $back[1] ?></a>

<?php if ($form_key !== '' && $form_key !== 'problem' && !$form) { ?>

    <?= emptyStateHtml(['title' => "That form isn't here", 'text' => 'It may have been taken down, or it is not one for you. Tell us what you need instead.', 'icon' => 'fa-clipboard-list', 'filtered' => false,
        'actions' => [['label' => 'New ticket', 'href' => 'ticket_add.php', 'icon' => 'fa-plus']]]) ?>

<?php } elseif ($choosing) { ?>

    <div class="itflow-portal-page-head">
        <div>
            <h1>New ticket</h1>
            <p>What do you need?</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="itflow-portal-requests mb-4">
                <a class="itflow-portal-request" href="ticket_add.php?form=problem">
                    <span class="itflow-portal-tile"><i class="fas fa-fw fa-life-ring"></i></span>
                    <span class="min-w-0">
                        <span class="itflow-portal-request-title">Report a problem</span>
                        <span class="itflow-portal-request-text">Something not working? Tell us what is wrong and we will get on it.</span>
                    </span>
                </a>
            </div>
            <h2 class="h6 text-body-secondary mb-2">Or ask for something</h2>
            <div class="itflow-portal-requests">
                <?php foreach ($forms as $row) { ?>
                    <a class="itflow-portal-request" href="ticket_add.php?form=<?= intval($row['ticket_template_id']) ?>">
                        <span class="itflow-portal-tile"><i class="fas fa-fw <?= ticketTemplatePortalIcon($row) ?>"></i></span>
                        <span class="min-w-0">
                            <span class="itflow-portal-request-title"><?= escapeHtml(ticketTemplatePortalTitle($row)) ?></span>
                            <?php if (trim((string) $row['ticket_template_portal_description']) !== '') { ?>
                                <span class="itflow-portal-request-text"><?= escapeHtml($row['ticket_template_portal_description']) ?></span>
                            <?php } ?>
                        </span>
                    </a>
                <?php } ?>
            </div>
        </div>
        <div class="col-lg-4">
            <?php require __DIR__ . '/includes/ticket_next_steps.php'; ?>
        </div>
    </div>

<?php } elseif ($form) {
    $form_id = intval($form['ticket_template_id']);
    $form_tasks = ticketTemplateTasks($form_id);
    $asked = array_values(array_filter(ticketTemplateFillIns($form, $form_tasks, ticketTemplateFillInRowsFor([$form_id])[$form_id]), function ($fill_in) {
        return $fill_in['client'];
    }));
    $asks_device = $assets && ticketTemplateMentionsAsset($form, $form_tasks);
    ?>

    <div class="itflow-portal-page-head">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="itflow-portal-tile itflow-portal-tile-lg"><i class="fas fa-fw <?= ticketTemplatePortalIcon($form) ?>"></i></span>
            <div class="min-w-0">
                <h1><?= escapeHtml(ticketTemplatePortalTitle($form)) ?></h1>
                <?php if (trim((string) $form['ticket_template_portal_description']) !== '') { ?>
                    <p><?= escapeHtml($form['ticket_template_portal_description']) ?></p>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <form class="itflow-portal-panel itflow-portal-panel-body" action="post.php" method="post" enctype="multipart/form-data" data-portal-once>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="form" value="<?= $form_id ?>">

                <?php if ($asked) { ?>
                    <div class="row g-3 mb-3">
                        <?php foreach ($asked as $fill_in) { ?>
                            <div class="col-md-6"><?= ticketFillInFieldHtml($fill_in, '') ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if ($asks_device) { ?>
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

                <div class="mb-3">
                    <label class="form-label" for="requestNotes">Anything else we should know?</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                        <textarea class="form-control" id="requestNotes" name="notes" rows="4" maxlength="5000" placeholder="Optional"></textarea>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Files</label>
                    <div class="itflow-portal-drop" data-portal-drop>
                        <input type="file" name="file[]" multiple id="ticketFiles" accept=".jpg, .jpeg, .gif, .png, .webp, .pdf, .txt, .md, .doc, .docx, .odt, .csv, .xls, .xlsx, .ods, .pptx, .odp, .zip, .tar, .gz, .xml, .msg, .json, .wav, .mp3, .ogg, .mov, .mp4, .av1, .ovpn">
                        <i class="fas fa-paperclip me-2"></i>Drop files here, or <label for="ticketFiles">choose files</label>
                        <div class="itflow-portal-files" data-portal-files hidden></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" name="add_ticket_from_form"><i class="fas fa-paper-plane me-2"></i>Send request</button>
            </form>
        </div>

        <div class="col-lg-4">
            <?php require __DIR__ . '/includes/ticket_next_steps.php'; ?>
        </div>
    </div>

<?php } else { ?>

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
                                <option value="<?= intval($asset['asset_id']) ?>"<?= intval($asset['asset_id']) === intval($_GET['asset'] ?? 0) ? ' selected' : '' ?> data-icon="fas fa-<?= getAssetIcon($asset['asset_type']) ?>"
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
        <?php require __DIR__ . '/includes/ticket_next_steps.php'; ?>
    </div>
</div>

<?php } ?>

<?php
require_once 'includes/footer.php';
