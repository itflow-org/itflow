<div class="modal" id="addCreditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-dark">
            <div class="modal-header bg-dark">
                <h5 class="modal-title"><i class="fas fa-fw fa-wallet me-2"></i>Adding <strong>Credit</strong> (Credit Balance: <?= billingMoney($credit_balance, $client_currency_code) ?>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Expire</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-calendar-day"></i></span>
                            <input type="date" class="form-control" name="expire" max="2999-12-31">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Type <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-th-list"></i></span>
                            <select class="form-select select2" name="type" required>
                                <option value="0">- Select a credit type -</option>
                                <option value="manual">Manual</option>
                                <option value="prepaid">Prepaid</option>
                                <option value="promotion">Promotion</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-dollar-sign"></i></span>
                            <input type="text" class="form-control" inputmode="decimal" pattern="[0-9]*\.?[0-9]{0,2}" name="amount" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Note <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-fw fa-file-alt"></i></span>
                            <input type="text" class="form-control" name="note" placeholder="What the credit is for" maxlength="250">
                        </div>
                    </div>

                    <?php if (isset($_GET['client_id'])) { ?>
                        <input type="hidden" name="client" value="<?= $client_id ?>">
                    <?php } else { ?>

                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
                                <select class="form-select select2" name="client" required>
                                    <option value="0">- No client -</option>
                                    <?php

                                    $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients ORDER BY client_name ASC");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                        $client_id = intval($row['client_id']);
                                        $client_name = escapeHtml($row['client_name']);
                                        ?>
                                        <option value="<?= $client_id ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= $client_name ?></option>

                                        <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                    <?php } ?>

                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_credit" class="btn btn-primary fw-bold"><i class="fas fa-fw fa-check me-2"></i>Create</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
