<?php
/*
 * Client Portal
 * New ticket form
 */

require_once 'includes/inc_all.php';

?>

    <ol class="breadcrumb d-print-none">
        <li class="breadcrumb-item">
            <a href="index.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="tickets.php">Tickets</a>
        </li>
        <li class="breadcrumb-item active">New Ticket</li>
    </ol>

    <h3>Raise a new ticket</h3>

    <div class="col-md-8">
        <form action="post.php" method="post">

            <div class="form-group">
                <label>Subject <strong class="text-danger">*</strong></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                    </div>
                    <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <div class="form-group">
                        <label>Priority <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-thermometer-half"></i></span>
                            </div>
                            <select class="form-control select2" name="priority" required>
                                <option>Low</option>
                                <option>Medium</option>
                                <option>High</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="form-group">
                    <label>Category</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                        </div>
                        <select class="form-control select2" name="category">
                            <option value="0">- No Category -</option>
                            <?php
                            $sql_categories = mysqli_query($mysqli, "SELECT category_id, category_name FROM categories WHERE category_type = 'Ticket' AND category_archived_at IS NULL");
                            while ($row = mysqli_fetch_array($sql_categories)) {
                                $category_id = intval($row['category_id']);
                                $category_name = nullable_htmlentities($row['category_name']);

                                ?>
                                <option value="<?php echo $category_id; ?>"><?php echo $category_name; ?></option>
                            <?php } ?>

                        </select>
                    </div>
                </div>
                </div>
            </div>



            <div class="form-group">
                <label>Details <strong class="text-danger">*</strong></label>
                <textarea class="form-control tinymce" name="details"></textarea>
            </div>

            <button class="btn btn-primary" name="add_ticket">Raise ticket</button>

        </form>
    </div>

<?php
require_once 'includes/footer.php';

