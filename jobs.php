<?php

// Default Column Sortby/Order Filter
$sort = "job_id";
$order = "DESC";

require_once "inc_all.php";

// Perms
enforceUserPermission('module_jobs');

//Rebuild URL
$url_query_strings_sort = http_build_query($get_copy);

// Fetch jobs from the database
$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS * FROM jobs
    LEFT JOIN clients ON jobs.client_id = clients.client_id
    WHERE (jobs.scope LIKE '%$q%' OR jobs.status LIKE '%$q%' OR jobs.type LIKE '%$q%' OR clients.client_name LIKE '%$q%')
    ORDER BY $sort $order LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fa fa-briefcase mr-2"></i>Jobs</h3>
        <div class="card-tools">
            <?php if (lookupUserPermission("module_jobs") >= 2) { ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addJobModal"><i class="fas fa-plus mr-2"></i>New Job</button>
            <?php } ?>
        </div>
    </div>

    <div class="card-body">
        <form class="mb-4" autocomplete="off">
            <div class="row">
                <div class="col-sm-4">
                    <div class="input-group">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(nullable_htmlentities($q)); } ?>" placeholder="Search Jobs">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-dark" href="?<?php echo $url_query_strings_sort; ?>&sort=job_id&order=<?php echo $disp; ?>">
                            Job Number <?php if ($sort == 'job_id') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Client</th>
                    <th>Scope</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Dropbox Link</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php

                while ($row = mysqli_fetch_array($sql)) {
                    $job_id = intval($row['job_id']);
                    $client_id = intval($row['client_id']);
                    $client_name = nullable_htmlentities($row['client_name']);
                    $scope = nullable_htmlentities($row['scope']);
                    $type = nullable_htmlentities($row['type']);
                    $status = nullable_htmlentities($row['status']);
                    $dropbox_link = nullable_htmlentities($row['dropbox_link']);
                    ?>

                    <tr>
                        <td class="text-bold"><?php echo $job_id; ?></td>
                        <td class="text-bold"><a href="client_jobs.php?client_id=<?php echo $client_id; ?>"><?php echo $client_name; ?></a></td>
                        <td><?php echo $scope; ?></td>
                        <td><?php echo $type; ?></td>
                        <td><?php echo $status; ?></td>
                        <td><a href="<?php echo $dropbox_link; ?>" target="_blank">View Link</a></td>
                        <td>
							<div class="dropdown dropleft text-center">
								<button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
									<i class="fas fa-ellipsis-h"></i>
								</button>
								<div class="dropdown-menu">
									<a class="dropdown-item" href="#" 
									data-toggle="modal" 
									data-target="#editJobModal" 
									onclick="populateEditJobModal(
										'<?php echo $job_id; ?>', 
										'<?php echo addslashes($scope); ?>', 
										'<?php echo $type; ?>', 
										'<?php echo addslashes($status); ?>', 
										'<?php echo addslashes($dropbox_link); ?>')">
										<i class="fas fa-fw fa-edit mr-2"></i>Edit
									</a>
									  <!-- Delete Button -->
									  <div class="dropdown-divider"></div>
									<a class="dropdown-item text-danger confirm-link" 
									href="post.php?delete_job=<?php echo $job_id; ?>">
										<i class="fas fa-fw fa-trash mr-2"></i>Delete
									</a>
								</div>
							</div>
						</td>

                    </tr>

                <?php } ?>

                </tbody>
            </table>
        </div>
        <?php require_once "pagination.php"; ?>
    </div>
</div>
<script>
    function populateEditJobModal(job_id, scope, type, status, dropbox_link) {
        // Populate the fields in the modal with the received data
        document.getElementById('editJobID').value = job_id;
        document.getElementById('editJobScope').value = scope;
        document.getElementById('editJobType').value = type;
        document.getElementById('editJobStatus').value = status;
        document.getElementById('editJobDropboxLink').value = dropbox_link;
    }
</script>

<?php

require_once "job_add_modal.php";
require_once "job_edit_modal.php";
require_once "footer.php";
?>
