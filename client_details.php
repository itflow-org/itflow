<table class="table">
  <tr>
    <td><i class="fa fa-map-marker"></i></td>
    <td>
      <a href="//maps.<?php echo $session_map_source; ?>.com/?q=<?php echo "$client_address $client_zip"; ?>" target="_blank">
        <?php echo $client_address; ?>
        <br>
        <?php echo "$client_city $client_state $client_zip"; ?>
      </a>
    </td>
  <tr>
    <td><i class="fa fa-phone"></i></td>
    <td><?php echo $client_phone; ?></td>
  </tr>
  <tr>      
    <td><i class="fa fa-envelope"></i></td>
    <td><a href="mailto:<?php echo $client_email; ?>"><?php echo $client_email; ?></a></td>
  </tr>
  <tr> 
    <td><i class="fa fa-globe"></i></td>
    <td><a href="http://<?php echo $client_website; ?>"><?php echo $client_website; ?></a></td>
  </tr>
</table>