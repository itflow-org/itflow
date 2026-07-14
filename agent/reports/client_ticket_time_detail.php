<?php

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_sales');

/**
 * Convert seconds to "HH:MM:SS" (supports totals > 24h by using hours > 24)
 */
function secondsToHmsString($seconds) {
    $seconds = (int) max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * Convert seconds to true decimal hours (rounded to 2 decimals).
 */
function secondsToDecimalHours($seconds) {
    $seconds = (int) max(0, $seconds);
    if ($seconds === 0) return 0.00;
    return round($seconds / 3600, 2);
}

/**
 * Round UP seconds to the nearest increment (in seconds).
 */
function secondsRoundUpToIncrement($seconds, $increment_seconds) {
    $seconds = (int) max(0, $seconds);
    $increment_seconds = (int) max(1, $increment_seconds);

    if ($seconds === 0) return 0;

    return (int) (ceil($seconds / $increment_seconds) * $increment_seconds);
}

/**
 * Validate YYYY-MM-DD
 */
function isValidDateYmd($s) {
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);
}

/**
 * Billing increment options
 * Key = hours (string), Value = increment seconds
 */
$billing_increment_options = [
    '0.1'  => 6 * 60,   // 6 minutes
    '0.25' => 15 * 60,  // 15 minutes [DEFAULT]
    '0.5'  => 30 * 60,  // 30 minutes
];

// Default range: current month
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to'])   ? $_GET['to']   : date('Y-m-t');

if (!isValidDateYmd($from)) $from = date('Y-m-01');
if (!isValidDateYmd($to))   $to   = date('Y-m-t');

// Inclusive datetime bounds
$from_dt = $from . " 00:00:00";
$to_dt   = $to   . " 23:59:59";

$billable_only = (isset($_GET['billable_only']) && (int)$_GET['billable_only'] === 1) ? 1 : 0;

// Billing increment selection (default 0.25)
$billing_increment_key = isset($_GET['billing_increment']) ? (string)$_GET['billing_increment'] : '0.25';
if (!array_key_exists($billing_increment_key, $billing_increment_options)) {
    $billing_increment_key = '0.25';
}
$billing_increment_seconds = $billing_increment_options[$billing_increment_key];
$billing_increment_minutes = (int) round($billing_increment_seconds / 60);

// Ticket-level billable flag (same as your original report)
$billable_sql = $billable_only ? " AND t.ticket_billable = 1 " : "";

/**
 * Query returns ONLY replies that have time_worked and are within date range.
 * Reply content column = tr.ticket_reply
 */
$stmt = $mysqli->prepare("
    SELECT
        c.client_id,
        c.client_name,
        t.ticket_id,
        t.ticket_prefix,
        t.ticket_number,
        t.ticket_subject,

        tr.ticket_reply_id,
        tr.ticket_reply_created_at,
        tr.ticket_reply_time_worked,
        TIME_TO_SEC(tr.ticket_reply_time_worked) AS reply_time_seconds,
        tr.ticket_reply AS reply_content

    FROM tickets t
    INNER JOIN clients c
        ON c.client_id = t.ticket_client_id

    INNER JOIN ticket_replies tr
        ON tr.ticket_reply_ticket_id = t.ticket_id
        AND tr.ticket_reply_time_worked IS NOT NULL
        AND TIME_TO_SEC(tr.ticket_reply_time_worked) > 0
        AND tr.ticket_reply_created_at BETWEEN ? AND ?

    WHERE c.client_archived_at IS NULL
      $billable_sql

    ORDER BY c.client_name ASC,
             t.ticket_number ASC,
             t.ticket_id ASC,
             tr.ticket_reply_created_at ASC
");
$stmt->bind_param("ss", $from_dt, $to_dt);
$stmt->execute();
$result = $stmt->get_result();

?>
<div class="card">
    <div class="card-header bg-dark py-2">
        <h3 class="card-title mt-2">
            <i class="fas fa-fw fa-life-ring mr-2"></i>
            Client Time Detail Audit Report (<?php echo escapeHtml($from); ?> to <?php echo escapeHtml($to); ?>)
            <?php if ($billable_only) { ?>
                <span class="badge badge-success ml-2">Billable Only</span>
            <?php } ?>
        </h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary d-print-none" onclick="window.print();">
                <i class="fas fa-fw fa-print mr-2"></i>Print
            </button>
        </div>
    </div>

    <div class="card-header d-print-none">
        <!-- Filters -->
        <form class="mb-3">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="mb-1">From</label>
                    <input type="date" class="form-control" name="from" value="<?php echo escapeHtml($from); ?>">
                </div>

                <div class="col-md-3 mb-2">
                    <label class="mb-1">To</label>
                    <input type="date" class="form-control" name="to" value="<?php echo escapeHtml($to); ?>">
                </div>

                <div class="col-md-3 mb-2">
                    <label class="mb-1">Billing time increment</label>
                    <select class="form-control" name="billing_increment">
                        <option value="0.1"  <?php echo ($billing_increment_key === '0.1')  ? 'selected' : ''; ?>>0.1 hour (6 minutes)</option>
                        <option value="0.25" <?php echo ($billing_increment_key === '0.25') ? 'selected' : ''; ?>>0.25 hour (15 minutes)</option>
                        <option value="0.5"  <?php echo ($billing_increment_key === '0.5')  ? 'selected' : ''; ?>>0.5 hour (30 minutes)</option>
                    </select>
                </div>

                <div class="col-md-2 mb-2 d-flex align-items-end ml-auto">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-fw fa-filter mr-1"></i>Apply
                    </button>
                </div>

                <div class="col-md-4 mb-2 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input
                            type="checkbox"
                            class="custom-control-input"
                            id="billable_only"
                            name="billable_only"
                            value="1"
                            <?php if ($billable_only) echo 'checked'; ?>
                        >
                        <label class="custom-control-label" for="billable_only">Billable tickets only</label>
                    </div>
                </div>


            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive-sm">
        <table class="table table-striped table-sm">
            <thead class="bg-dark">
            <tr>
                <th>Ticket / Replies with Time</th>
                <th class="text-right" style="width: 150px;">Time Worked</th>
                <th class="text-right" style="width: 120px;">Billable (hrs)</th>
            </tr>
            </thead>

            <tbody>
            <?php
            // Helper: print ticket subtotal row (billable hours = sum of rounded replies for that ticket)
            $printTicketSubtotalRow = function($ticket_label_html, $ticket_seconds, $ticket_billable_seconds) {
                $ticket_billed = secondsToDecimalHours($ticket_billable_seconds);
                ?>
                <tr class="font-weight-bold">
                    <td class="text-right pr-3">Ticket Total for <?php echo $ticket_label_html; ?></td>
                    <td class="text-right"><?php echo formatDuration(secondsToHmsString($ticket_seconds)); ?></td>
                    <td class="text-right"><?php echo number_format($ticket_billed, 2); ?></td>
                </tr>
                <?php
                return $ticket_billed;
            };

            $current_client_id = null;
            $current_client_name = null;

            $current_ticket_id = null;
            $current_ticket_label_html = null;

            $client_ticket_count = 0;
            $client_time_seconds = 0;

            // Billable seconds are based on rounding each reply UP to the chosen increment
            $client_billable_seconds = 0;

            $ticket_time_seconds = 0;
            $ticket_billable_seconds = 0;

            $grand_ticket_count = 0;
            $grand_time_seconds = 0;
            $grand_billable_seconds = 0;

            $had_rows = false;

            while ($r = mysqli_fetch_assoc($result)) {
                $had_rows = true;

                $client_id = (int)$r['client_id'];
                $client_name_html = escapeHtml($r['client_name']);

                $ticket_id = (int)$r['ticket_id'];
                $ticket_prefix = escapeHtml($r['ticket_prefix']);
                $ticket_number = (int)$r['ticket_number'];
                $ticket_subject_html = escapeHtml($r['ticket_subject']);

                $reply_created_at = $r['ticket_reply_created_at'];
                $reply_seconds = (int)$r['reply_time_seconds'];
                $reply_hms = secondsToHmsString($reply_seconds);

                // Rounded-up billable seconds for THIS reply
                $reply_billable_seconds = secondsRoundUpToIncrement($reply_seconds, $billing_increment_seconds);

                // Reply content: escape for safety, keep line breaks readable
                $reply_content_raw = $r['reply_content'] ?? '';
                $reply_content_clean = strip_tags($reply_content_raw);
                $reply_content_clean = str_replace(["\r\n", "\r"], "\n", $reply_content_clean);
                $reply_content_clean = preg_replace("/\n{3,}/", "\n\n", $reply_content_clean);
                $reply_content_html = nl2br(escapeHtml(trim($reply_content_clean)));

                // Close out previous client if client changed
                if ($current_client_id !== null && $client_id !== $current_client_id) {

                    // Close out previous ticket (if any)
                    if ($current_ticket_id !== null) {
                        $printTicketSubtotalRow($current_ticket_label_html, $ticket_time_seconds, $ticket_billable_seconds);

                        $ticket_time_seconds = 0;
                        $ticket_billable_seconds = 0;
                        $current_ticket_id = null;
                        $current_ticket_label_html = null;

                        echo '<tr><td colspan="3"></td></tr>';
                    }

                    // Client subtotal (billable based on sum of rounded replies across all tickets)
                    ?>
                    <tr class="font-weight-bold">
                        <td class="text-right">
                            Total for <?php echo $current_client_name; ?> (<?php echo $client_ticket_count; ?> tickets)
                        </td>
                        <td class="text-right"><?php echo formatDuration(secondsToHmsString($client_time_seconds)); ?></td>
                        <td class="text-right"><?php echo number_format(secondsToDecimalHours($client_billable_seconds), 2); ?></td>
                    </tr>
                    <tr><td colspan="3"></td></tr>
                    <?php

                    // Reset client totals
                    $client_ticket_count = 0;
                    $client_time_seconds = 0;
                    $client_billable_seconds = 0;
                }

                // Client header
                if ($client_id !== $current_client_id) {
                    $current_client_id = $client_id;
                    $current_client_name = $client_name_html;
                    ?>
                    <tr class="table-active">
                        <td colspan="3" class="font-weight-bold"><?php echo $client_name_html; ?></td>
                    </tr>
                    <?php
                }

                // Ticket label
                $display_ticket = trim($ticket_prefix . $ticket_number);
                if ($display_ticket === '') $display_ticket = (string)$ticket_number;
                $ticket_label_html = escapeHtml($display_ticket) . " - " . $ticket_subject_html;

                // Ticket changed: close previous ticket subtotal
                if ($current_ticket_id !== null && $ticket_id !== $current_ticket_id) {
                    $printTicketSubtotalRow($current_ticket_label_html, $ticket_time_seconds, $ticket_billable_seconds);

                    echo '<tr><td colspan="3"></td></tr>';

                    // Reset ticket accumulators
                    $ticket_time_seconds = 0;
                    $ticket_billable_seconds = 0;
                    $current_ticket_id = null;
                    $current_ticket_label_html = null;
                }

                // Ticket header (first row for this ticket)
                if ($ticket_id !== $current_ticket_id) {
                    $current_ticket_id = $ticket_id;
                    $current_ticket_label_html = $ticket_label_html;

                    $client_ticket_count++;
                    $grand_ticket_count++;

                    ?>
                    <tr>
                        <td class="font-weight-bold"><?php echo $ticket_label_html; ?></td>
                        <td class="text-right text-muted"></td>
                        <td class="text-right text-muted"></td>
                    </tr>
                    <?php
                }

                // Reply row (indented)
                ?>
                <tr>
                    <td class="pl-4 text-muted">
                        <i class="far fa-clock mr-1"></i>
                        <?php echo escapeHtml(date('Y-m-d g:i A', strtotime($reply_created_at))); ?>
                        <div class="mt-1 text-body" style="white-space: normal;">
                            <?php echo $reply_content_html; ?>
                        </div>
                    </td>
                    <td class="text-right"><?php echo formatDuration($reply_hms); ?></td>
                    <td class="text-right"><?php echo number_format(secondsToDecimalHours($reply_billable_seconds), 2); ?></td>
                </tr>
                <?php

                // Totals (raw time)
                $ticket_time_seconds += $reply_seconds;
                $client_time_seconds += $reply_seconds;
                $grand_time_seconds  += $reply_seconds;

                // Totals (billable time = sum of rounded replies)
                $ticket_billable_seconds += $reply_billable_seconds;
                $client_billable_seconds += $reply_billable_seconds;
                $grand_billable_seconds  += $reply_billable_seconds;
            }

            if (!$had_rows) {
                ?>
                <tr>
                    <td colspan="3" class="text-center text-muted">
                        No ticket replies with time worked found for this date range.
                    </td>
                </tr>
                <?php
            } else {
                // Close last ticket subtotal
                if ($current_ticket_id !== null) {
                    $printTicketSubtotalRow($current_ticket_label_html, $ticket_time_seconds, $ticket_billable_seconds);
                    echo '<tr><td colspan="3"></td></tr>';
                }

                // Close last client subtotal
                ?>
                <tr class="font-weight-bold">
                    <td class="text-right">
                        Total for <?php echo $current_client_name; ?> (<?php echo $client_ticket_count; ?> tickets)
                    </td>
                    <td class="text-right"><?php echo formatDuration(secondsToHmsString($client_time_seconds)); ?></td>
                    <td class="text-right"><?php echo number_format(secondsToDecimalHours($client_billable_seconds), 2); ?></td>
                </tr>

                <tr><td colspan="3"></td></tr>

                <!-- Grand totals -->
                <tr class="font-weight-bold">
                    <td class="text-right">
                        Grand Total (<?php echo $grand_ticket_count; ?> tickets)
                    </td>
                    <td class="text-right"><?php echo formatDuration(secondsToHmsString($grand_time_seconds)); ?></td>
                    <td class="text-right"><?php echo number_format(secondsToDecimalHours($grand_billable_seconds), 2); ?></td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>

        <small class="text-muted p-2">
            This report shows only ticket replies with time worked within the selected date range.
            “Billable (hrs)” is calculated by rounding each reply up to the nearest <?php echo (int)$billing_increment_minutes; ?> minutes (<?php echo escapeHtml($billing_increment_key); ?> hours),
            then summing those rounded values for ticket/client/grand totals.
            <br>
            Reply content is displayed under each reply timestamp.
        </small>
    </div>
</div>

<?php
require_once "../../includes/footer.php";
