<?php

/*
 * Merge a client into another - for the same company added twice (functions/client.php
 * clientMerge(), merge_client). Pick the client to keep; this one's records all go to it, and
 * this one is left archived and empty. It can't be undone, so the window says what moves and
 * whose access changes first.
 */

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_client', 3);

$client_id = intval($_GET['client_id'] ?? 0);
enforceClientAccess();

$client = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id"));
if (!$client) {
    echo json_encode(['error' => 'That client no longer exists.']);
    exit;
}

$summary = clientMergeSummary($client_id);

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fas fa-fw fa-compress-alt me-2"></i>Merge <strong><?= escapeHtml($client['client_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off" data-client-merge="<?= $client_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <div class="modal-body">

        <p>For the same company added twice: everything <strong><?= escapeHtml($client['client_name']) ?></strong> has moves to the client you pick, and this one is left archived and empty.</p>

        <div class="mb-3">
            <label class="form-label">Merge into <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                <select class="form-select select2" name="into_client_id" required>
                    <option value="">- The client to keep -</option>
                    <?php
                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients
                        WHERE client_id != $client_id AND client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                    while ($row = mysqli_fetch_assoc($sql)) { ?>
                        <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation']) ?>><?= escapeHtml($row['client_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div class="small mb-2"><strong>Moves across:</strong>
            <?php if ($summary) { ?>
                <?= escapeHtml(implode(', ', array_map(function ($label, $n) { return "$n $label"; }, array_keys($summary), $summary))) ?>, and its tags, notes, history and the rest.
            <?php } else { ?>
                <span class="text-body-secondary">it has nothing yet but its details.</span>
            <?php } ?>
        </div>
        <div class="small text-body-secondary mb-3">The kept client's details, billing settings, primary contact and location stay as they are. This client's quick notes are added under the kept client's.</div>

        <!-- Restricted agents whose sight of these records changes - filled when a client is picked -->
        <div class="alert alert-warning small py-2" data-client-merge-access hidden></div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="clientMergeSure" required>
            <label class="form-check-label" for="clientMergeSure">I understand this can't be undone</label>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="merge_client" class="btn btn-danger text-bold"><i class="fas fa-compress-alt me-2"></i>Merge</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
    </div>
</form>

<script>
    (function () {
        var form = document.querySelector('form[data-client-merge]:not([data-wired])');
        if (!form) {
            return;
        }
        form.dataset.wired = '1';
        var into = form.querySelector('select[name="into_client_id"]');
        var access = form.querySelector('[data-client-merge-access]');
        into.addEventListener('change', function () {
            access.hidden = true;
            if (!into.value) {
                return;
            }
            itflowGet('ajax.php', { client_merge_access: form.dataset.clientMerge, into: into.value }, function (data) {
                var changes = JSON.parse(data).changes || [];
                access.textContent = '';
                if (!changes.length) {
                    return;
                }
                var head = document.createElement('div');
                head.className = 'fw-semibold mb-1';
                head.textContent = 'Some agents only see some clients - check their access after merging:';
                access.appendChild(head);
                changes.forEach(function (change) {
                    var line = document.createElement('div');
                    line.textContent = change.name + (change.what === 'gains' ? ' is kept off this client but not the other - they will see what moves.' : ' can see this client but not the other - they will lose sight of what moves.');
                    access.appendChild(line);
                });
                access.hidden = false;
            });
        });
    })();
</script>

<?php
require_once '../../../includes/modal_footer.php';
