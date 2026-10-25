<?php

/*
 * A contract template's fields, in tabs - for contract_template_add.php and
 * contract_template_edit.php, which set $template (a contractTemplateRow(),
 * or null when new). The same terms a contract has, without the client and
 * its dates. The server checks them (contractFromInput()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$t = $template ?? null;
$t_value = function ($key, $default = '') use ($t) {
    return $t !== null && array_key_exists("contract_template_$key", $t) && $t["contract_template_$key"] !== null ? $t["contract_template_$key"] : $default;
};
$t_money = function ($value) {
    return $value === '' || $value === null ? '' : number_format((float) $value, 2, '.', '');
};
$t_hours = function ($value) {
    return floatval($value) > 0 ? rtrim(rtrim(number_format(floatval($value), 2, '.', ''), '0'), '.') : '';
};
$t_term = intval($t_value('term_months', 12));
$t_slas = contractSlaChoices($t ? array_map(function ($part) use ($t) { return intval($t["contract_template_sla_{$part}_id"]); }, CONTRACT_SLA_PRIORITIES) : []);
$tab = 'templateTab' . bin2hex(random_bytes(3));

?>

<ul class="nav nav-pills nav-justified mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#<?= $tab ?>General" role="tab"><i class="fas fa-fw fa-file-contract me-1"></i>Template</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#<?= $tab ?>Cover" role="tab"><i class="fas fa-fw fa-shield-alt me-1"></i>Cover</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#<?= $tab ?>Terms" role="tab"><i class="fas fa-fw fa-file-alt me-1"></i>Terms</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="<?= $tab ?>General" role="tabpanel">
        <div class="row g-3 mb-3">
            <div class="col-md-7">
                <label class="form-label">Name <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    <input type="text" class="form-control" name="name" maxlength="255" placeholder="Managed Services - Gold" value="<?= escapeHtml($t_value('name')) ?>" required<?= $t ? '' : ' autofocus' ?>>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label">Type</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                    <select class="form-select" name="type">
                        <?php foreach (CONTRACT_TYPES as $key => $type) { ?>
                            <option value="<?= $key ?>"<?= $t_value('type', 'managed') === $key ? ' selected' : '' ?> title="<?= escapeHtml($type['about']) ?>"><?= escapeHtml($type['label']) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-align-left"></i></span>
                <input type="text" class="form-control" name="description" maxlength="1000" placeholder="Who it suits, and what sets it apart" value="<?= escapeHtml($t_value('description')) ?>">
            </div>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Term</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-history"></i></span>
                    <select class="form-select" name="term_months">
                        <?php $terms = CONTRACT_TERMS + (!isset(CONTRACT_TERMS[$t_term]) ? [$t_term => contractTermLabel($t_term)] : []);
                        ksort($terms);
                        foreach ($terms as $months => $label) { ?>
                            <option value="<?= $months ?>"<?= $months === $t_term ? ' selected' : '' ?>><?= escapeHtml($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Notice to end it</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                    <input type="number" class="form-control" name="notice_days" min="0" max="365" value="<?= intval($t_value('notice_days', 30)) ?>">
                    <span class="input-group-text">days</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="auto_renew" value="1" id="<?= $tab ?>AutoRenew"<?= intval($t_value('auto_renew', 1)) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="<?= $tab ?>AutoRenew">Renews itself</label>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="<?= $tab ?>Cover" role="tabpanel">
        <div class="mb-3">
            <label class="form-label">Support hours</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                <input type="text" class="form-control" name="support_hours" maxlength="100" placeholder="Mon-Fri 8am-6pm, emergencies 24/7" value="<?= escapeHtml($t_value('support_hours')) ?>">
            </div>
        </div>
        <?php if (contractHoursReady()) { ?>
            <div class="row g-3 mb-1">
                <div class="col-md-6">
                    <label class="form-label">Hours included each month</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-hourglass-half"></i></span>
                        <input type="text" inputmode="decimal" class="form-control" name="monthly_hours" placeholder="None" value="<?= escapeHtml($t_hours($t_value('monthly_hours', 0))) ?>">
                        <span class="input-group-text">hours</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tell the owner under</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <input type="text" inputmode="decimal" class="form-control" name="hours_alert" placeholder="Don't tell" value="<?= escapeHtml($t_hours($t_value('hours_alert', 0))) ?>">
                        <span class="input-group-text">hours left</span>
                    </div>
                </div>
            </div>
            <small class="text-secondary d-block mb-3">Tickets' time is drawn from the hours included each month - for that month only - and from any hours bought on the contract, before it is billed.</small>
        <?php } ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Hourly rate</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                    <input type="text" inputmode="decimal" class="form-control" name="rate_standard" placeholder="The client's" value="<?= escapeHtml($t_money($t_value('rate_standard', ''))) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">After hours</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-moon"></i></span>
                    <input type="text" inputmode="decimal" class="form-control" name="rate_after_hours" placeholder="None" value="<?= escapeHtml($t_money($t_value('rate_after_hours', ''))) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Net terms</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                    <input type="number" class="form-control" name="net_terms" min="0" max="365" placeholder="The client's" value="<?= escapeHtml((string) $t_value('net_terms', '')) ?>">
                    <span class="input-group-text">days</span>
                </div>
            </div>
        </div>
        <small class="text-secondary d-block mb-3">Leave a rate or the net terms empty and the client's own apply.</small>
        <label class="form-label">SLA by ticket priority</label>
        <?php foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) { ?>
            <div class="input-group mb-2">
                <span class="input-group-text" style="width: 7rem;"><i class="fa fa-fw fa-stopwatch me-2"></i><?= $priority ?></span>
                <select class="form-select" name="sla_<?= $part ?>" aria-label="<?= $priority ?> priority SLA">
                    <option value="0">The client's usual SLA</option>
                    <?php foreach ($t_slas as $sla_id => $sla) { ?>
                        <option value="<?= $sla_id ?>"<?= intval($t_value("sla_{$part}_id", 0)) === $sla_id ? ' selected' : '' ?>><?= escapeHtml($sla['sla_name'] . ' - ' . contractSlaTargets($sla)) ?><?= $sla['sla_archived_at'] ? ' (archived)' : '' ?></option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
        <small class="text-secondary">From Admin > SLAs.</small>
    </div>

    <div class="tab-pane fade" id="<?= $tab ?>Terms" role="tabpanel">
        <textarea class="form-control tinymce" name="details" rows="12" aria-label="Terms"><?= escapeHtml(documentHtmlPurify((string) $t_value('details'))) ?></textarea>
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/contract_placeholders.php'; ?>
    </div>
</div>
