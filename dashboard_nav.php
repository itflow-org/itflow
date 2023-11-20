<div class="row mb-3">
	<div class="col-md-12">
		<div class="btn-group btn-group-lg btn-block">
			<a href="dashboard.php" class="btn btn-<?php if (basename($_SERVER["PHP_SELF"]) == "dashboard.php") { echo "dark"; } else { echo "secondary"; } ?>">Personal <small>(WIP)</small></a>
			<?php if($config_module_enable_accounting == 1) { ?>
			<a href="dashboard_financial.php" class="btn btn-<?php if (basename($_SERVER["PHP_SELF"]) == "dashboard_financial.php") { echo "dark"; } else { echo "secondary"; } ?>">Administrative</a>
			<?php } ?>
			<a href="dashboard_technical.php" class="btn btn-<?php if (basename($_SERVER["PHP_SELF"]) == "dashboard_technical.php") { echo "dark"; } else { echo "secondary"; } ?>">Technical</a>
		</div>
	</div>
</div>