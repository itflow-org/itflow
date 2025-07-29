<div class="modal" id="linkContactToDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-user mr-2"></i>Link Contact to <strong><?php echo $document_name; ?></strong></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="document_id" value="<?php echo $document_id; ?>">
                <div class="modal-body">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            </div>
                            <select class="form-control select2" name="contact_id">
                                <option value="">- Select a Contact -</option>
                                <?php
                                // Check if there are any associated vendors
                                if (!empty($linked_contacts)) {
                                    $excluded_contact_ids = implode(",", $linked_contacts);
                                    $exclude_condition = "AND contact_id NOT IN ($excluded_contact_ids)";
                                } else {
                                    $exclude_condition = "";  // No condition if there are no displayed vendors
                                }

                                $sql_contacts_select = mysqli_query($mysqli, "SELECT * FROM contacts
                                    WHERE contact_client_id = $client_id 
                                    AND contact_archived_at IS NULL
                                    $exclude_condition
                                    ORDER BY contact_name ASC"
                                );
                                while ($row = mysqli_fetch_array($sql_contacts_select)) {
                                    $contact_id = intval($row['contact_id']);
                                    $contact_name = nullable_htmlentities($row['contact_name']);

                                    ?>
                                    <option value="<?php echo $contact_id ?>"><?php echo $contact_name; ?></option>
                                    <?php
                                }
                                ?>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="link_contact_to_document" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Link</button>
                    <button type="button" class="btn btn-light" data-dismiss="modal"><i class="fa fa-times mr-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
