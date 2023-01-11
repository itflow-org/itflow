<?php

$contact_phone = formatPhoneNumber($contact_phone);
$contact_mobile = formatPhoneNumber($contact_mobile);
$location_phone = formatPhoneNumber($location_phone);

?>

<div class="card mb-3">
  <div class="card-body">
    <div class="row">
      <div class="col-md">
        <h4 class="text-secondary"><strong><?php echo $client_name; ?></strong></h4>
        <?php if(!empty($location_address)){ ?>
        <a href="//maps.<?php echo $session_map_source; ?>.com/?q=<?php echo "$location_address $location_zip"; ?>" target="_blank">
          <div><i class="fa fa-fw fa-map-marker-alt text-secondary ml-1 mr-1"></i> <?php echo $location_address; ?></div>
          <div class="ml-4 mb-2"><?php echo "$location_city $location_state $location_zip"; ?></div>
        </a>
        <?php } ?>
        <?php
        if(!empty($location_phone)){
        ?>
          <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <a href="tel:<?php echo $location_phone?>"><?php echo $location_phone; ?></a>
        <br>
        <?php 
        } 
        ?>
        <?php
        if(!empty($client_website)){
        ?>
          <i class="fa fa-fw fa-globe text-secondary ml-1 mr-2 mb-2"></i> <a target="_blank" href="//<?php echo $client_website; ?>"><?php echo $client_website; ?></a>
        <br>
        <?php 
        }
        ?>
        <?php
        if(!empty($client_tag_name_display_array)){
        ?>
        <?php echo $client_tags_display; ?>
        <?php 
        }
        ?>
      </div>
      <div class="col-md border-left">
        <h4 class="text-secondary">Contact</h4>
        <?php
        if(!empty($contact_name)){
        ?>
          <i class="fa fa-fw fa-user text-secondary ml-1 mr-2 mb-2"></i> <?php echo $contact_name; ?>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_email)){
        ?>
          <i class="fa fa-fw fa-envelope text-secondary ml-1 mr-2 mb-2"></i> <a href="mailto:<?php echo $contact_email; ?>"><?php echo $contact_email; ?></a><button class='btn btn-sm clipboardjs' data-clipboard-text='<?php echo $contact_email; ?>'><i class='far fa-copy text-secondary'></i></button>
        <br>
        <?php
        }
        ?>
        <?php
        if(!empty($contact_phone)){
        ?>
          <i class="fa fa-fw fa-phone text-secondary ml-1 mr-2 mb-2"></i> <a href="tel:<?php echo $contact_phone; ?>"><?php echo $contact_phone; ?> </a>
        <?php 
        if(!empty($contact_extension)){ 
        ?>
        x<?php echo $contact_extension; ?>
        <?php
        }
        ?>
        <br>
        <?php 
        } 
        ?>
        <?php
        if(!empty($contact_mobile)){
        ?>
          <i class="fa fa-fw fa-mobile-alt text-secondary ml-1 mr-2 mb-2"></i> <a href="tel:<?php echo $contact_mobile; ?>"> <?php echo $contact_mobile; ?> </a>
        <?php 
        } 
        ?>
      </div>
      <?php if($session_user_role == 1 || $session_user_role == 3 AND $config_module_enable_accounting == 1){ ?>
      <div class="col-md border-left">
        <h4 class="text-secondary">Billing</h4>
        <h6 class="ml-1 text-secondary">Paid <div class="text-dark float-right"> <?php echo numfmt_format_currency($currency_format, $amount_paid, $client_currency_code); ?></div></h6>
        <h6 class="ml-1 text-secondary">Balance <div class="<?php if($balance > 0){ echo "text-danger"; }else{ echo "text-dark"; } ?> float-right"> <?php echo numfmt_format_currency($currency_format, $balance, $client_currency_code); ?></div></h6>
        <h6 class="ml-1 text-secondary">Monthly Recurring <div class="text-dark float-right"> <?php echo numfmt_format_currency($currency_format, $recurring_monthly, $client_currency_code); ?></div></h6>
        <h6 class="ml-1 text-secondary">Net Terms <div class="text-dark float-right"><?php echo $client_net_terms; ?> <small class="text-secondary">Days</small></div></h6>
      </div>
      <?php } ?>
      <?php if($config_module_enable_ticketing == 1){ ?>
      <div class="col-md border-left">
        <h4 class="text-secondary">Support</h4>
        <h6 class="ml-1 text-secondary">Open Tickets <div class="text-dark float-right"><?php echo $num_active_tickets; ?></div></h6>
      </div>
      <?php } ?>
      <div class="col-md-1 border-left">
        <?php if($session_user_role == 3) { ?>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-dark btn-sm float-right" type="button" data-toggle="dropdown">
              <i class="fas fa-fw fa-ellipsis-v"></i>
            </button>
            <div class="dropdown-menu">
              <a class="dropdown-item" href="post.php?export_client_pdf=<?php echo $client_id; ?>" target="_blank">Export Data PDF<br><small class="text-secondary">(without passwords)</small></a>
              <a class="dropdown-item" href="post.php?export_client_pdf=<?php echo $client_id; ?>&passwords" target="_blank">Export Data PDF<br><small class="text-secondary">(with passwords)</small></a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientModal<?php echo $client_id; ?>">Edit Client</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger" href="post.php?archive_client=<?php echo $client_id; ?>">Archive Client</a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#deleteClientModal<?php echo $client_id; ?>">Delete Client</a>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<?php 
  
  include("client_edit_modal.php");
  include("client_delete_modal.php");
  include("category_quick_add_modal.php");

?>