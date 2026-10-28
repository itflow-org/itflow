<?php

/*
 * Onboarding - kickoff: the client's projects so far, and the onboarding
 * project to start - its tickets raised from a project template (New Client
 * Onboarding, when you have it), with a manager, the client's contact, a
 * start and a due date (client_onboarding_kickoff). The tickets go to the
 * manager when asked, else to whoever each template names.
 */

defined('FROM_CLIENT_ONBOARDING') || die("Direct file access is not allowed");

$co_projects = [];
$co_sql = mysqli_query($mysqli, "SELECT project_id, project_prefix, project_number, project_name, project_due, project_completed_at,
        (SELECT COUNT(*) FROM tickets WHERE ticket_project_id = project_id) AS tickets
    FROM projects WHERE project_client_id = $client_id AND project_archived_at IS NULL ORDER BY project_id DESC");
while ($co_project = mysqli_fetch_assoc($co_sql)) {
    $co_projects[] = $co_project;
}
$co_templates = [];
$co_pick = '';
$co_sql = mysqli_query($mysqli, "SELECT project_template_id, project_template_name,
        (SELECT COUNT(*) FROM project_template_ticket_templates WHERE project_template_ticket_templates.project_template_id = project_templates.project_template_id) AS tickets
    FROM project_templates WHERE project_template_archived_at IS NULL ORDER BY project_template_name ASC");
while ($co_template = mysqli_fetch_assoc($co_sql)) {
    $co_id = intval($co_template['project_template_id']);
    $co_templates[$co_id] = $co_template['project_template_name'] . ' - ' . intval($co_template['tickets']) . ' ticket' . (intval($co_template['tickets']) === 1 ? '' : 's');
    // One about onboarding is the likely one
    if ($co_pick === '' && stripos($co_template['project_template_name'], 'onboard') !== false) {
        $co_pick = (string) $co_id;
    }
}
$co_agents = [];
$co_sql = mysqli_query($mysqli, "SELECT user_id, user_name FROM users WHERE user_type = 1 AND user_status = 1 AND user_archived_at IS NULL ORDER BY user_name ASC");
while ($co_agent = mysqli_fetch_assoc($co_sql)) {
    $co_agents[intval($co_agent['user_id'])] = $co_agent['user_name'];
}
$co_contacts = [];
$co_contact_pick = '0';
$co_sql = mysqli_query($mysqli, "SELECT contact_id, contact_name, contact_primary FROM contacts WHERE contact_client_id = $client_id AND contact_archived_at IS NULL ORDER BY contact_primary DESC, contact_name ASC");
while ($co_contact = mysqli_fetch_assoc($co_sql)) {
    $co_contacts[intval($co_contact['contact_id'])] = $co_contact['contact_name'];
    if (intval($co_contact['contact_primary']) && $co_contact_pick === '0') {
        $co_contact_pick = (string) $co_contact['contact_id'];
    }
}

?>
<?php if ($co_projects) { ?>
    <ul class="list-unstyled itflow-gs-people mb-4" data-co-projects="<?= count($co_projects) ?>">
        <?php foreach ($co_projects as $co_project) { ?>
            <li data-co-project="<?= intval($co_project['project_id']) ?>">
                <span class="itflow-gs-person-initials" aria-hidden="true"><i class="fas fa-project-diagram"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <a href="/agent/<?= projectUrl($co_project['project_id'], $client_id) ?>" class="fw-semibold text-truncate d-block" target="_blank"><?= escapeHtml(projectReference($co_project) . ' - ' . $co_project['project_name']) ?></a>
                    <div class="small text-body-secondary"><?= intval($co_project['tickets']) ?> ticket<?= intval($co_project['tickets']) === 1 ? '' : 's' ?><?= !empty($co_project['project_due']) ? ' - due ' . escapeHtml(date('M j, Y', strtotime($co_project['project_due']))) : '' ?></div>
                </div>
                <?php if (!empty($co_project['project_completed_at'])) { ?><span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-normal">Completed</span><?php } ?>
            </li>
        <?php } ?>
    </ul>
    <h2 class="h6 fw-semibold">Start another</h2>
<?php } ?>

<form action="post.php" method="post" autocomplete="off" data-gs-form data-co-form="kickoff">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="row g-3 mb-3">
        <div class="col-12">
            <?php if ($co_templates) { ?>
                <?= settingsSelect('project_template_id', $co_pick, ['0' => '- None - an empty project -'] + $co_templates, ['id' => 'coProjectTemplate', 'icon' => 'fa-clone', 'label' => 'Template',
                    'help' => 'Its tickets are raised on the project, with their tasks - for the client, not emailed to them.']) ?>
            <?php } else { ?>
                <div class="small text-body-secondary" data-co-no-templates><i class="fas fa-info-circle me-1"></i>No project templates yet - New Client Onboarding is in the Project templates pack in Maintenance &gt; Starter Content. Without one the project starts empty.</div>
            <?php } ?>
        </div>
        <div class="col-12">
            <?= settingsInput('name', 'Onboarding - ' . $co_name, ['id' => 'coProjectName', 'icon' => 'fa-project-diagram', 'label' => 'Name', 'required' => true, 'maxlength' => 255]) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsSelect('manager', (string) intval($session_user_id), ['0' => '- Nobody -'] + $co_agents, ['id' => 'coProjectManager', 'icon' => 'fa-user-tie', 'label' => 'Manager']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsSelect('contact_id', $co_contact_pick, ['0' => '- Nobody -'] + $co_contacts, ['id' => 'coProjectContact', 'icon' => 'fa-user', 'label' => 'Their contact']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('start', date('Y-m-d'), ['id' => 'coProjectStart', 'icon' => 'fa-calendar-day', 'type' => 'date', 'label' => 'Starts', 'max' => '2999-12-31']) ?>
        </div>
        <div class="col-sm-6">
            <?= settingsInput('due', date('Y-m-d', strtotime('+30 days')), ['id' => 'coProjectDue', 'icon' => 'fa-calendar-check', 'type' => 'date', 'label' => 'Due', 'max' => '2999-12-31']) ?>
        </div>
        <?php if ($co_templates) { ?>
            <div class="col-12">
                <?= settingsSwitch('assign_to_manager', '1', ['id' => 'coProjectAssign', 'label' => 'Assign its tickets to the manager', 'help' => 'Off, each goes to whoever its ticket template names, or nobody.']) ?>
            </div>
        <?php } ?>
    </div>
    <button type="submit" name="client_onboarding_kickoff" value="1" class="btn btn-primary" data-gs-save><i class="fas fa-flag me-2"></i>Start the project</button>
</form>
