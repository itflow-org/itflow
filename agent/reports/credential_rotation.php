<?php

/*
 * Credential rotation - every client login whose password hasn't changed in
 * a while (90 days unless another span is picked), oldest first, with how
 * many each client has. Archived credentials are left out; ones with no
 * change date recorded count as due. Each opens on its client's credentials
 * page, where it can be changed (credentials.php?open=ID).
 */

require_once "includes/inc_all_reports.php";

enforceUserPermission('module_credential');

$day_options = [30 => '30 days', 60 => '60 days', 90 => '90 days', 180 => '6 months', 365 => 'A year'];
$days = intval($_GET['days'] ?? CREDENTIAL_ROTATE_DAYS);
if (!isset($day_options[$days])) {
    $days = CREDENTIAL_ROTATE_DAYS;
}

$sql = mysqli_query($mysqli,
    "SELECT credential_id, credential_name, credential_description, credential_uri, credential_password_changed_at, credential_accessed_at,
            credential_client_id, client_name
        FROM credentials
        JOIN clients ON credential_client_id = client_id
        WHERE credential_archived_at IS NULL AND client_archived_at IS NULL
        AND (credential_password_changed_at IS NULL OR credential_password_changed_at < NOW() - INTERVAL $days DAY)
        " . clientScopeSql('credential_client_id') . "
        ORDER BY client_name ASC, credential_password_changed_at IS NOT NULL, credential_password_changed_at ASC, credential_name ASC"
);

$credentials = [];
$by_client = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $credentials[] = $row;
    $client = intval($row['credential_client_id']);
    $by_client[$client] = $by_client[$client] ?? ['name' => $row['client_name'], 'count' => 0, 'year' => 0];
    $by_client[$client]['count']++;
    $age = credentialAge($row['credential_password_changed_at']);
    if (!$age || $age['state'] === 'overdue') {
        $by_client[$client]['year']++;
    }
}

?>

    <div class="card card-dark">
        <div class="card-header py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-history me-2"></i>Credentials not changed in <?= strtolower($day_options[$days]) ?></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-primary d-print-none" onclick="window.print();"><i class="fas fa-fw fa-print me-2"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <form class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
                <label class="form-label mb-0" for="rotationDays">Not changed in</label>
                <select class="form-select w-auto" id="rotationDays" name="days" onchange="this.form.submit()">
                    <?php foreach ($day_options as $value => $label) { ?>
                        <option value="<?= $value ?>" <?= $value === $days ? 'selected' : '' ?>><?= $label ?></option>
                    <?php } ?>
                </select>
                <span class="text-body-secondary small ms-sm-2"><?= count($credentials) ?> credential<?= count($credentials) === 1 ? '' : 's' ?> across <?= count($by_client) ?> client<?= count($by_client) === 1 ? '' : 's' ?></span>
            </form>

            <?php if (!$credentials) { ?>
                <div class="itflow-asset-empty"><i class="fas fa-check-circle fa-2x mb-2 text-success opacity-75"></i><div>Every password has changed in the last <?= strtolower($day_options[$days]) ?>.</div></div>
            <?php } else { ?>

                <h6 class="text-body-secondary text-uppercase small fw-semibold">By client</h6>
                <div class="table-responsive-sm mb-4">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light"><tr><th>Client</th><th class="text-end">Due</th><th class="text-end">Over a year or never</th></tr></thead>
                        <tbody>
                        <?php foreach ($by_client as $client_id => $client) { ?>
                            <tr>
                                <td><a href="../credentials.php?client_id=<?= $client_id ?>&show=due&sort=credential_password_changed_at&order=ASC"><?= escapeHtml($client['name']) ?></a></td>
                                <td class="text-end"><?= $client['count'] ?></td>
                                <td class="text-end"><?= $client['year'] ? '<span class="text-danger fw-semibold">' . $client['year'] . '</span>' : '<span class="text-body-secondary">0</span>' ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <h6 class="text-body-secondary text-uppercase small fw-semibold">Credentials, oldest first</h6>
                <div class="table-responsive-sm">
                    <table class="table table-hover align-middle">
                        <thead class="table-light text-nowrap">
                        <tr>
                            <th>Client</th>
                            <th>Credential</th>
                            <th>Password last changed</th>
                            <th>Last used</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($credentials as $row) {
                            $client_id = intval($row['credential_client_id']);
                            $age = credentialAge($row['credential_password_changed_at']); ?>
                            <tr>
                                <td class="text-nowrap"><?= escapeHtml($row['client_name']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= credentialTile($row, 'sm') ?>
                                        <div class="min-w-0">
                                            <a class="fw-semibold" href="../credentials.php?client_id=<?= $client_id ?>&open=<?= intval($row['credential_id']) ?>"><?= escapeHtml($row['credential_name']) ?></a>
                                            <?php if ($row['credential_description']) { ?><div class="small text-body-secondary"><?= escapeHtml($row['credential_description']) ?></div><?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($age) { ?>
                                        <?= credentialAgePill($age) ?>
                                        <div class="small text-body-secondary mt-1"><?= escapeHtml($age['date']) ?></div>
                                    <?php } else { ?>
                                        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis border border-danger-subtle fw-normal">Never recorded</span>
                                    <?php } ?>
                                </td>
                                <td class="text-nowrap small text-body-secondary"><?= $row['credential_accessed_at'] ? timeAgo($row['credential_accessed_at']) : '-' ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

<?php
require_once "../../includes/footer.php";
