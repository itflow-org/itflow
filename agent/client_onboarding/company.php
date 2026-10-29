<?php

/*
 * Onboarding - the company: its name, short name, industry, website, how
 * they found you, tags and notes (client_onboarding_company). For a new
 * client this makes it, as New Client does - with the website's domain and
 * certificate, to watch for renewals; for a lead it makes them a client.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_c = $co_client ?? [];
$co_tags = $client_id ? tagIdsForRecord('client', $client_id) : [];
if (!empty($settings_kept['values']['tags_shown']) && isset($_SESSION['co_kept_tags'])) {
    $co_tags = array_map('intval', (array) $_SESSION['co_kept_tags']);
}
unset($_SESSION['co_kept_tags']);

?>
<?php if ($co_client && intval($co_client['client_lead'])) { ?>
    <div class="alert alert-info small d-flex gap-2" data-co-lead>
        <i class="fas fa-info-circle mt-1"></i>
        <div><?= escapeHtml($co_name) ?> is a lead. Saving this step makes them a client.</div>
    </div>
<?php } ?>

<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="company">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="tags_shown" value="1">
    <div class="row g-3 mb-3">
        <div class="col-sm-8">
            <?= settingsInput('name', $co_c['client_name'] ?? '', ['id' => 'coName', 'icon' => 'fa-building', 'label' => 'Name', 'required' => true, 'maxlength' => 200, 'placeholder' => 'The name you know them by', 'autofocus' => !$client_id]) ?>
        </div>
        <div class="col-sm-4">
            <?= settingsInput('abbreviation', $co_c['client_abbreviation'] ?? '', ['id' => 'coAbbreviation', 'icon' => 'fa-id-badge', 'label' => 'Short name', 'maxlength' => 6, 'placeholder' => 'From the name']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('type', $co_c['client_type'] ?? '', ['id' => 'coType', 'icon' => 'fa-briefcase', 'label' => 'Industry', 'maxlength' => 200, 'placeholder' => 'Dental practice, law firm...']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('website', $co_c['client_website'] ?? '', ['id' => 'coWebsite', 'icon' => 'fa-globe', 'label' => 'Website', 'maxlength' => 200, 'placeholder' => 'example.com',
                'help' => $client_id ? '' : 'Its domain and certificate are added too, to watch for renewals.']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsSelect('referral', $co_c['client_referral'] ?? '', [], ['id' => 'coReferral', 'icon' => 'fa-link', 'label' => 'How they found you', 'search' => true,
                'options_html' => '<option value="">- None -</option>' . categoryOptionsHtml('Referral', settingsValue('referral', $co_c['client_referral'] ?? ''))]) ?>
        </div>
        <div class="col-sm-6">
            <div class="itflow-settings-field">
                <label class="form-label" for="coTags">Tags</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                    <select class="form-select select2" id="coTags" name="tags[]" data-placeholder="Add some tags" multiple<?= !empty($session_is_admin) ? ' data-tags="true"' : '' ?>>
                        <?= tagOptionsHtml('client', $co_tags) ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="col-12">
            <?= settingsTextarea('notes', $co_c['client_notes'] ?? '', ['id' => 'coNotes', 'label' => 'Notes', 'rows' => 3, 'placeholder' => 'Anything the team should know about them']) ?>
        </div>
    </div>
    <button type="submit" name="client_onboarding_company" value="1" class="btn btn-primary" data-gs-save>
        <i class="fas <?= $client_id ? 'fa-check' : 'fa-plus' ?> me-2"></i><?= !$client_id ? 'Add the client' : (intval($co_client['client_lead']) ? 'Make them a client' : 'Save') ?>
    </button>
</form>
