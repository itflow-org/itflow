<div class="modal" id="addQuickCategoryExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-light">
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="type" value="Expense">
                    <input type="hidden" name="color" value="#000000">
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" placeholder="Category name" required autofocus>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-fw fa-times"></i></button>
                            <button type="submit" name="add_category" class="btn btn-primary"><i class="fa fa-fw fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="addQuickCategoryIncomeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-light">
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="type" value="Income">
                    <input type="hidden" name="color" value="#000000">
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" placeholder="Category name" required autofocus>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-fw fa-times"></i></button>
                            <button type="submit" name="add_category" class="btn btn-primary"><i class="fa fa-fw fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="addQuickVendorModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-light">
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" placeholder="Vendor name" required autofocus>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-fw fa-times"></i></button>
                            <button type="submit" name="add_vendor" class="btn btn-primary"><i class="fa fa-fw fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="addQuickReferralModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-light">
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="type" value="Referral">
                    <input type="hidden" name="color" value="#000000">
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" placeholder="Referral name" required autofocus>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-fw fa-times"></i></button>
                            <button type="submit" name="add_category" class="btn btn-primary"><i class="fa fa-fw fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="addQuickCalendarModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-light">
            <div class="modal-body">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="color" value="#000000">
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" placeholder="Calendar name" required autofocus>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-fw fa-times"></i></button>
                            <button type="submit" name="add_calendar" class="btn btn-primary"><i class="fa fa-fw fa-check"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
