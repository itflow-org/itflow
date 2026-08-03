<?php
defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

// The Income page is a UNION of two tables, so its bulk checkboxes carry a composite reference
// ("Payment-12" / "Revenue-7") rather than a bare id. Split them back out into per-table id
// lists so each bulk handler can update the right table.
$payment_ids = [];
$revenue_ids = [];

foreach ((array) ($_POST['income_ids'] ?? []) as $income_ref) {

    if (!is_string($income_ref)) {
        continue;
    }

    $income_ref_parts = explode('-', $income_ref, 2);

    if (count($income_ref_parts) != 2) {
        continue;
    }

    $income_ref_id = intval($income_ref_parts[1]);

    if ($income_ref_id < 1) {
        continue;
    }

    if ($income_ref_parts[0] == 'Payment') {
        $payment_ids[$income_ref_id] = $income_ref_id;
    } elseif ($income_ref_parts[0] == 'Revenue') {
        $revenue_ids[$income_ref_id] = $income_ref_id;
    }

}

$income_count = count($payment_ids) + count($revenue_ids);
