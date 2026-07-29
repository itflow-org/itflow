<?php
require_once '../../includes/modal_header.php';

// With a client_id we're editing that client's overrides, otherwise adding new
$client_id = intval($_GET['client_id'] ?? 0);

$client_name = '';
$client_assignments = [];
if ($client_id) {
    $client_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT client_name FROM clients WHERE client_id = $client_id LIMIT 1"));
    $client_name = escapeHtml($client_row['client_name']);

    $sql_assignments = mysqli_query($mysqli, "SELECT sla_assignment_priority, sla_assignment_sla_id FROM sla_assignments WHERE sla_assignment_client_id = $client_id");
    while ($assignment_row = mysqli_fetch_assoc($sql_assignments)) {
        $client_assignments[$assignment_row['sla_assignment_priority']] = intval($assignment_row['sla_assignment_sla_id']);
    }
}

$sql_slas = mysqli_query($mysqli, "SELECT sla_id, sla_name FROM slas WHERE sla_archived_at IS NULL ORDER BY sla_name ASC");
$active_slas = [];
while ($sla_row = mysqli_fetch_assoc($sql_slas)) {
    $active_slas[intval($sla_row['sla_id'])] = $sla_row['sla_name'];
}

ob_start();
?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fas fa-fw fa-random mr-2"></i><?= $client_id ? "Editing SLA overrides: <strong>$client_name</strong>" : "New client SLA override" ?></h5>
    <button type="button" class="close text-white" data-dismiss="modal">
        <span>&times;</span>
    </button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="modal-body">

        <div class="form-group">
            <label>Client <strong class="text-danger">*</strong></label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                </div>
                <?php if ($client_id) { ?>
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <input type="text" class="form-control" value="<?= $client_name ?>" disabled>
                <?php } else { ?>
                    <select class="form-control select2" name="client_id" required>
                        <option value="">- Client -</option>
                        <?php
                        $sql_clients = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients WHERE client_archived_at IS NULL ORDER BY client_name ASC");
                        while ($client_row = mysqli_fetch_assoc($sql_clients)) { ?>
                            <option value="<?= intval($client_row['client_id']) ?>"><?= escapeHtml($client_row['client_name']) ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div>
        </div>

        <?php foreach (['Low', 'Medium', 'High'] as $priority) {
            $current = $client_assignments[$priority] ?? 'default';
            ?>
            <div class="form-group">
                <label><?= $priority ?> priority</label>
                <select class="form-control" name="client_sla_<?= strtolower($priority) ?>">
                    <option value="default" <?php if ($current === 'default') { echo "selected"; } ?>>Default (follow global)</option>
                    <option value="0" <?php if ($current === 0) { echo "selected"; } ?>>None (no SLA)</option>
                    <?php foreach ($active_slas as $active_sla_id => $active_sla_name) { ?>
                        <option value="<?= $active_sla_id ?>" <?php if ($current === $active_sla_id) { echo "selected"; } ?>><?= escapeHtml($active_sla_name) ?></option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>

    </div>

    <div class="modal-footer">
        <button type="submit" name="save_client_sla_assignment" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>
        <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
