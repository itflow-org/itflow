<?php

/*
 * Who should have a vendor from a template - no one in particular, every
 * client, or the clients with one of the client tags picked. ?id= the
 * template. Saved by save_vendor_template_required
 * (admin/post/vendor_template.php); the template's page, the clients'
 * Vendors and their overviews then show the clients missing one.
 */

require_once '../../includes/modal_header.php';

$template = vendorTemplateInfo($_GET['id'] ?? 0);

ob_start();

if (!$template) { ?>
    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-clipboard-check me-2"></i>Who should have it</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body text-center text-secondary py-5">That vendor template no longer exists.</div>
<?php } else {
    $vendor_template_id = intval($template['vendor_template_id']);
    $required = intval($template['vendor_template_required']);
    $tag_ids = array_keys(vendorTemplateRequiredTags([$vendor_template_id])[$vendor_template_id]);
    ?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-clipboard-check me-2"></i>Who should have it <small class="ms-1 opacity-75"><?= escapeHtml($template['vendor_template_name']) ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="/admin/post.php" method="post" autocomplete="off" data-vendor-template-required>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="vendor_template_id" value="<?= $vendor_template_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label class="form-label">Every one of these should have it as a vendor</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-users"></i></span>
                <select class="form-select select2" name="required" id="vendorTemplateRequired">
                    <option value="<?= VENDOR_TEMPLATE_REQUIRED_NONE ?>" <?= $required === VENDOR_TEMPLATE_REQUIRED_NONE ? 'selected' : '' ?>>No one in particular</option>
                    <option value="<?= VENDOR_TEMPLATE_REQUIRED_EVERY ?>" <?= $required === VENDOR_TEMPLATE_REQUIRED_EVERY ? 'selected' : '' ?>>Every client</option>
                    <option value="<?= VENDOR_TEMPLATE_REQUIRED_TAGGED ?>" <?= $required === VENDOR_TEMPLATE_REQUIRED_TAGGED ? 'selected' : '' ?>>Clients with a tag</option>
                </select>
            </div>
            <div class="form-text">Leads and archived clients don't count. The template's page, each client's Vendors and their overview show who is missing it.</div>
        </div>

        <div class="mb-1" id="vendorTemplateRequiredTags" <?= $required === VENDOR_TEMPLATE_REQUIRED_TAGGED ? '' : 'hidden' ?>>
            <label class="form-label">Client tags</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-tags"></i></span>
                <select class="form-select select2" name="tags[]" multiple data-placeholder="- Pick client tags -">
                    <?php foreach (tagOptionsFor('client', $tag_ids) as $tag) { ?>
                        <?= tagOptionHtml($tag, $tag_ids) ?>
                    <?php } ?>
                </select>
            </div>
            <div class="form-text">Clients with any of them - VoIP, say, for the phone carrier every client on your hosted phones has.</div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="submit" name="save_vendor_template_required" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>

</form>

<script>
    (function () {
        // The tags are for "Clients with a tag" only
        var required = document.getElementById('vendorTemplateRequired');
        var tags = document.getElementById('vendorTemplateRequiredTags');
        if (required && tags) {
            required.addEventListener('change', function () {
                tags.hidden = required.value !== '<?= VENDOR_TEMPLATE_REQUIRED_TAGGED ?>';
            });
        }
    })();
</script>

<?php }

require_once '../../../includes/modal_footer.php';
