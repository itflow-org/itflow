<?php
require_once("inc_all_settings.php"); ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>Mail Settings</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">

                <div class="form-group">
                    <label>SMTP Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_smtp_host" placeholder="Mail Server Address" value="<?php echo nullable_htmlentities($config_smtp_host); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>SMTP Port</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_smtp_port" placeholder="Mail Server Port Number" value="<?php echo intval($config_smtp_port); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Encryption</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <select class="form-control" name="config_smtp_encryption">
                            <option value=''>None</option>
                            <option <?php if ($config_smtp_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                            <option <?php if ($config_smtp_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>SMTP Username</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_smtp_username" placeholder="Username" value="<?php echo nullable_htmlentities($config_smtp_username); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>SMTP Password</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                        </div>
                        <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="Password" value="<?php echo nullable_htmlentities($config_smtp_password); ?>" autocomplete="new-password" required>
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_mail_from_email" placeholder="Email Address" value="<?php echo nullable_htmlentities($config_mail_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_mail_from_name" placeholder="Name" value="<?php echo nullable_htmlentities($config_mail_from_name); ?>">
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <label>IMAP Host</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_imap_host" placeholder="Incoming Mail Server Address (for email to ticket parsing)" value="<?php echo nullable_htmlentities($config_imap_host); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>IMAP Port</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span>
                        </div>
                        <input type="number" min="0" class="form-control" name="config_imap_port" placeholder="Incoming Mail Server Port Number (993)" value="<?php echo intval($config_imap_port); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>IMAP Encryption</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                        </div>
                        <select class="form-control" name="config_imap_encryption">
                            <option value=''>None</option>
                            <option <?php if ($config_imap_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                            <option <?php if ($config_imap_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                        </select>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_mail_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

<?php if (!empty($config_smtp_host) && !empty($config_smtp_port) && !empty($config_smtp_username) && !empty($config_smtp_password) && !empty($config_mail_from_email) && !empty($config_mail_from_name)) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Test Email Sending</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <div class="input-group">
                    <input type="email" class="form-control " name="email" placeholder="Email address to test">
                    <div class="input-group-append">
                        <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<?php } ?>

<?php if (!empty($config_smtp_username) && !empty($config_smtp_password) && !empty($config_imap_host) && !empty($config_imap_port)) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Test Email Receiving</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <div class="input-group-append">
                    <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox mr-2"></i>Test</button>
                </div>
            </form>
        </div>
    </div>

<?php } ?>

<?php require_once("footer.php");
