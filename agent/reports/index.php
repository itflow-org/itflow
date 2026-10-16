<?php

/*
 * Reports - every report this user may open, in its group, with a line on
 * what each one shows. Drawn from reportCatalog(), the same list as the menu.
 */

require_once "includes/inc_report.php";
require_once "includes/inc_report_layout.php";

$groups = reportCatalogForUser();
$group_tones = ['finance' => 'success', 'billing' => 'primary', 'service' => 'info', 'clients' => 'warning'];

?>

<div class="itflow-report">

    <div class="card">
        <div class="card-header bg-dark py-2 d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title mt-2 me-auto"><i class="fas fa-fw fa-chart-bar me-2"></i>Reports</h3>
        </div>
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-fw fa-search"></i></span>
                        <input type="search" class="form-control" placeholder="Find a report..." data-report-find aria-label="Find a report" autofocus>
                    </div>
                </div>
                <div class="col">
                    <span class="small text-body-secondary">Each report shows what your role can see - pick a period, narrow it to one client, print it or export it to CSV.</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$groups) { ?>
        <div class="card"><div class="card-body"><?= reportEmpty('Your role does not include any reports yet - ask an administrator to give it read access to the area you need, such as Support or Financial.', 'fa-lock', 'secondary') ?></div></div>
    <?php } ?>

    <?php foreach ($groups as $group_key => $group) {
        $tone = $group_tones[$group_key] ?? 'secondary'; ?>
        <section class="mb-4" data-report-group>
            <h2 class="itflow-report-group-title mb-2"><?= escapeHtml($group['label']) ?></h2>
            <div class="row g-3">
                <?php foreach ($group['reports'] as $key => $report) { ?>
                    <div class="col-md-6 col-xl-4" data-report-card data-report-text="<?= escapeHtml(strtolower($report['title'] . ' ' . $report['description'] . ' ' . $group['label'])) ?>">
                        <a class="card h-100 mb-0 itflow-report-card text-decoration-none" href="<?= $key ?>.php">
                            <div class="card-body d-flex gap-3">
                                <span class="itflow-report-card-icon bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis"><i class="fas fa-fw <?= escapeHtml($report['icon']) ?>"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block fw-semibold text-body"><?= escapeHtml($report['title']) ?></span>
                                    <span class="d-block small text-body-secondary"><?= escapeHtml($report['description']) ?></span>
                                </span>
                            </div>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <div class="text-center text-body-secondary py-4" data-report-none hidden>No report matches that.</div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
