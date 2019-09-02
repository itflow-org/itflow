<div class="card">
	<div class="card-header bg-dark text-white">
		<h6 class="float-left mt-1"><i class="fa fa-edit"></i><?php echo $note_subject; ?></h6>
		<button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#editNoteModal"><i class="fa fa-plus"></i></button>
	</div>
	<div class="card-body">
		<?php
		$Parsedown = new Parsedown();
		echo $Parsedown->text("$note_body");
		?>
	</div>
</div>