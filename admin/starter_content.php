<?php

require_once "includes/inc_all_admin.php";

define('FROM_STARTER_CONTENT', true);
require_once "post/starter_content_model.php";
require_once "post/demo_data_model.php";

$starter_content_packs = starterContentPacks();
$starter_content_status = starterContentStatus($mysqli);

$demo_data_status = demoDataStatus($mysqli);
$demo_data_loaded = count($demo_data_status['loaded']);

$total_missing = 0;
foreach ($starter_content_status as $pack_status) {
    $total_missing = $total_missing + $pack_status['missing'];
}

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-seedling me-2"></i>Starter Content</h3>
        <?php if ($total_missing) { ?>
        <div class="card-tools">
            <form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="load_starter_content" value="all" class="btn btn-primary btn-sm">
                    <i class="fas fa-fw fa-download me-2"></i>Add all <?= $total_missing ?> missing
                </button>
            </form>
        </div>
        <?php } ?>
    </div>
    <div class="card-body">

        <p>
            A library of opinionated defaults for a typical MSP. Add whichever packs are useful - on a
            brand new install or on one that has been running for years.
        </p>
        <p class="text-muted">
            Nothing here is overwritten or deleted. Anything already present under the same name is
            skipped, so a pack can be added again later to pick up entries you removed by mistake, and
            adding twice never produces duplicates. Everything added is editable and deletable
            afterwards like any other record.
        </p>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Content</th>
                        <th class="text-center">In library</th>
                        <th class="text-center">Already here</th>
                        <th class="text-center">Will be added</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($starter_content_packs as $pack => $pack_details) {
                        $pack_status = $starter_content_status[$pack];
                    ?>
                    <tr>
                        <td>
                            <strong><i class="fas fa-fw <?= escapeHtml($pack_details['icon']) ?> me-2"></i><?= escapeHtml($pack_details['label']) ?></strong>
                            <br><small class="text-muted"><?= escapeHtml($pack_details['description']) ?></small>
                            <?php
                                // Loading out of order works, it just produces less - say so up front
                                // rather than leaving someone with 77 uncategorised products
                                $requires = $pack_details['requires'] ?? '';
                                if ($requires && !empty($starter_content_status[$requires]['missing'])) {
                            ?>
                            <br><small class="text-warning"><i class="fas fa-fw fa-exclamation-triangle me-1"></i>Add <?= escapeHtml($starter_content_packs[$requires]['label']) ?> first, or these come in without them.</small>
                            <?php } ?>
                        </td>
                        <td class="text-center"><?= intval($pack_status['total']) ?></td>
                        <td class="text-center"><?= intval($pack_status['present']) ?></td>
                        <td class="text-center">
                            <?php if ($pack_status['missing']) { ?>
                                <span class="badge bg-warning text-dark p-2"><?= intval($pack_status['missing']) ?></span>
                            <?php } else { ?>
                                <span class="text-success"><i class="fas fa-fw fa-check"></i></span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <?php if ($pack_status['missing']) { ?>
                            <form action="post.php" method="POST" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="load_starter_content" value="<?= escapeHtml($pack) ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-fw fa-plus me-2"></i>Add
                                </button>
                            </form>
                            <?php } else { ?>
                                <span class="text-muted">Nothing to add</span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted mb-0">
            Ticket templates bring their task lists with them. Products are filed under income
            categories and priced as starting points only - hardware and resold licensing come in at
            zero because they are quoted per deal.
        </p>

    </div>
</div>

<div class="card card-dark mt-3">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-flask me-2"></i>Demo Data</h3>
    </div>
    <div class="card-body">

        <p>
            A fictional book of business for demos, training and screenshots - <?= intval($demo_data_status['total']) ?> clients
            with the people, equipment, documentation, tickets and billing a typical MSP would be carrying for them, plus the
            bank accounts, operating costs and transfers on your own side of the books.
            It is generated across the <strong>last two years</strong> and dated relative to today, so the dashboard, ticket
            queue, aging, profit and loss and every other report have real history behind them rather than a single flat month.
        </p>
        <p class="text-muted">
            This is sample data, not configuration. Every demo client carries the <strong>Demo Data</strong> client tag and
            everything created hangs off one of them, so removing it takes the whole lot back out again. Contacts use
            addresses on the reserved <strong>.example</strong> domain, which cannot receive mail, and the demo
            agreements are created with email notification switched off.
        </p>

        <?php if ($demo_data_status['other_clients'] && !$demo_data_loaded) { ?>
        <div class="alert alert-warning">
            <i class="fas fa-fw fa-exclamation-triangle me-2"></i>
            This install already has <strong><?= intval($demo_data_status['other_clients']) ?></strong> client(s) of its own.
            Demo data is meant for a demo or training install - it will sit alongside your real clients in every list, report and total until it is removed.
        </div>
        <?php } ?>

        <?php if (!$demo_data_loaded) { ?>

            <?php if (!empty($starter_content_status['categories']['missing']) || !empty($starter_content_status['tags']['missing'])) { ?>
            <p class="text-warning">
                <i class="fas fa-fw fa-exclamation-triangle me-1"></i>Add the Categories and Tags packs above first, or the demo tickets, invoices and tags come in bare.
            </p>
            <?php } ?>

            <form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="load_demo_data" value="1" class="btn btn-primary">
                    <i class="fas fa-fw fa-flask me-2"></i>Add demo data
                </button>
            </form>

        <?php } else { ?>

            <p>
                <strong><?= intval($demo_data_loaded) ?></strong> demo client(s) are here:
                <?= escapeHtml(implode(', ', $demo_data_status['loaded'])) ?>
            </p>

            <div class="d-flex gap-2">

                <form action="post.php" method="POST" autocomplete="off" onsubmit="return confirm('Delete every client tagged Demo Data, along with all of their contacts, assets, documentation, credentials, tickets, quotes, invoices and payments? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="remove_demo_data" value="1" class="btn btn-danger">
                        <i class="fas fa-fw fa-trash me-2"></i>Remove demo data
                    </button>
                </form>

                <?php if ($demo_data_loaded < $demo_data_status['total']) { ?>
                <form action="post.php" method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" name="load_demo_data" value="1" class="btn btn-primary">
                        <i class="fas fa-fw fa-plus me-2"></i>Add the missing <?= intval($demo_data_status['total'] - $demo_data_loaded) ?>
                    </button>
                </form>
                <?php } ?>

            </div>

        <?php } ?>

    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
