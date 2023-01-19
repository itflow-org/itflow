<?php include("inc_all_settings.php"); ?>

<div class="card card-dark">
  <div class="card-header">
    <h3 class="card-title"><i class="fa fa-fw fa-palette"></i> Theme</h3>
  </div>
  <div class="card-body">
    <form action="post.php" method="post" autocomplete="off">

      <label>Select a Theme</label>
        <div class="form-row">

          <?php
          
          foreach($themes_array as $theme_color) {
          
          ?>
          
          <div class="col-3 text-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="theme" value="<?php echo $theme_color; ?>" <?php if($config_theme == $theme_color){ echo "checked"; } ?>>
              <label class="form-check-label">
                <i class="fa fa-fw fa-6x fa-circle text-<?php echo $theme_color; ?>"></i>
                <br>
                <?php echo $theme_color; ?>
              </label>
            </div>
          </div>
         
          <?php } ?>
          
        </div>

      <hr>
      
      <button type="submit" name="edit_theme_settings" class="btn btn-primary text-bold"><i class="fa fa-check"></i> Set Theme</button>
        
    </form>
  </div>
</div>

<?php include("footer.php");