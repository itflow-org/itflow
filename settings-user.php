<?php include("header.php");



?>

<div class="card mb-3">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-cog mr-2"></i>User Settings</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <input type="hidden" name="secretkey" value="<?php echo $secret_key; ?>">
      <div class="custom-control custom-switch mb-2">
        <input type="checkbox" class="custom-control-input" name="en2fa" <?php if($en2fa == 1){ echo "checked"; } ?> value="1" id="customSwitch1">
        <label class="custom-control-label" for="customSwitch1">Enable Two Factor Authentication</label>
      </div>

     <?php
        
        require_once('rfc6238.php');

        //Generate a base32 Key
        $secretkey = key32gen();
        
        //Generate QR Code based off the generated key
        print sprintf('<img src="%s"/>',TokenAuth6238::getBarCodeUrl('','',$secretkey,'PittPC-CRM'));
  
      ?>
      
      <input type="hidden" name="token" value="<?php echo $secretkey; ?>">
      <hr>
      <button type="submit" name="settings_2fa" class="btn btn-primary">Enable</button>        
    
    </form>

    <hr>

    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Enter Code</label>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
          </div>
          <input type="text" class="form-control" name="code" placeholder="Enter Code" required>
        </div>
      </div>
      <hr>
      <button type="submit" name="verify" class="btn btn-primary">Verify</button>        
    
    </form>


  </div>
</div>

<?php include("footer.php"); ?>