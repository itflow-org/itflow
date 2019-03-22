<?php $sql = mysqli_query($mysqli,"SELECT * FROM client_notes WHERE client_id = $client_id ORDER BY client_note_id DESC"); ?>

<div class="table-responsive">
  <table class="table table-striped table-borderless table-hover" id="dataTable" width="100%" cellspacing="0">
    <thead>
      <tr>
        <th>Subject</th>
        <th>Note</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
  
      while($row = mysqli_fetch_array($sql)){
        $client_note_id = $row['client_note_id'];
        $client_note_subject = $row['client_note_subject'];
        $client_note_body = $row['client_note_body'];
  
      ?>
      <tr>
        <td><?php echo "$client_note_subject"; ?></td>
        <td><?php echo "$client_note_body"; ?></td>
        <td>
          <div class="dropdown dropleft text-center">
            <button class="btn btn-secondary btn-sm" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <i class="fas fa-ellipsis-h"></i>
            </button>
            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
              <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editClientNoteModal<?php echo $client_note_id; ?>">Edit</a>
              <a class="dropdown-item" href="post.php?delete_client_note=<?php echo $client_note_id; ?>">Delete</a>
            </div>
          </div>      
        </td>
      </tr>

      <?php
      include("edit_client_note_modal.php");
      }
      ?>

    </tbody>
  </table>
</div>