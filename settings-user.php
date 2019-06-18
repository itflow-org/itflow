<?php include("header.php"); ?>

<div class="card">
  <div class="card-header bg-dark text-white">
    <h6 class="float-left mt-1"><i class="fa fa-fw fa-lock mr-2"></i>Two Factor Authentication</h6>
  </div>
  <div class="card-body">
    <form class="p-3" action="post.php" method="post" autocomplete="off">

     <?php
        
        require_once('rfc6238.php');

        //Generate a base32 Key
        $secretkey = key32gen();
        
        if(!empty($session_token)){ 
          //Generate QR Code based off the generated key
          print sprintf('<img src="%s"/>',TokenAuth6238::getBarCodeUrl('','',$session_token,'PittPC-CRM'));
        }
  
      ?>
      
      <input type="hidden" name="token" value="<?php echo $secretkey; ?>">
     
      <hr>

      <?php if(empty($session_token)){ ?>
        <button type="submit" name="enable_2fa" class="btn btn-primary">Enable 2FA</button>
      <?php }else{ ?>
        <button type="submit" name="disable_2fa" class="btn btn-danger">Disable 2FA</button> 
      <?php } ?>      
    
    </form>

    <?php if(!empty($session_token)){ ?>
    <form class="p-3" action="post.php" method="post" autocomplete="off">
      <div class="form-group">
        <label>Verify 2FA is Working</label>
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
    <?php } ?>
  </div>
</div>

<?php include("footer.php"); ?>