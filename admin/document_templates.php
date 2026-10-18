<?php

/*
 * Admin > Document Templates - documents ready to start a client's from: a
 * runbook, a build sheet, a plan. Each row shows what it is for, how many
 * documents have been made from it and for how many clients, when it was
 * last used and last changed. A template can be changed, copied as the start
 * of another, archived - not offered for new documents, while the documents
 * made from it stay - or deleted. The work is in functions/document_template.php;
 * the handlers are admin/post/document_template.php.
 */

// Default Column Sortby Filter
$sort = "document_template_name";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$show_archived = ($_GET['show'] ?? '') === 'archived';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'document_template_name' => "document_template_name $order",
    'used_count' => "used_count $order, document_template_name ASC",
    'last_used' => "last_used IS NULL, last_used $order, document_template_name ASC",
    'changed_at' => "changed_at $order, document_template_name ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'document_template_name';
}

$search = "(document_template_name LIKE '%$q%' OR document_template_description LIKE '%$q%' OR document_template_content LIKE '%$q%')";

// Its use is the documents made from it that are not archived - as documentTemplateUsage() counts them
$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS document_templates.document_template_id, document_template_name, document_template_description, document_template_content,
        document_template_created_at, document_template_updated_at, document_template_archived_at, COALESCE(document_template_updated_at, document_template_created_at) AS changed_at,
        COALESCE(updater.user_name, creator.user_name) AS changed_by,
        COALESCE(used.used_count, 0) AS used_count, COALESCE(used.client_count, 0) AS client_count, used.last_used
    FROM document_templates
    LEFT JOIN users creator ON creator.user_id = document_template_created_by AND document_template_created_by > 0
    LEFT JOIN users updater ON updater.user_id = document_template_updated_by AND document_template_updated_by > 0
    LEFT JOIN (
        SELECT document_template_id AS used_template_id, COUNT(*) AS used_count, COUNT(DISTINCT document_client_id) AS client_count, MAX(document_created_at) AS last_used
        FROM documents WHERE document_template_id > 0 AND document_archived_at IS NULL GROUP BY document_template_id
    ) used ON used.used_template_id = document_templates.document_template_id
    WHERE $search AND document_template_archived_at IS " . ($show_archived ? 'NOT NULL' : 'NULL') . "
    ORDER BY {$sortable_columns[$sort]}, document_templates.document_template_id ASC
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT COALESCE(SUM(document_template_archived_at IS NULL), 0), COALESCE(SUM(document_template_archived_at IS NOT NULL), 0) FROM document_templates WHERE $search"));

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($archived) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $archived ? 'archived' : '']));
};

$add_url = 'modals/document_template/document_template_add.php';
$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-file-alt me-2"></i>Document Templates</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-size="xl" data-modal-url="<?= $add_url ?>"><i class="fas fa-plus me-2"></i>New Document Template</button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show_archived) { ?><input type="hidden" name="show" value="archived"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search document templates..." title="Name, summary or what it says">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <a class="nav-link <?= $show_archived ? '' : 'active' ?>" href="<?= escapeHtml($pill_url(false)) ?>"><i class="fas fa-fw fa-file-alt me-1"></i>Active <span class="badge rounded-pill ms-1"><?= intval($counts[0]) ?></span></a>
            <a class="nav-link <?= $show_archived ? 'active' : '' ?>" href="<?= escapeHtml($pill_url(true)) ?>" title="Not offered for new documents - the documents made from them stay"><i class="fas fa-fw fa-archive me-1"></i>Archived <span class="badge rounded-pill ms-1"><?= intval($counts[1]) ?></span></a>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <th class="ps-3"><?= $sort_link('document_template_name', 'Template') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('used_count', 'Used') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('last_used', 'Last used') ?></th>
                <th class="d-none d-lg-table-cell"><?= $sort_link('changed_at', 'Changed') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($sql)) {
                $template_id = intval($row['document_template_id']);
                $name = escapeHtml($row['document_template_name']);
                $used_count = intval($row['used_count']);
                $client_count = intval($row['client_count']);
                $summary = trim((string) $row['document_template_description']) !== '' ? $row['document_template_description'] : documentTemplatePreview($row['document_template_content'], 140);
                $used_text = $used_count . ' document' . ($used_count === 1 ? '' : 's') . ($client_count > 1 ? ' · ' . $client_count . ' clients' : '');
                $edit_url = "modals/document_template/document_template_edit.php?id=$template_id";
                ?>
                <tr data-document-template-id="<?= $template_id ?>">
                    <td class="ps-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw fa-file-alt"></i></span>
                            <div class="min-w-0">
                                <a class="fw-semibold text-body" href="document_template.php?document_template_id=<?= $template_id ?>"><?= $name ?></a>
                                <?php if ($summary !== '') { ?>
                                    <div class="small text-body-secondary"><?= escapeHtml($summary) ?></div>
                                <?php } ?>
                                <?php if ($row['document_template_archived_at']) { ?>
                                    <div class="small text-body-secondary"><i class="fas fa-fw fa-archive me-1"></i>Archived <?= date('M j, Y', strtotime($row['document_template_archived_at'])) ?></div>
                                <?php } ?>
                                <!-- On smaller screens its use comes along under the name -->
                                <div class="d-md-none small text-body-secondary mt-1"><?= $used_count ? $used_text . ' · last ' . timeAgo($row['last_used']) : 'Not used yet' ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell small text-nowrap"><?= $used_count ? escapeHtml($used_text) : '<span class="text-body-secondary">Not yet</span>' ?></td>
                    <td class="d-none d-md-table-cell small text-body-secondary text-nowrap"<?= $row['last_used'] ? ' title="' . escapeHtml($row['last_used']) . '"' : '' ?>><?= $row['last_used'] ? timeAgo($row['last_used']) : '-' ?></td>
                    <td class="d-none d-lg-table-cell small text-body-secondary text-nowrap" title="<?= escapeHtml($row['changed_at']) ?>">
                        <?= timeAgo($row['changed_at']) ?>
                        <?php if ($row['changed_by'] !== null) { ?><div><?= escapeHtml($row['changed_by']) ?></div><?php } ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-size="xl" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if (!$row['document_template_archived_at']) { ?>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/document_template/document_template_use.php?id=<?= $template_id ?>">
                                        <i class="fas fa-fw fa-plus text-secondary me-2"></i>New document for a client
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item" href="post.php?copy_document_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="A new template starting from this one">
                                    <i class="fas fa-fw fa-copy text-secondary me-2"></i>Copy
                                </a>
                                <div class="dropdown-divider"></div>
                                <?php if ($row['document_template_archived_at']) { ?>
                                    <a class="dropdown-item" href="post.php?restore_document_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>">
                                        <i class="fas fa-fw fa-box-open text-secondary me-2"></i>Restore
                                    </a>
                                <?php } else { ?>
                                    <a class="dropdown-item text-danger" href="post.php?archive_document_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>" title="Not offered for new documents - the documents made from it stay">
                                        <i class="fas fa-fw fa-archive me-2"></i>Archive
                                    </a>
                                <?php } ?>
                                <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?delete_document_template=<?= $template_id ?>&csrf_token=<?= $csrf ?>"
                                    data-confirm-title="Delete <?= $name ?>?" data-confirm-text="Its images go with it. Documents made from it stay as they are." data-confirm-button="Delete">
                                    <i class="fas fa-fw fa-trash me-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $empty_state = [
        'thing' => 'document templates',
        'icon' => 'fa-file-alt',
        'filtered' => $q !== '',
        'archived' => $show_archived,
        'views' => ['show'],
        'text' => 'A document ready to start a client\'s from - a runbook, a build sheet, a plan. Agents start one from <strong>Document from template</strong> in a client\'s Files. Maintenance > Starter Content has a set for an MSP.',
        'actions' => [['label' => 'New Document Template', 'modal' => $add_url, 'size' => 'xl']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php
require_once "../includes/footer.php";
