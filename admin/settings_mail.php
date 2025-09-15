<?php
require_once "includes/inc_all_admin.php";
 ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>SMTP Mail Settings <small>(For Sending Email)</small></h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <!-- SMTP Provider -->
                <div class="form-group">
                    <label>SMTP Provider</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span>
                        </div>
                        <select class="form-control" name="config_smtp_provider" id="config_smtp_provider">
                            <option value="none" <?php if(($config_smtp_provider ?? '')==='none' || ($config_smtp_provider ?? '')==='') echo 'selected'; ?>>None (Disabled)</option>
                            <option value="standard_smtp" <?php if(($config_smtp_provider ?? 'standard_smtp')==='standard_smtp') echo 'selected'; ?>>Standard SMTP (Username/Password)</option>
                            <option value="google_oauth" <?php if(($config_smtp_provider ?? '')==='google_oauth') echo 'selected'; ?>>Google Workspace (OAuth)</option>
                            <option value="microsoft_oauth" <?php if(($config_smtp_provider ?? '')==='microsoft_oauth') echo 'selected'; ?>>Microsoft 365 (OAuth)</option>
                        </select>
                    </div>
                    <small class="text-secondary d-block mt-1" id="smtp_provider_hint">
                        Choose your SMTP provider. OAuth options ignore the SMTP password here.
                    </small>
                </div>


                <!-- Standard SMTP fields (show only for standard_smtp) -->
                <div id="smtp_standard_fields">
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
                            <input type="text" class="form-control" name="config_smtp_username" placeholder="Username (Leave blank if no auth is required)" value="<?php echo nullable_htmlentities($config_smtp_username); ?>">
                        </div>
                    </div>

                    <div class="form-group" id="smtp_password_group">
                        <div class="form-group">
                            <label>SMTP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                                </div>
                                <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="Password (Leave blank if no auth is required)" value="<?php echo nullable_htmlentities($config_smtp_password); ?>" autocomplete="new-password">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <hr>

                <button type="submit" name="edit_mail_smtp_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>IMAP Mail Settings <small>(For Monitoring Ticket Inbox)</small></h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label>IMAP Provider</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span>
                        </div>
                        <select class="form-control" name="config_imap_provider" id="config_imap_provider">
                            <option value="none" <?php if($config_imap_provider ==='') echo 'selected'; ?>>None (Disabled)</option>
                            <option value="standard_imap" <?php if(($config_imap_provider ?? 'standard_imap')==='standard_imap') echo 'selected'; ?>>Standard IMAP (Username/Password)</option>
                            <option value="google_oauth" <?php if(($config_imap_provider ?? '')==='google_oauth') echo 'selected'; ?>>Google Workspace (OAuth)</option>
                            <option value="microsoft_oauth" <?php if(($config_imap_provider ?? '')==='microsoft_oauth') echo 'selected'; ?>>Microsoft 365 (OAuth)</option>
                        </select>
                    </div>
                    <small class="text-secondary d-block mt-1" id="imap_provider_hint">
                    Select your mailbox provider. OAuth options ignore the IMAP password here.
                    </small>
                </div>
                <div id="standard_fields" style="display:none;">
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
                </div>

                <div class='form-group'>
                    <label>IMAP Username</label>
                    <div class='input-group'>
                        <div class='input-group-prepend'>
                            <span class='input-group-text'><i class='fa fa-fw fa-user'></i></span>
                        </div>
                        <input type='text' class='form-control' name='config_imap_username' placeholder='Username (email address)' value="<?php echo nullable_htmlentities($config_imap_username); ?>" required>
                    </div>
                </div>

                <div class='form-group' id="imap_password_group">
                    <label>IMAP Password</label>
                    <div class='input-group'>
                        <div class='input-group-prepend'>
                            <span class='input-group-text'><i class='fa fa-fw fa-key'></i></span>
                        </div>
                        <input type='password' class='form-control' data-toggle='password' name='config_imap_password' placeholder='Password (not used for OAuth)' value="<?php echo nullable_htmlentities($config_imap_password); ?>" autocomplete='new-password'>
                        <div class='input-group-append'>
                            <span class='input-group-text'><i class='fa fa-fw fa-eye'></i></span>
                        </div>
                    </div>
                </div>

                <!-- OAuth shared fields (show for google_oauth / microsoft_oauth) -->
                <div id="smtp_oauth_fields" style="display:none;">
                    <hr>
                    <h5 class="mb-2">OAuth Settings (shared for IMAP & SMTP)</h5>
                    <p class="text-secondary" id="oauth_hint">
                        Configure OAuth credentials for the selected provider.
                    </p>

                    <div class="form-group">
                        <label>OAuth Client ID</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span></div>
                            <input type="text" class="form-control" name="config_mail_oauth_client_id"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_client_id ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>OAuth Client Secret</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                            <input type="password" class="form-control" data-toggle="password" name="config_mail_oauth_client_secret"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_client_secret ?? ''); ?>" autocomplete="new-password">
                            <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                        </div>
                    </div>

                    <div class="form-group" id="tenant_row" style="display:none;">
                        <label>Tenant ID (Microsoft 365 only)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-building"></i></span></div>
                            <input type="text" class="form-control" name="config_mail_oauth_tenant_id"
                                   value="<?php echo nullable_htmlentities($config_mail_oauth_tenant_id ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Refresh Token</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-sync-alt"></i></span></div>
                            <textarea class="form-control" name="config_mail_oauth_refresh_token" rows="2"
                                      placeholder="Paste refresh token"><?php echo nullable_htmlentities($config_mail_oauth_refresh_token ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Access Token (optional – will refresh if expired)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-shield-alt"></i></span></div>
                            <textarea class="form-control" name="config_mail_oauth_access_token" rows="2"
                                      placeholder="Can be left blank; system refreshes using the refresh token"><?php echo nullable_htmlentities($config_mail_oauth_access_token ?? ''); ?></textarea>
                        </div>
                        <small class="text-secondary">
                            Expires at: <?php echo !empty($config_mail_oauth_access_token_expires_at) ? htmlspecialchars($config_mail_oauth_access_token_expires_at) : 'n/a'; ?>
                        </small>
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_mail_imap_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Mail From Configuration</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <p>Each of the "From Email" Addresses need to be able to send email on behalf of the SMTP user configured above
                <h5>System Default</h5>
                <p class="text-secondary">(used for system tasks such as sending share links)</p>
                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_mail_from_email" placeholder="Email Address (ex noreply@yourcompany.com)" value="<?php echo nullable_htmlentities($config_mail_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_mail_from_name" placeholder="Name (ex YourCompany)" value="<?php echo nullable_htmlentities($config_mail_from_name); ?>">
                    </div>
                </div>

                <h5>Invoices</h5>
                <p class="text-secondary">(used for when invoice emails are sent)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_invoice_from_email" placeholder="Email (ex billing@yourcompany.com)" value="<?php echo nullable_htmlentities($config_invoice_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_invoice_from_name" placeholder="Name (ex CompanyName Billing)" value="<?php echo nullable_htmlentities($config_invoice_from_name); ?>">
                    </div>
                </div>

                <h5>Quotes</h5>
                <p class="text-secondary">(used for when quote emails are sent)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_quote_from_email" placeholder="Email (ex sales@yourcompany.com)" value="<?php echo nullable_htmlentities($config_quote_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_quote_from_name" placeholder="Name (ex YourCompany Sales)" value="<?php echo nullable_htmlentities($config_quote_from_name); ?>">
                    </div>
                </div>

                <h5>Tickets</h5>
                <p class="text-secondary">(used for when tickets are created and emailed to a client)</p>

                <div class="form-group">
                    <label>From Email</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                        </div>
                        <input type="email" class="form-control" name="config_ticket_from_email" placeholder="Email (ex support@yourcompany.com)" value="<?php echo nullable_htmlentities($config_ticket_from_email); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>From Name</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                        </div>
                        <input type="text" class="form-control" name="config_ticket_from_name" placeholder="Name (ex YourCompany Support)" value="<?php echo nullable_htmlentities($config_ticket_from_name); ?>">
                    </div>
                </div>

                <hr>

                <button type="submit" name="edit_mail_from_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save</button>

            </form>
        </div>
    </div>

    <?php if (!empty($config_smtp_host) && !empty($config_smtp_port) && !empty($config_mail_from_email) && !empty($config_mail_from_name)) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-paper-plane mr-2"></i>Test Email Sending</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="input-group">
                    <select class="form-control select2" name="test_email" required>
                        <option value="">- Select an Email Address to send from -</option>
                        <?php
                        if ($config_mail_from_email) {
                        ?>
                        <option value="1"><?php echo nullable_htmlentities($config_mail_from_name); ?> (<?php echo nullable_htmlentities($config_mail_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_invoice_from_email) {
                        ?>
                        <option value="2"><?php echo nullable_htmlentities($config_invoice_from_name); ?> (<?php echo nullable_htmlentities($config_invoice_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_quote_from_email) {
                        ?>
                        <option value="3"><?php echo nullable_htmlentities($config_quote_from_name); ?> (<?php echo nullable_htmlentities($config_quote_from_email); ?>)</option>
                        <?php } ?>

                        <?php
                        if ($config_ticket_from_email) {
                        ?>
                        <option value="4"><?php echo nullable_htmlentities($config_ticket_from_name); ?> (<?php echo nullable_htmlentities($config_ticket_from_email); ?>)</option>
                        <?php } ?>


                    </select>
                    <input type="email" class="form-control " name="email_to" placeholder="Email address to send to">
                    <div class="input-group-append">
                        <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php } ?>

    <?php if (!empty($config_imap_username) && !empty($config_imap_password) && !empty($config_imap_host) && !empty($config_imap_port)) { ?>

    <div class="card card-dark">
        <div class="card-header py-3">
            <h3 class="card-title"><i class="fas fa-fw fa-plug mr-2"></i>Test IMAP Connection</h3>
        </div>
        <div class="card-body">
            <form action="post.php" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

                <div class="input-group-append">
                    <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox mr-2"></i>Test</button>
                </div>
            </form>
        </div>
    </div>

    <?php } ?>

<script>
(function(){
  function setDisabled(container, disabled){
    if(!container) return;
    container.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !!disabled);
  }

  function wireProvider(selectId, standardWrapId, passwordGroupId, oauthWrapId, tenantRowId, hintId, oauthHintId){
    const sel   = document.getElementById(selectId);
    const std   = document.getElementById(standardWrapId);
    const pwd   = document.getElementById(passwordGroupId);
    const oauth = document.getElementById(oauthWrapId);
    const ten   = document.getElementById(tenantRowId);
    const hint  = document.getElementById(hintId);
    const ohint = document.getElementById(oauthHintId);

    function toggle(){
      const v = (sel && sel.value) || '';
      const isNone  = (v === 'none' || v === '');
      const isStd   = v === 'standard_smtp' || v === 'standard_imap';
      const isG     = v === 'google_oauth';
      const isM     = v === 'microsoft_oauth';
      const isOAuth = isG || isM;

      if (std)   std.style.display   = isStd ? '' : 'none';
      if (pwd)   pwd.style.display   = isStd ? '' : 'none';
      if (oauth) oauth.style.display = isOAuth ? '' : 'none';
      if (ten)   ten.style.display   = isM ? '' : 'none';

      setDisabled(std,   !isStd);
      setDisabled(pwd,   !isStd);
      setDisabled(oauth, !isOAuth);

      if (hint) {
        hint.textContent = isNone
          ? 'Disabled.'
          : isStd
            ? 'Standard: provide host, port, encryption, username & password.'
            : isG
              ? 'Google OAuth: set Client ID/Secret; paste a refresh token; username should be the mailbox email.'
              : 'Microsoft 365 OAuth: set Client ID/Secret/Tenant; paste a refresh token; username should be the mailbox email.';
      }
      if (ohint) {
        ohint.textContent = isG
          ? 'Google Workspace OAuth: Client ID/Secret from Google Cloud; Refresh token via consent.'
          : isM
            ? 'Microsoft 365 OAuth: Client ID/Secret/Tenant from Entra ID; Refresh token via consent.'
            : 'Configure OAuth credentials for the selected provider.';
      }
    }

    if (sel) { sel.addEventListener('change', toggle); toggle(); }
  }

  // IMAP (you already have these IDs in your page)
  wireProvider('config_imap_provider', 'standard_fields', 'imap_password_group',
               'oauth_fields', 'tenant_row', 'imap_provider_hint', 'oauth_hint');

  // SMTP (the IDs we just added)
  wireProvider('config_smtp_provider', 'smtp_standard_fields', 'smtp_password_group',
               'smtp_oauth_fields', 'smtp_tenant_row', 'smtp_provider_hint', 'smtp_oauth_hint');
})();
</script>

<?php require_once "../includes/footer.php";
