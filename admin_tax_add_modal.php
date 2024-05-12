<div class="modal" id="addTaxModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fas fa-fw fa-balance-scale mr-2"></i>New Tax</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="post.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

        <div class="modal-body bg-white">
          <div class="form-group">
            <label>Name <strong class="text-danger">*</strong></label>
            <input type="text" class="form-control" name="name" placeholder="Tax name" required autofocus>
          </div>
          <div class="form-group">
            <label>Percent <strong class="text-danger">*</strong></label>
            <input type="number" min="0" step="any" class="form-control col-md-4" name="percent">
          </div>
        </div>
        <div class="modal-footer bg-white">
          <button type="submit" name="add_tax" class="btn btn-primary text-bold"><i class="fa fa-check mr- 2"></i>Create</button>
          <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fas fa-times mr-2"></i>Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
