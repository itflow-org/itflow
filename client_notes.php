<?php $sql = mysqli_query($mysqli,"SELECT * FROM notes WHERE client_id = $client_id ORDER BY note_id DESC"); ?>

<div class="card">
  <div class="card-header">
    <h6 class="float-left mt-1"><i class="fa fa-edit"></i> Notes</h6>
    <button class="btn btn-primary btn-sm float-right" data-toggle="modal" data-target="#addNoteModal"><i class="fa fa-plus"></i></button>
  </div>
  <div class="card-body">

    <?php
    
    while($row = mysqli_fetch_array($sql)){
      $note_id = $row['note_id'];
      $note_subject = $row['note_subject'];
      $note_body = $row['note_body'];
    
    ?>
        <div class="card mb-5">
          <div class="card-header">
            
              <?php echo $note_subject; ?>
              <div class="dropdown dropleft text-center float-right">
                <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  <i class="fas fa-ellipsis-h"></i>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#viewNoteModal<?php echo $note_id; ?>">View</a>
                  <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editNoteModal<?php echo $note_id; ?>">Edit</a>
                  <a class="dropdown-item" href="post.php?delete_note=<?php echo $note_id; ?>">Delete</a>
                </div>
              </div>     
            
          </div>
          <div class="card-body">
            <?php
            $Parsedown = new Parsedown();
            echo $Parsedown->text("$note_body");
            ?>
          </div>
          <div class="card-footer">
            <small>Created 2019-02-13 - Updated 2019-04-22</small>
          </div>
        </div>
     
      <?php 
      include("view_note_modal.php");
      include("edit_note_modal.php");
      
    } 

    ?>
  </div>
</div>

<?php include("add_note_modal.php"); ?>
