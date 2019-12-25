<?php include("header.php"); ?>

<div class="row justify-content-md-center">
	<div class="col-md-5">
		<div class="card mt-5">
		  <div class="card-body bg-light text-center">
		  	<h2 class="mb-3">Where did everybody go?</h2>
		  	<i class="far fa-fw fa-4x fa-frown-open mb-3"></i>
		  	<h4 class="mb-4">why not invite someone over?</h4>
		  	<button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-fw fa-plus"></i></button>
		  </div>
		</div>
	</div>
</div>

<?php include("add_client_modal.php"); ?>
<?php include("footer.php"); ?>