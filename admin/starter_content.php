<?php

/*
 * Maintenance > Starter Content - the library of defaults for a typical MSP, pack by pack
 * (starterContentPacks()), and the demo data. Laid out as the other Maintenance pages are: what
 * it is and the one thing to do at the top, the packs and the demo data under it, and at a glance
 * and how it works to the side. Pick which and Preview are includes/starter_content_pick.php, shared
 * with Get started's Starter content step; js/starter_content.js keeps the ticks and counts in step.
 * Clear out unused, beside a pack's counts, opens modals/starter_content/starter_clear.php.
 */

require_once "includes/inc_all_admin.php";

define('FROM_STARTER_CONTENT', true);
require_once "post/starter_content_model.php";
require_once "post/demo_data_model.php";
require_once "includes/starter_content_pick.php";

$starter_content_packs = starterContentPacks();

// Each pack's items not here yet - for Pick which, and how many Add brings - and how many of
// the ones here nothing uses, for Clear out unused
$starter_content_items = [];
$starter_content_status = [];
$total_in_library = $total_missing = $total_unused = 0;
foreach ($starter_content_packs as $pack => $pack_details) {
    $starter_content_items[$pack] = starterContentItems($mysqli, $pack);
    $total = starterContentTotal($pack);
    $missing = count($starter_content_items[$pack]);
    $unused = $total - $missing ? count(starterContentUnused($mysqli, $pack)['items']) : 0;
    $starter_content_status[$pack] = ['total' => $total, 'missing' => $missing, 'present' => $total - $missing, 'unused' => $unused];
    $total_in_library += $total;
    $total_missing += $missing;
    $total_unused += $unused;
}
$total_present = $total_in_library - $total_missing;

$demo_data_status = demoDataStatus($mysqli);
$demo_data_loaded = count($demo_data_status['loaded']);
$demo_data_total = intval($demo_data_status['total']);

// The top of the page says where the install stands
if (!$total_missing) {
    $hero = ['tone' => 'success', 'icon' => 'fa-check', 'title' => 'Everything in the library is here'];
} elseif (!$total_present) {
    $hero = ['tone' => 'primary', 'icon' => 'fa-seedling', 'title' => 'Start from a library of MSP defaults'];
} else {
    $hero = ['tone' => 'primary', 'icon' => 'fa-seedling', 'title' => "$total_missing more to add from the library"];
}

?>

<div class="row g-3">

    <div class="col-xl-8">

        <!-- Where it stands, and Add all -->
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3">
                    <span class="itflow-asset-icon itflow-asset-icon-lg rounded-circle bg-<?= $hero['tone'] ?>-subtle text-<?= $hero['tone'] ?>-emphasis" aria-hidden="true"><i class="fas <?= $hero['icon'] ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h4 mb-1"><?= escapeHtml($hero['title']) ?></h2>
                        <p class="text-body-secondary mb-0">
                            Categories, tags, ticket and project templates, canned responses, vendor, document and license templates, and the products and services a typical MSP starts from.
                            Add what's useful - what you already have is never changed.
                        </p>
                        <?php if ($total_missing) { ?>
                            <form action="post.php" method="POST" autocomplete="off" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="load_starter_content" value="all" class="btn btn-primary">
                                    <i class="fas fa-fw fa-download me-2"></i>Add all <?= $total_missing ?>
                                </button>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- The packs - each with what it would add, Pick which, Add and, for templates, Update -->
        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-box-open me-2"></i>Packs</h3>
            </div>
            <div class="list-group list-group-flush itflow-starter-packs">
                <?php foreach ($starter_content_packs as $pack => $pack_details) {
                    $pack_status = $starter_content_status[$pack];
                    $all_here = !$pack_status['missing'];
                    $can_update = !empty($pack_details['update']) && $pack_status['present'];
                    // Loading out of order works, it just produces less - said up front rather than
                    // leaving someone with 77 uncategorized products
                    $requires = $pack_details['requires'] ?? '';
                    $needs_first = $requires && !empty($starter_content_status[$requires]['missing']) && !$all_here;
                ?>
                    <div class="list-group-item py-3<?= $all_here ? ' is-here' : '' ?>" data-starter-pick data-starter-pack="<?= escapeHtml($pack) ?>">
                        <div class="d-flex flex-wrap flex-md-nowrap gap-3 align-items-start">
                            <!-- The pack beside its icon; on a phone its buttons go under it -->
                            <div class="d-flex gap-3 align-items-start flex-grow-1 min-w-0">
                                <span class="itflow-gs-pack-icon" aria-hidden="true"><i class="fas fa-fw <?= escapeHtml($pack_details['icon']) ?>"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-baseline column-gap-2">
                                        <span class="fw-semibold"><?= escapeHtml($pack_details['label']) ?></span>
                                        <?php if ($all_here) { ?>
                                            <span class="small text-body-secondary"><i class="fas fa-check text-success me-1"></i>All <?= intval($pack_status['total']) ?> here</span>
                                        <?php } else { ?>
                                            <span class="small text-body-secondary">
                                                <span data-starter-count data-starter-more="<?= $pack_status['present'] ? 1 : 0 ?>"><?= intval($pack_status['missing']) ?> <?= $pack_status['present'] ? 'more ' : '' ?>to add</span><?php if ($pack_status['present']) { ?> · <?= intval($pack_status['present']) ?> of <?= intval($pack_status['total']) ?> here<?php } ?>
                                            </span>
                                        <?php } ?>
                                        <?php if ($pack_status['unused']) { ?>
                                            <!-- The ones here that nothing uses, archived from a window that lists them -->
                                            <span class="small text-body-secondary">·
                                                <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none ajax-modal" data-modal-url="/admin/modals/starter_content/starter_clear.php?pack=<?= urlencode($pack) ?>" data-modal-size="lg" data-starter-clear>
                                                    <i class="fas fa-broom me-1"></i>Clear out <?= intval($pack_status['unused']) ?> unused
                                                </button>
                                            </span>
                                        <?php } ?>
                                    </div>
                                    <div class="small text-body-secondary itflow-gs-pack-about" title="<?= escapeHtml($pack_details['description']) ?>"><?= escapeHtml($pack_details['description']) ?></div>
                                    <?php if ($needs_first) { ?>
                                        <div class="small text-warning-emphasis mt-1"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Add <?= escapeHtml($starter_content_packs[$requires]['label']) ?> first, or these come in without them.</div>
                                    <?php } ?>
                                    <?= starterPickHtml($pack, $starter_content_items[$pack], "starterAdd_$pack") ?>
                                </div>
                            </div>
                            <?php if (!$all_here || $can_update) { ?>
                                <div class="d-flex flex-wrap gap-2 ms-auto ms-md-0 flex-shrink-0">
                                    <?php if ($can_update) { ?>
                                        <!-- The ones already here brought up to this version - asked for, never done by Add -->
                                        <form action="post.php" method="POST" autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" name="<?= escapeHtml($pack_details['update']['button']) ?>" value="1" class="btn btn-outline-secondary btn-sm text-nowrap confirm-link"
                                                    data-confirm-title="Update the <?= intval($pack_status['present']) ?> you have?"
                                                    data-confirm-text="<?= escapeHtml(sprintf($pack_details['update']['confirm'], intval($pack_status['present']))) ?>"
                                                    data-confirm-button="Update them" data-confirm-icon="warning">
                                                <i class="fas fa-fw fa-sync-alt me-2"></i>Update the <?= intval($pack_status['present']) ?> you have
                                            </button>
                                        </form>
                                    <?php } ?>
                                    <?php if (!$all_here) { ?>
                                        <form action="post.php" method="POST" autocomplete="off" id="starterAdd_<?= escapeHtml($pack) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" name="load_starter_content" value="<?= escapeHtml($pack) ?>" class="btn btn-primary btn-sm text-nowrap" data-starter-add>
                                                <i class="fas fa-fw fa-plus me-2"></i>Add
                                            </button>
                                        </form>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Demo data - a made-up book of business, added and removed whole -->
        <div class="card mb-3" id="demo-data">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-flask me-2"></i>Demo data</h3>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-baseline column-gap-2 mb-1">
                    <?php if ($demo_data_loaded) { ?>
                        <span class="fw-semibold"><?= $demo_data_loaded === $demo_data_total ? "All $demo_data_total demo clients are here" : "$demo_data_loaded of the $demo_data_total demo clients are here" ?></span>
                    <?php } else { ?>
                        <span class="fw-semibold">Not added</span>
                    <?php } ?>
                </div>
                <p class="text-body-secondary">
                    A made-up MSP's book of business for demos, training and screenshots - <?= $demo_data_total ?> clients with their people, equipment, documentation, credentials,
                    tickets and billing, and your own side of the books, spread over the last two years so every report has history behind it.
                </p>

                <?php if ($demo_data_status['other_clients'] && !$demo_data_loaded) { ?>
                    <div class="alert alert-warning small">
                        <i class="fas fa-fw fa-exclamation-triangle me-2"></i>This install has <strong><?= intval($demo_data_status['other_clients']) ?></strong> client<?= intval($demo_data_status['other_clients']) === 1 ? '' : 's' ?> of its own.
                        Demo data sits beside them in every list, report and total until it is removed - it is meant for a demo or training install.
                    </div>
                <?php } ?>
                <?php if ($demo_data_loaded < $demo_data_total && (!empty($starter_content_status['categories']['missing']) || !empty($starter_content_status['tags']['missing']))) { ?>
                    <p class="small text-warning-emphasis">
                        <i class="fas fa-fw fa-exclamation-triangle me-1"></i>Add the Categories and Tags packs first, or the tickets, invoices and tags come in bare.
                    </p>
                <?php } ?>

                <?php if ($demo_data_loaded) { ?>
                    <details class="itflow-starter-pick mb-3">
                        <summary class="small">Which clients</summary>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach ($demo_data_status['loaded'] as $demo_client) { ?>
                                <span class="badge rounded-pill bg-body-tertiary text-body border fw-normal"><?= escapeHtml($demo_client) ?></span>
                            <?php } ?>
                        </div>
                    </details>
                <?php } ?>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($demo_data_loaded < $demo_data_total) { ?>
                        <form action="post.php" method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="load_demo_data" value="1" class="btn btn-primary">
                                <i class="fas fa-fw fa-flask me-2"></i><?= $demo_data_loaded ? 'Add the missing ' . ($demo_data_total - $demo_data_loaded) : 'Add demo data' ?>
                            </button>
                        </form>
                    <?php } ?>
                    <?php if ($demo_data_loaded) { ?>
                        <form action="post.php" method="POST" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" name="remove_demo_data" value="1" class="btn btn-outline-danger confirm-link"
                                    data-confirm-title="Remove the demo data?"
                                    data-confirm-text="The demo clients go, with all of their contacts, assets, documentation, credentials, tickets, quotes, invoices and payments, and so do the demo suppliers, accounts, response targets and company expenses. This can't be undone."
                                    data-confirm-button="Remove it">
                                <i class="fas fa-fw fa-trash-alt me-2"></i>Remove demo data
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>

    </div>

    <div class="col-xl-4">

        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-seedling me-2"></i>At a glance</h3>
            </div>
            <ul class="list-group list-group-flush small" data-starter-glance>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">In the library</span>
                    <span class="text-end fw-semibold"><?= $total_in_library ?> in <?= count($starter_content_packs) ?> packs</span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Already here</span>
                    <span class="text-end fw-semibold"><?= $total_present ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">To add</span>
                    <span class="text-end fw-semibold"><?= $total_missing ? $total_missing : '<span class="text-success-emphasis"><i class="fas fa-check me-1"></i>None</span>' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Here but unused</span>
                    <span class="text-end fw-semibold"><?= $total_unused ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between gap-3">
                    <span class="text-body-secondary">Demo data</span>
                    <span class="text-end fw-semibold"><?= $demo_data_loaded ? "$demo_data_loaded of $demo_data_total clients" : 'Not added' ?></span>
                </li>
            </ul>
        </div>

        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-info-circle me-2"></i>How adding works</h3>
            </div>
            <div class="card-body small">
                <ul class="ps-3 mb-0 d-grid gap-2">
                    <li>Adding never changes or removes anything. A name you already have is skipped, so a pack can be added again to bring back something deleted - never a duplicate.</li>
                    <li><strong>Pick which</strong>, under a pack, lists what it would add as it will look, with a preview of each template. Untick what you don't want.</li>
                    <li><strong>Update</strong> brings templates and canned responses added from an earlier version up to this one - it replaces what they hold.</li>
                    <li><strong>Clear out unused</strong> archives what came from a pack and nothing uses yet - anything in use stays, and Undo puts them back.</li>
                    <li>Request forms come with the ticket templates, turned off. Canned responses come in shared. Products are priced as starting points - hardware and resold licensing at zero.</li>
                </ul>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header py-3">
                <h3 class="card-title"><i class="fas fa-fw fa-flask me-2"></i>About demo data</h3>
            </div>
            <div class="card-body small">
                <ul class="ps-3 mb-0 d-grid gap-2">
                    <li>Nothing in it says demo - the clients, tags and references read as real, which is the point.</li>
                    <li>Removing it goes by the <?= $demo_data_total ?> client names, so <strong>a renamed demo client stays</strong>.</li>
                    <li>Contacts are on the <strong>.example</strong> domain, which can't receive mail, and agreements come with notifications off.</li>
                    <li>Accounts, suppliers, response targets, tax rates, catalog lines and calendars you already had are never touched.</li>
                </ul>
            </div>
        </div>

    </div>

</div>

<?= starterPreviewModalHtml() ?>
<script src="<?= staticFileUrl('/js/starter_content.js') ?>"></script>

<?php require_once "../includes/footer.php"; ?>
