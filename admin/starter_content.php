<?php

require_once "includes/inc_all_admin.php";

define('FROM_STARTER_CONTENT', true);
require_once "post/starter_content_model.php";

$starter_content_packs = starterContentPacks();
$starter_content_status = starterContentStatus($mysqli);

$total_missing = 0;
foreach ($starter_content_status as $pack_status) {
    $total_missing = $total_missing + $pack_status['missing'];
}

?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-seedling mr-2"></i>Starter Content</h3>
        <?php if ($total_missing) { ?>
        <div class="card-tools">
            <form action="post.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" name="load_starter_content" value="all" class="btn btn-primary btn-sm">
                    <i class="fas fa-fw fa-download mr-2"></i>Add all <?= $total_missing ?> missing
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
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($starter_content_packs as $pack => $pack_details) {
                        $pack_status = $starter_content_status[$pack];
                    ?>
                    <tr>
                        <td>
                            <strong><i class="fas fa-fw <?= escapeHtml($pack_details['icon']) ?> mr-2"></i><?= escapeHtml($pack_details['label']) ?></strong>
                            <br><small class="text-muted"><?= escapeHtml($pack_details['description']) ?></small>
                            <?php
                                // Loading out of order works, it just produces less - say so up front
                                // rather than leaving someone with 77 uncategorised products
                                $requires = $pack_details['requires'] ?? '';
                                if ($requires && !empty($starter_content_status[$requires]['missing'])) {
                            ?>
                            <br><small class="text-warning"><i class="fas fa-fw fa-exclamation-triangle mr-1"></i>Add <?= escapeHtml($starter_content_packs[$requires]['label']) ?> first, or these come in without them.</small>
                            <?php } ?>
                        </td>
                        <td class="text-center"><?= intval($pack_status['total']) ?></td>
                        <td class="text-center"><?= intval($pack_status['present']) ?></td>
                        <td class="text-center">
                            <?php if ($pack_status['missing']) { ?>
                                <span class="badge badge-warning p-2"><?= intval($pack_status['missing']) ?></span>
                            <?php } else { ?>
                                <span class="text-success"><i class="fas fa-fw fa-check"></i></span>
                            <?php } ?>
                        </td>
                        <td class="text-right">
                            <?php if ($pack_status['missing']) { ?>
                            <form action="post.php" method="POST" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" name="load_starter_content" value="<?= escapeHtml($pack) ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-fw fa-plus mr-2"></i>Add
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

<?php require_once "../includes/footer.php"; ?>
