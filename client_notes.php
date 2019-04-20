<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_notes WHERE client_id = $client_id ORDER BY client_note_id DESC"); ?>


  <?php
  
  while($row = mysqli_fetch_array($sql)){
    $client_note_id = $row['client_note_id'];
    $client_note_subject = $row['client_note_subject'];
    $client_note_body = $row['client_note_body'];
  
    ?>
      <div class="card mb-5">
        <div class="card-header">
          
            <?php echo $client_note_subject; ?>
            <div class="dropdown dropleft text-center float-right">
              <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-ellipsis-h"></i>
              </button>
              <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#viewClientNoteModal<?php echo $client_note_id; ?>">View</a>
                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientNoteModal<?php echo $client_note_id; ?>">Edit</a>
                <a class="dropdown-item" href="post.php?delete_client_note=<?php echo $client_note_id; ?>">Delete</a>
              </div>
            </div>     
          
        </div>
        <div class="card-body">
          <?php
          $Parsedown = new Parsedown();
          echo $Parsedown->text("$client_note_body");
          ?>
        </div>
        <div class="card-footer">
          <small>Created 2019-02-13 - Updated 2019-04-22</small>
        </div>
      </div>
   
    <?php 
    include("view_client_note_modal.php");
    include("edit_client_note_modal.php");
     } ?>

