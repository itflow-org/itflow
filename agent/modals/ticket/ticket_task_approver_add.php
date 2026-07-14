<?php

require_once '../../../includes/modal_header.php';

$task_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT * FROM tasks
    WHERE task_id = $task_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
$task_name = escapeHtml($row['task_name']);

// Generate the HTML form content using output buffering.
ob_start();

?>

    <div class="modal-header bg-dark">
        <h5 class="modal-title"><i class="fa fa-fw fa-shield-alt mr-2"></i>New approver for task <?=$task_name?></h5>
        <button type="button" class="close text-white" data-dismiss="modal">
            <span>&times;</span>
        </button>
    </div>
    <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">

        <div class="modal-body">

            <div class="form-group">
                <label>Approval scope <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                    </div>
                    <select class="form-control" name="approval_scope" id="approval_scope" required>
                        <option value="">Select scope...</option>
                        <option value="internal">Internal</option>
                        <option value="client">Client</option>
                    </select>
                </div>
            </div>


            <div class="form-group d-none" id="approval_type_wrapper">
                <label>Who can approve? <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                    </div>
                    <select class="form-control" name="approval_type" id="approval_type" required>
                        <!-- JS -->
                    </select>
                </div>
            </div>


            <div class="form-group d-none" id="specific_user_wrapper">
                <label>Select specific internal approver <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                    </div>
                    <select class="form-control select2" name="approval_required_user_id" id="specific_user_select">
                        <option value="">Select user...</option>
                    </select>
                </div>
            </div>


        </div>

        <div class="modal-footer">
            <button type="submit" name="add_ticket_task_approver" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>
            <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
        </div>

    </form>


<!-- JS to make the correct boxes appear depending on if internal/client approval) -->
<script>
    $('#approval_scope').on('change', function() {
        const scope = $(this).val();
        const typeSelect = $('#approval_type');
        const wrapper = $('#approval_type_wrapper');

        typeSelect.empty();
        $('#specific_user_wrapper').addClass('d-none');

        if (!scope) {
            wrapper.addClass('d-none');
            return;
        }

        wrapper.removeClass('d-none');

        if (scope === 'internal') {
            typeSelect.append('<option value="">Select...</option>');
            typeSelect.append('<option value="any">Any internal reviewer</option>');
            typeSelect.append('<option value="specific">Specific agent</option>');
        }

        if (scope === 'client') {
            typeSelect.append('<option value="">Select...</option>');
            typeSelect.append('<option value="any">Ticket contact</option>');
            typeSelect.append('<option value="technical">Technical contacts</option>');
            typeSelect.append('<option value="billing">Billing contacts</option>');
        }
    });

    // Specific user (internal only for now)
    $('#approval_type').on('change', function() {
        const type = $(this).val();
        const scope = $('#approval_scope').val();
        const userSelect = $('#specific_user_select');

        if (type !== 'specific' || scope !== 'internal') {
            $('#specific_user_wrapper').addClass('d-none');
            return;
        }

        $('#specific_user_wrapper').removeClass('d-none');
        userSelect.empty().append('<option value="">Loading...</option>');

        $.getJSON('ajax.php?get_internal_users=true', function(data) {
            userSelect.empty().append('<option value="">Select user...</option>');
            data.users.forEach(function(u) {
                userSelect.append(`<option value="${u.user_id}">${u.user_name}</option>`);
            });
        });
    });

</script>

<?php

require_once '../../../includes/modal_footer.php';
