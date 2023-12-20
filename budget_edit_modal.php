<div class="modal" id="editBudgetModal<?php echo $budget_id; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title text-white"><i class="fa fa-fw fa-balance-scale mr-2"></i>Editing Budget</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">
                    <input type="hidden" name="budget_id" value="<?php echo $budget_id; ?>">

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Month <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <select class="form-control select2" name="month" required>
                                    <option value="">- Select a Month -</option>
                                    <?php
                                    $months = [
                                        1 => '01 - January',
                                        2 => '02 - February',
                                        3 => '03 - March',
                                        4 => '04 - April',
                                        5 => '05 - May',
                                        6 => '06 - June',
                                        7 => '07 - July',
                                        8 => '08 - August',
                                        9 => '09 - September',
                                        10 => '10 - October',
                                        11 => '11 - November',
                                        12 => '12 - December'
                                    ];

                                    foreach ($months as $num => $name) {
                                        echo '<option value="' . $num . '"';
                                        if ($budget_month == $num) {
                                            echo ' selected';
                                        }
                                        echo '>' . $name . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Year <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric"
                                    pattern="^[0-9]{4}$" name="year" placeholder="2024"
                                    value="<?php echo $budget_year; ?>" required>
                            </div>
                        </div>

                    </div>

                    <div class="form-group">
                        <label>Description <strong class="text-danger">*</strong></label>
                        <textarea class="form-control" rows="6" name="description"
                            placeholder="Enter a description"required>
                            <?php echo $budget_description; ?>
                        </textarea>
                    </div>

                    <div class="form-row">

                        <div class="form-group col-md">
                            <label>Amount <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                </div>
                                <input type="text" class="form-control" inputmode="numeric"
                                    pattern="[0-9]*\.?[0-9]{0,2}" name="amount"
                                    value="<?php echo number_format($budget_amount, 2, '.', ''); ?>"
                                    placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="form-group col-md">
                            <label>Category <strong class="text-danger">*</strong></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                </div>
                                <select class="form-control select2" name="category" required>
                                    <?php

                                    $sql_select = mysqli_query(
                                        $mysqli,
                                        "SELECT category_id, category_name FROM categories
                                        WHERE category_type = 'Expense'
                                        AND (category_archived_at > '$expense_created_at'
                                        OR category_archived_at IS NULL)
                                        ORDER BY category_name ASC"
                                    );
                                    while ($row = mysqli_fetch_array($sql_select)) {
                                        $category_id_select = intval($row['category_id']);
                                        $category_name_select = nullable_htmlentities($row['category_name']);
                                        ?>
                                        <option <?php 
                                            if ($budget_category_id == $category_id_select) 
                                                { ?> selected <?php } ?>
                                                value="<?php echo $category_id_select; ?>">
                                                <?php echo $category_name_select; ?>
                                        </option>
                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="edit_budget" class="btn btn-primary text-bold">
                        <i class="fas fa-check mr-2"></i>Save
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
