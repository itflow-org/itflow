<?php

/*
 * A contract's fields, in tabs - for contract_add.php and contract_edit.php,
 * which set $form_contract (a contractRow(), or null when new), $form_copy (a
 * contract being copied, or null) and $form_client_id (0 to pick the client
 * here). Agreement: the template it starts from, the client, name, type,
 * contact, owner, and its term - start, length, end, renewal and notice.
 * Cover: support hours, rates, net terms and an SLA for each priority.
 * Terms: the agreement's words. Notes: internal. Picking a template fills
 * the fields from it, and the end date follows the start and term until it
 * is changed (js/contract_form.js). The server checks it all again
 * (contractFromInput()).
 */

isset($mysqli) || die("Direct file access is not allowed");

$c = $form_contract ?? $form_copy ?? null;
$c_new = empty($form_contract);
$c_client_id = intval($c['contract_client_id'] ?? $form_client_id);
$c_templates = $c_new ? contractTemplates() : [];
$c_slas = contractSlaChoices($c ? array_map(function ($part) use ($c) { return intval($c["contract_sla_{$part}_id"]); }, CONTRACT_SLA_PRIORITIES) : []);
$c_value = function ($key, $default = '') use ($c) {
    return $c !== null && array_key_exists("contract_$key", $c) && $c["contract_$key"] !== null ? $c["contract_$key"] : $default;
};
$c_start = $c_new ? date('Y-m-d') : (string) $c_value('start_date');
$c_term = intval($c_value('term_months', 12));
$c_end = $c_new ? contractTermEnd($c_start, $c_term) : (string) $c_value('end_date');
$c_money = function ($value) {
    return $value === '' || $value === null ? '' : number_format((float) $value, 2, '.', '');
};

// The clients to pick from, and their contacts - shown for the client picked
$c_clients = [];
$c_contacts = [];
$client_where = $c_client_id ? "client_id = $c_client_id" : "client_archived_at IS NULL " . clientScopeSql('clients.client_id');
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, client_rate, client_net_terms FROM clients WHERE $client_where ORDER BY client_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $c_clients[intval($row['client_id'])] = $row;
}
if ($c_clients) {
    $sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_title, contact_client_id, contact_primary FROM contacts
        WHERE contact_client_id IN (" . implode(',', array_keys($c_clients)) . ") AND (contact_archived_at IS NULL OR contact_id = " . intval($c_value('contact_id', 0)) . ")
        ORDER BY contact_primary DESC, contact_name ASC");
    while ($row = mysqli_fetch_assoc($sql)) {
        $c_contacts[] = $row;
    }
}
$c_contact_id = intval($c_value('contact_id', 0));
if ($c_new && !$c_contact_id && $c_client_id) {
    foreach ($c_contacts as $contact) {
        if (intval($contact['contact_primary']) === 1 && intval($contact['contact_client_id']) === $c_client_id) {
            $c_contact_id = intval($contact['contact_id']);
            break;
        }
    }
}
$c_owner_id = intval($c_value('owner_id', $c_new ? ($session_user_id ?? 0) : 0));
$c_owners = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND (user_archived_at IS NULL OR user_id = $c_owner_id) ORDER BY user_name ASC");
while ($row = mysqli_fetch_assoc($sql)) {
    $c_owners[intval($row['user_id'])] = $row['user_name'];
}

// The templates, for filling the fields from one
$c_template_data = [];
foreach ($c_templates as $template_id => $template) {
    $c_template_data[$template_id] = [
        'name' => $template['contract_template_name'], 'type' => $template['contract_template_type'], 'term_months' => intval($template['contract_template_term_months']),
        'auto_renew' => intval($template['contract_template_auto_renew']), 'notice_days' => intval($template['contract_template_notice_days']),
        'rate_standard' => $c_money($template['contract_template_rate_standard']), 'rate_after_hours' => $c_money($template['contract_template_rate_after_hours']),
        'net_terms' => $template['contract_template_net_terms'] === null ? '' : intval($template['contract_template_net_terms']), 'support_hours' => (string) $template['contract_template_support_hours'],
        'details' => documentHtmlPurify((string) $template['contract_template_details']),
    ];
    foreach (CONTRACT_SLA_PRIORITIES as $part) {
        $c_template_data[$template_id]["sla_$part"] = intval($template["contract_template_sla_{$part}_id"]);
    }
}
$tab = 'contractTab' . bin2hex(random_bytes(3));

?>

<div data-contract-form>
    <?php if ($c_template_data) { ?>
        <script type="application/json" data-contract-templates><?= json_encode($c_template_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php } ?>

    <ul class="nav nav-pills nav-justified mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="pill" href="#<?= $tab ?>Agreement" role="tab"><i class="fas fa-fw fa-file-contract me-1"></i>Agreement</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#<?= $tab ?>Cover" role="tab"><i class="fas fa-fw fa-shield-alt me-1"></i>Cover</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#<?= $tab ?>Terms" role="tab"><i class="fas fa-fw fa-file-alt me-1"></i>Terms</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="pill" href="#<?= $tab ?>Notes" role="tab"><i class="fas fa-fw fa-sticky-note me-1"></i>Notes</a></li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="<?= $tab ?>Agreement" role="tabpanel">
            <?php if ($c_template_data) { ?>
                <div class="mb-3">
                    <label class="form-label">Start from a template</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-clone"></i></span>
                        <select class="form-select" name="template_id" data-contract-template>
                            <option value="0">- None -</option>
                            <?php foreach ($c_templates as $template_id => $template) { ?>
                                <option value="<?= $template_id ?>"<?= intval($c_value('template_id', 0)) === $template_id ? ' selected' : '' ?>><?= escapeHtml($template['contract_template_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <small class="text-secondary">Fills in the type, term, cover and terms from it - change any of them after.</small>
                </div>
            <?php } else { ?>
                <input type="hidden" name="template_id" value="<?= intval($c_value('template_id', 0)) ?>">
            <?php } ?>

            <?php if (count($c_clients) > 1 || !$c_client_id) { ?>
                <div class="mb-3">
                    <label class="form-label">Client <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        <select class="form-select select2" name="client_id" required data-contract-client>
                            <option value="">- Pick a client -</option>
                            <?php foreach ($c_clients as $client_option_id => $client_option) { ?>
                                <option value="<?= $client_option_id ?>"<?= selectOptionClient($client_option_id, $client_option['client_name'], $client_option['client_abbreviation'] ?? '') ?>><?= escapeHtml($client_option['client_name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } else { ?>
                <input type="hidden" name="client_id" value="<?= $c_client_id ?>" data-contract-client>
            <?php } ?>

            <div class="row g-3 mb-3">
                <div class="col-md-7">
                    <label class="form-label">Name <strong class="text-danger">*</strong></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        <input type="text" class="form-control" name="name" maxlength="255" placeholder="Managed Services Agreement" value="<?= escapeHtml($form_copy ? $c_value('name') . ' (copy)' : $c_value('name')) ?>" required<?= $c_new ? ' autofocus' : '' ?> data-contract-field="name">
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Type</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        <select class="form-select" name="type" data-contract-field="type">
                            <?php foreach (CONTRACT_TYPES as $key => $type) { ?>
                                <option value="<?= $key ?>"<?= $c_value('type', 'managed') === $key ? ' selected' : '' ?> title="<?= escapeHtml($type['about']) ?>"><?= escapeHtml($type['label']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Contact at the client</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-address-card"></i></span>
                        <select class="form-select" name="contact_id" data-contract-contact>
                            <option value="0">- None -</option>
                            <?php foreach ($c_contacts as $contact) { ?>
                                <option value="<?= intval($contact['contact_id']) ?>" data-client="<?= intval($contact['contact_client_id']) ?>"<?= intval($contact['contact_id']) === $c_contact_id ? ' selected' : '' ?><?= $c_client_id && intval($contact['contact_client_id']) !== $c_client_id ? ' hidden' : '' ?>><?= escapeHtml($contact['contact_name'] . ($contact['contact_title'] ? " - {$contact['contact_title']}" : '')) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-tie"></i></span>
                        <select class="form-select" name="owner_id">
                            <option value="0">- No one -</option>
                            <?php foreach ($c_owners as $user_id => $user_name) { ?>
                                <option value="<?= $user_id ?>"<?= $user_id === $c_owner_id ? ' selected' : '' ?>><?= escapeHtml($user_name) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <small class="text-secondary">Reminded before it renews or ends.</small>
                </div>
            </div>

            <?php if ($c_new) { ?>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                        <select class="form-select" name="status">
                            <option value="Active">Active - in force from its start date</option>
                            <option value="Draft">Draft - not in force yet</option>
                        </select>
                    </div>
                </div>
            <?php } ?>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Starts</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                        <input type="date" class="form-control" name="start_date" value="<?= escapeHtml($c_start) ?>" data-contract-start>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Term</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-history"></i></span>
                        <select class="form-select" name="term_months" data-contract-field="term_months" data-contract-term>
                            <?php $terms = CONTRACT_TERMS + ($c_term && !isset(CONTRACT_TERMS[$c_term]) ? [$c_term => contractTermLabel($c_term)] : []);
                            ksort($terms);
                            foreach ($terms as $months => $label) { ?>
                                <option value="<?= $months ?>"<?= $months === $c_term ? ' selected' : '' ?>><?= escapeHtml($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ends</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar-check"></i></span>
                        <input type="date" class="form-control" name="end_date" value="<?= escapeHtml($c_end) ?>" data-contract-end<?= $c_new ? '' : ' data-touched="1"' ?>>
                    </div>
                </div>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="auto_renew" value="1" id="<?= $tab ?>AutoRenew"<?= intval($c_value('auto_renew', 1)) ? ' checked' : '' ?> data-contract-field="auto_renew">
                        <label class="form-check-label" for="<?= $tab ?>AutoRenew">Renews itself for another term</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notice to end it</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-bell"></i></span>
                        <input type="number" class="form-control" name="notice_days" min="0" max="365" value="<?= intval($c_value('notice_days', 30)) ?>" data-contract-field="notice_days">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
            </div>
            <small class="text-secondary d-block mt-2" data-contract-notice-note></small>
        </div>

        <div class="tab-pane fade" id="<?= $tab ?>Cover" role="tabpanel">
            <div class="mb-3">
                <label class="form-label">Support hours</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-business-time"></i></span>
                    <input type="text" class="form-control" name="support_hours" maxlength="100" placeholder="Mon-Fri 8am-6pm, emergencies 24/7" value="<?= escapeHtml($c_value('support_hours')) ?>" data-contract-field="support_hours">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Hourly rate</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                        <input type="text" inputmode="decimal" class="form-control" name="rate_standard" placeholder="The client's" value="<?= escapeHtml($c_money($c_value('rate_standard', ''))) ?>" data-contract-field="rate_standard">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">After hours</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-moon"></i></span>
                        <input type="text" inputmode="decimal" class="form-control" name="rate_after_hours" placeholder="None" value="<?= escapeHtml($c_money($c_value('rate_after_hours', ''))) ?>" data-contract-field="rate_after_hours">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Net terms</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                        <input type="number" class="form-control" name="net_terms" min="0" max="365" placeholder="The client's" value="<?= escapeHtml((string) $c_value('net_terms', '')) ?>" data-contract-field="net_terms">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
            </div>
            <small class="text-secondary d-block mb-3">Leave a rate or the net terms empty and the client's own apply.</small>

            <label class="form-label">SLA by ticket priority</label>
            <?php foreach (CONTRACT_SLA_PRIORITIES as $priority => $part) { ?>
                <div class="input-group mb-2">
                    <span class="input-group-text" style="width: 7rem;"><i class="fa fa-fw fa-stopwatch me-2"></i><?= $priority ?></span>
                    <select class="form-select" name="sla_<?= $part ?>" data-contract-field="sla_<?= $part ?>" aria-label="<?= $priority ?> priority SLA">
                        <option value="0">The client's usual SLA</option>
                        <?php foreach ($c_slas as $sla_id => $sla) { ?>
                            <option value="<?= $sla_id ?>"<?= intval($c_value("sla_{$part}_id", 0)) === $sla_id ? ' selected' : '' ?>><?= escapeHtml($sla['sla_name'] . ' - ' . contractSlaTargets($sla)) ?><?= $sla['sla_archived_at'] ? ' (archived)' : '' ?></option>
                        <?php } ?>
                    </select>
                </div>
            <?php } ?>
            <small class="text-secondary">From Admin > SLAs.</small>
        </div>

        <div class="tab-pane fade" id="<?= $tab ?>Terms" role="tabpanel">
            <textarea class="form-control tinymce" name="details" rows="12" aria-label="Terms" data-contract-field="details"><?= escapeHtml(documentHtmlPurify((string) $c_value('details'))) ?></textarea>
        </div>

        <div class="tab-pane fade" id="<?= $tab ?>Notes" role="tabpanel">
            <div class="input-group">
                <span class="input-group-text align-items-start pt-2"><i class="fa fa-fw fa-sticky-note"></i></span>
                <textarea class="form-control" name="notes" rows="8" maxlength="10000" placeholder="For your team - what was agreed, who to talk to about renewing"><?= escapeHtml($c_value('notes')) ?></textarea>
            </div>
            <small class="text-secondary">Only agents see these.</small>
        </div>
    </div>
</div>

<script src="/js/contract_form.js"></script>
