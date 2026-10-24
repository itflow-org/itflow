<?php

/*
 * Admin > Ticket Statuses - the steps a ticket goes through. The five ITFlow
 * has (New, Open, On Hold, Resolved, Closed) and your own - Waiting on
 * Vendor, Scheduled, Parts Ordered. Each row shows the status as it looks,
 * what it is for, how many tickets are in it, whether the SLA's resolution
 * clock runs in it and what a client's reply does to a ticket in it. In use
 * are in the order the pickers and the board show them, and can be dragged
 * into another; switched off ones are kept, but can't be picked. The work is
 * in functions/ticket_status.php; the handlers are admin/post/ticket_status.php.
 */

// Default Column Sortby Filter - the order the pickers and the board show them in
$sort = "ticket_status_order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$builtins = ticketStatusBuiltIns();

$views = [
    '' => ['label' => 'In use', 'icon' => 'fa-check-circle', 'where' => 'ticket_status_active = 1', 'title' => 'In the pickers and on the board'],
    'off' => ['label' => 'Switched off', 'icon' => 'fa-power-off', 'where' => 'ticket_status_active = 0', 'title' => 'Kept, but can\'t be picked'],
];
$show = (string) ($_GET['show'] ?? '');
$show = isset($views[$show]) ? $show : '';

// Only these sort - an unknown column would take the page down
$sortable_columns = [
    'ticket_status_order' => "ticket_status_order $order, ticket_status_id ASC",
    'ticket_status_name' => "ticket_status_name $order",
    'tickets' => "n_tickets $order, ticket_status_order ASC",
    'ticket_status_pauses_sla' => "ticket_status_pauses_sla $order, ticket_status_order ASC",
];
if (!isset($sortable_columns[$sort])) {
    $sort = 'ticket_status_order';
}

$filters = "(ticket_status_name LIKE '%$q%'" . (ticketStatusColumnsReady() ? " OR ticket_status_description LIKE '%$q%'" : '') . ")";

$sql = mysqli_query($mysqli, "SELECT SQL_CALC_FOUND_ROWS " . ticketStatusColumnsSql() . ",
        (SELECT COUNT(*) FROM tickets WHERE ticket_status = ticket_status_id AND ticket_archived_at IS NULL) AS n_tickets
    FROM ticket_statuses
    WHERE $filters AND {$views[$show]['where']}
    ORDER BY {$sortable_columns[$sort]}
    LIMIT $record_from, $record_to");

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

$counts = mysqli_fetch_row(mysqli_query($mysqli, "SELECT " . implode(', ', array_map(function ($view) {
    return "COALESCE(SUM({$view['where']}), 0)";
}, $views)) . " FROM ticket_statuses WHERE $filters"));
$view_counts = array_combine(array_keys($views), $counts);

// Dragging into order: the ones in use, in their order, with nothing searched
$can_reorder = $show === '' && $sort === 'ticket_status_order' && $order === 'ASC' && $q === '' && $num_rows[0] > 1;

$sort_link = function ($column, $label) use ($url_query_strings_sort, $sort, $disp, $order_icon) {
    return '<a class="text-reset text-decoration-none" href="?' . escapeHtml($url_query_strings_sort) . '&amp;sort=' . $column . '&amp;order=' . $disp . '">'
        . $label . ($sort === $column ? " $order_icon" : '') . '</a>';
};
$pill_url = function ($view) {
    return '?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'show' => $view]));
};

$csrf = $_SESSION['csrf_token'];

?>

<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-info-circle me-2"></i>Ticket Statuses</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_status/ticket_status_add.php">
                <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-2">New Status</span>
            </button>
        </div>
    </div>
    <div class="card-header py-3">
        <form autocomplete="off">
            <?php if ($show !== '') { ?><input type="hidden" name="show" value="<?= escapeHtml($show) ?>"><?php } ?>
            <div class="row g-2 align-items-center">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search statuses..." title="Name or what it is for">
                        <button class="btn btn-dark" title="Search"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
        </form>
        <nav class="nav nav-pills itflow-asset-tabs mt-2">
            <?php foreach ($views as $view_key => $view) { ?>
                <a class="nav-link <?= $show === (string) $view_key ? 'active' : '' ?>" href="<?= escapeHtml($pill_url($view_key)) ?>" title="<?= escapeHtml($view['title']) ?>"><i class="fas fa-fw <?= $view['icon'] ?> me-1"></i><?= escapeHtml($view['label']) ?> <span class="badge rounded-pill ms-1"><?= intval($view_counts[$view_key]) ?></span></a>
            <?php } ?>
        </nav>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-nowrap <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
            <tr>
                <?php if ($can_reorder) { ?><th style="width: 1%"><span class="visually-hidden">Order</span></th><?php } ?>
                <th class="<?= $can_reorder ? 'ps-0' : 'ps-3' ?>"><?= $sort_link('ticket_status_name', 'Status') ?></th>
                <th class="text-end"><?= $sort_link('tickets', 'Tickets') ?></th>
                <th class="d-none d-md-table-cell"><?= $sort_link('ticket_status_pauses_sla', 'SLA clock') ?></th>
                <th class="d-none d-lg-table-cell">When the client replies</th>
                <th></th>
            </tr>
            </thead>
            <tbody<?= $can_reorder ? ' data-ticket-status-sortable data-ticket-status-offset="' . intval($record_from) . '"' : '' ?>>
            <?php
            $position = $record_from - 1;
            while ($row = mysqli_fetch_assoc($sql)) {
                $position++;
                $ticket_status_id = intval($row['ticket_status_id']);
                $name = escapeHtml($row['ticket_status_name']);
                $builtin = $builtins[$ticket_status_id] ?? null;
                $about = ticketStatusAbout($row);
                $active = intval($row['ticket_status_active']) === 1;
                $paused = intval($row['ticket_status_pauses_sla']) === 1;
                $reply = TICKET_STATUS_CLIENT_REPLY[intval($row['ticket_status_client_reply']) === 0 ? 0 : 1];
                $tickets = intval($row['n_tickets']);
                $edit_url = "modals/ticket_status/ticket_status_edit.php?id=$ticket_status_id";
                $tickets_url = "/agent/tickets.php?status[]=$ticket_status_id";
                $fixed = $builtin ? ' <i class="fas fa-lock small text-body-tertiary" title="Fixed for the statuses ITFlow has"></i>' : '';
                $clock_html = $ticket_status_id === TICKET_STATUS_CLOSED
                    ? '<span class="text-body-secondary">Stopped</span>'
                    : ($paused ? '<span class="text-warning-emphasis"><i class="fas fa-fw fa-pause-circle me-1"></i>Paused</span>' : '<span class="text-body-secondary"><i class="fas fa-fw fa-play-circle me-1"></i>Runs</span>');
                $reply_html = $ticket_status_id === TICKET_STATUS_CLOSED
                    ? '<span class="text-body-secondary" title="A closed ticket can\'t be replied to - the client is told to raise a new one">Can\'t reply</span>'
                    : '<span class="text-body-secondary"><i class="fas fa-fw ' . $reply['icon'] . ' me-1"></i>' . ([TICKET_STATUS_NEW => 'Opens it', TICKET_STATUS_OPEN => 'Stays Open', TICKET_STATUS_RESOLVED => 'Reopens it'][$ticket_status_id] ?? $reply['short']) . '</span>';
                ?>
                <tr data-ticket-status-id="<?= $ticket_status_id ?>">
                    <?php if ($can_reorder) { ?>
                        <td class="pe-0"><span class="itflow-category-grip text-body-secondary" title="Drag to move" data-ticket-status-grip><i class="fas fa-grip-vertical" aria-hidden="true"></i></span></td>
                    <?php } ?>
                    <td class="<?= $can_reorder ? 'ps-0' : 'ps-3' ?>">
                        <div class="d-flex align-items-start gap-3">
                            <span class="itflow-asset-icon itflow-asset-tone-other d-none d-sm-inline-flex" aria-hidden="true"><i class="fas fa-fw <?= $builtin ? $builtin['icon'] : 'fa-tag' ?>"></i></span>
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <a class="ajax-modal text-decoration-none" href="#" data-modal-url="<?= $edit_url ?>" title="Edit <?= $name ?>"><?= ticketStatusBadgeHtml($row['ticket_status_name'], $row['ticket_status_color'], 'list') ?></a>
                                    <?php if ($builtin) { ?>
                                        <span class="badge rounded-pill text-bg-light border fw-normal" title="<?= escapeHtml($builtin['set_by']) ?>"><i class="fas fa-fw fa-lock text-secondary me-1"></i>Built in</span>
                                    <?php } ?>
                                </div>
                                <?php if ($about !== '') { ?>
                                    <div class="small text-body-secondary mt-1"><?= escapeHtml($about) ?></div>
                                <?php } ?>
                                <!-- On smaller screens the clock and replies come along under the name -->
                                <div class="d-lg-none mt-1 small d-flex flex-wrap gap-3">
                                    <span class="d-md-none"><?= $clock_html ?></span>
                                    <span title="When the client replies"><?= $reply_html ?></span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($tickets) { ?>
                            <a href="<?= escapeHtml($tickets_url) ?>" title="See the tickets in <?= $name ?>"><?= number_format($tickets) ?></a>
                        <?php } else { ?>
                            <span class="text-body-tertiary">0</span>
                        <?php } ?>
                    </td>
                    <td class="d-none d-md-table-cell text-nowrap"><?= $clock_html . $fixed ?></td>
                    <td class="d-none d-lg-table-cell text-nowrap"><?= $reply_html . $fixed ?></td>
                    <td class="text-end">
                        <div class="dropdown dropstart">
                            <button class="btn btn-sm btn-light" title="More actions" type="button" data-bs-toggle="dropdown"><i class="fas fa-fw fa-ellipsis-v"></i></button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item ajax-modal" href="#" data-modal-url="<?= $edit_url ?>">
                                    <i class="fas fa-fw fa-edit text-secondary me-2"></i>Edit
                                </a>
                                <?php if ($tickets) { ?>
                                    <a class="dropdown-item" href="<?= escapeHtml($tickets_url) ?>">
                                        <i class="fas fa-fw fa-life-ring text-secondary me-2"></i>See its tickets
                                    </a>
                                <?php } ?>
                                <?php if ($can_reorder) { ?>
                                    <?php if ($position > 0) { ?>
                                        <a class="dropdown-item" href="post.php?move_ticket_status=<?= $ticket_status_id ?>&direction=up&csrf_token=<?= $csrf ?>">
                                            <i class="fas fa-fw fa-arrow-up text-secondary me-2"></i>Move up
                                        </a>
                                    <?php } ?>
                                    <?php if ($position < $num_rows[0] - 1) { ?>
                                        <a class="dropdown-item" href="post.php?move_ticket_status=<?= $ticket_status_id ?>&direction=down&csrf_token=<?= $csrf ?>">
                                            <i class="fas fa-fw fa-arrow-down text-secondary me-2"></i>Move down
                                        </a>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (!$builtin) { ?>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($active) { ?>
                                        <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_status/ticket_status_remove.php?id=<?= $ticket_status_id ?>&action=off" title="Kept, but it can't be picked - and it comes off the board">
                                            <i class="fas fa-fw fa-power-off text-secondary me-2"></i>Switch off
                                        </a>
                                    <?php } else { ?>
                                        <a class="dropdown-item" href="post.php?switch_on_ticket_status=<?= $ticket_status_id ?>&csrf_token=<?= $csrf ?>" title="Back in the pickers and on the board">
                                            <i class="fas fa-fw fa-power-off text-secondary me-2"></i>Switch on
                                        </a>
                                    <?php } ?>
                                    <a class="dropdown-item text-danger ajax-modal" href="#" data-modal-url="modals/ticket_status/ticket_status_remove.php?id=<?= $ticket_status_id ?>&action=delete">
                                        <i class="fas fa-fw fa-trash me-2"></i>Delete
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php if ($can_reorder) { ?>
        <div class="small text-body-secondary px-3 py-2 border-top"><i class="fas fa-fw fa-grip-vertical me-1"></i>Drag them into the order the status pickers and the ticket board show them in.</div>
    <?php } elseif ($show === '' && $q !== '') { ?>
        <div class="small text-body-secondary px-3 py-2 border-top">Clear the search to drag them into order.</div>
    <?php } ?>
    <?php
    $empty_state = [
        'thing' => 'ticket statuses',
        'icon' => 'fa-info-circle',
        'filtered' => $q !== '',
        'views' => ['show'],
        'view' => $show === 'off' ? [
            'title' => 'None switched off',
            'icon' => 'fa-power-off',
            'text' => 'A status you switch off is kept here - it can\'t be picked, and comes off the board.',
        ] : [],
        'text' => 'The steps a ticket goes through - add your own, like Waiting on Vendor or Scheduled.',
        'actions' => [['label' => 'New Status', 'modal' => 'modals/ticket_status/ticket_status_add.php']],
    ];
    require_once "../includes/filter_footer.php";
    ?>
</div>

<?php if ($can_reorder) { ?>
    <script src="/libs/SortableJS/Sortable.min.js"></script>
    <script>
        (function () {
            // Dragged into order - saved as soon as one is dropped, in their place among the rest (offset - the ones on the pages before);
            // a failure reloads the page to show the order kept
            const body = document.querySelector('[data-ticket-status-sortable]');
            new Sortable(body, {
                handle: '[data-ticket-status-grip]',
                animation: 150,
                onEnd: function (event) {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }
                    const ids = Array.from(body.querySelectorAll('tr[data-ticket-status-id]')).map(function (row) {
                        return row.dataset.ticketStatusId;
                    });
                    itflowPost('post.php', { reorder_ticket_statuses: 1, csrf_token: '<?= $csrf ?>', offset: body.dataset.ticketStatusOffset, ticket_status_ids: ids })
                        .then(function (text) {
                            const result = JSON.parse(text);
                            if (!result.ok) {
                                throw new Error(result.error || 'Not saved');
                            }
                            itflowToast('Order saved', 'success');
                        })
                        .catch(function () {
                            window.location.reload();
                        });
                }
            });
        })();
    </script>
<?php } ?>

<?php
require_once "../includes/footer.php";
