<div class="modal" id="addCreditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-dark">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fa fa-fw fa-wallet me-2"></i>Adding <strong>Credit</strong> (Credit Balance: <?= numfmt_format_currency($currency_format, $credit_balance, $client_currency_code) ?>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">

                    <div class="mb-3">
                        <label>Expire</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar-day"></i></span>
                            <input type="date" class="form-control" name="expire" max="2999-12-31">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Type<strong class="text-danger ms-2">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-th-list"></i></span>
                            <select class="form-select select2" name="type" required>
                                <option value="0">- Select Credit Type -</option>
                                <option value="manual">Manual</option>
                                <option value="prepaid">Prepaid</option>
                                <option value="promotion">Promotion</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Amount<strong class="text-danger ms-2">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                            <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Note<strong class="text-danger ms-2">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-file-alt"></i></span>
                            <input type="text" class="form-control" name="note" placeholder="Enter a note" maxlength="250">
                        </div>
                    </div>

                    <?php if (isset($_GET['client_id'])) { ?>
                        <input type="hidden" name="client" value="<?= $client_id ?>">
                    <?php } else { ?>

                        <div class="mb-3">
                            <label>Client</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="client" required>
                                    <option value="0">- Client (Optional) -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name FROM clients ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']);
                                        ?>
                                        <option value="<?= $client_id ?>"><?= $client_name ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                    <?php } ?>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_credit" class="btn btn-primary text-bold"><i class="fa fa-fw fa-check me-2"></i>Add</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
