<?php include("header.php"); ?>

<?php $sql = mysqli_query($mysqli,"SELECT * FROM accounts ORDER BY account_id DESC"); ?>

<?php include("settings-nav.php"); ?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-cog"></i> Defaults Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Transfer From Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-server"></i></span>
          </div>
          <input type="text" class="form-control" name="smtp_host" placeholder="Mail Server Address" required autofocus>
        </div>
      </div>
      
      <div class="form-group">
        <label>Transfer To Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="username" placeholder="Username" required>
        </div>
      </div>

      <div class="form-group">
        <label>Payment Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="username" placeholder="Username" required>
        </div>
      </div>

      <div class="form-group mb-5">
        <label>Expense Account</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-user"></i></span>
          </div>
          <input type="text" class="form-control" name="username" placeholder="Username" required>
        </div>
      </div>

      <hr>
      <button type="submit" name="add_client_contact" class="btn btn-primary">Save</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");