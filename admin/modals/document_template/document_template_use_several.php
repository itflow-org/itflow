<?php

/*
 * New documents from a template for several clients at once - from the
 * template's page. Each is filled in from its own client - its primary site
 * and contact - and the fill-ins are answered once, for all of them. Each goes
 * in its client's Files at the top level. ?id= the template; &missing=1 picks
 * the clients it is required of that have none yet. Made by
 * make_documents_from_template (admin/post/document_template.php).
 */

require_once '../../includes/modal_header.php';

$template = documentTemplateRow($_GET['id'] ?? 0);

ob_start();

if (!$template || $template['document_template_archived_at']) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-copy me-2"></i>New documents</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That document template is gone or archived.</div>
<?php } else {
    $document_template_id = intval($template['document_template_id']);
    $picked = [];
    if (!empty($_GET['missing'])) {
        $tag_ids = array_keys(documentTemplateRequiredTags([$document_template_id])[$document_template_id]);
        $picked = array_keys(documentTemplateCoverage($document_template_id, $template['document_template_required'], $tag_ids)['missing']);
    }
    $used = documentTemplateWordsUsed($template);
    $from_client = array_values(array_intersect(array_keys($used['placeholder']), array_merge(DOCUMENT_TEMPLATE_SITE_PLACEHOLDERS, DOCUMENT_TEMPLATE_CONTACT_PLACEHOLDERS)));
    $fill_ins = documentTemplateFillInsFor($document_template_id);
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-copy me-2"></i>New documents from <strong><?= escapeHtml($template['document_template_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="document_template_id" value="<?= $document_template_id ?>">

    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Clients <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="client_ids[]" multiple required data-placeholder="- Pick clients -">
                    <?php
                    $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name, client_lead FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql_clients)) { ?>
                        <option value="<?= intval($row['client_id']) ?>" <?= in_array(intval($row['client_id']), $picked, true) ? 'selected' : '' ?>><?= escapeHtml($row['client_name']) ?><?= intval($row['client_lead']) ? ' (lead)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if (!empty($_GET['missing'])) { ?>
                <div class="form-text"><?= $picked ? 'The ' . count($picked) . ' client' . (count($picked) === 1 ? '' : 's') . ' it is required of with none yet.' : 'Every client it is required of has one.' ?></div>
            <?php } ?>
        </div>

        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="skip_existing" value="1" id="documentTemplateSkipExisting" checked>
            <label class="form-check-label" for="documentTemplateSkipExisting">Skip clients that already have one</label>
        </div>

        <?php if ($used['placeholder'] || $used['table']) { ?>
            <div class="small text-secondary mb-3">
                <i class="fas fa-fw fa-magic me-1"></i>Each is filled in from its own client: <span class="font-monospace"><?= escapeHtml(implode(' ', array_map(function ($word) {
                    return "[$word]";
                }, array_values($used['placeholder'] + $used['table'])))) ?></span><?= $from_client ? ' - with its primary site and contact' : '' ?>.
            </div>
        <?php } ?>

        <?php if ($fill_ins) { ?>
            <div class="border rounded p-3 bg-body-tertiary mb-3">
                <div class="fw-semibold mb-1"><i class="fas fa-fw fa-pen-square text-secondary me-1"></i>It asks for</div>
                <div class="small text-secondary mb-2">Answered once, for every one of them. Leave one blank to fill it in on each document later.</div>
                <div class="row g-2">
                    <?php foreach ($fill_ins as $fill_in) { ?>
                        <div class="col-md-6"><?= ticketFillInFieldHtml($fill_in, '') ?></div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <small class="text-secondary">Each goes in its client's Files at the top level, named and summed up as the template is.</small>
    </div>
    <div class="modal-footer">
        <button type="submit" name="make_documents_from_template" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Create</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<?php }

require_once '../../../includes/modal_footer.php';
