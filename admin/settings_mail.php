<?php
require_once "includes/inc_all_admin.php";

// ---- Tiny status dot for tab labels ----------------------------------------
function renderMailStatusDot($on) {
    return $on
        ? '<i class="fas fa-circle text-success ml-2" style="font-size:.5rem;vertical-align:middle;" title="Configured"></i>'
        : '<i class="far fa-circle text-muted ml-2" style="font-size:.5rem;vertical-align:middle;" title="Not configured"></i>';
}

$smtp_on = !empty($config_smtp_provider);
$imap_on = !empty($config_imap_provider);
$oauth_needed = in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)
             || in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true);

// ---- OAuth callback URI (for Entra App Registration) ------------------------
if (defined('BASE_URL') && !empty(BASE_URL)) {
    $mail_oauth_callback_uri = rtrim((string) BASE_URL, '/') . '/admin/oauth_microsoft_mail_callback.php';
} else {
    $mail_oauth_callback_uri = 'https://' . rtrim((string) $config_base_url, '/') . '/admin/oauth_microsoft_mail_callback.php';
}

// ---- Readiness checks (drive the Tests tab) --------------------------------
$smtp_standard_ready = !empty($config_smtp_host) && !empty($config_smtp_port)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name);

$smtp_oauth_ready = in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)
    && !empty($config_mail_from_email) && !empty($config_mail_from_name)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_smtp_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$imap_standard_ready = !empty($config_imap_username) && !empty($config_imap_password)
    && !empty($config_imap_host) && !empty($config_imap_port);

$imap_oauth_ready = in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true)
    && !empty($config_imap_username)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($config_imap_provider !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$oauth_provider_for_test = '';
if (in_array($config_imap_provider, ['google_oauth', 'microsoft_oauth'], true)) {
    $oauth_provider_for_test = $config_imap_provider;
} elseif (in_array($config_smtp_provider, ['google_oauth', 'microsoft_oauth'], true)) {
    $oauth_provider_for_test = $config_smtp_provider;
}

$oauth_has_required_fields = !empty($oauth_provider_for_test)
    && !empty($config_mail_oauth_client_id) && !empty($config_mail_oauth_client_secret)
    && !empty($config_mail_oauth_refresh_token)
    && ($oauth_provider_for_test !== 'microsoft_oauth' || !empty($config_mail_oauth_tenant_id));

$send_ready = $smtp_standard_ready || $smtp_oauth_ready;
$imap_ready = $imap_standard_ready || $imap_oauth_ready;
?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-envelope mr-2"></i>Mail Configuration</h3>
    </div>
    <div class="card-body">

        <ul class="nav nav-tabs" id="mailTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" href="#tab-smtp" data-target="#tab-smtp">
                    <i class="fas fa-fw fa-paper-plane mr-1"></i>Sending<?php echo renderMailStatusDot($smtp_on); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#tab-imap" data-target="#tab-imap">
                    <i class="fas fa-fw fa-inbox mr-1"></i>Receiving<?php echo renderMailStatusDot($imap_on); ?>
                </a>
            </li>
            <li class="nav-item" id="tabitem-oauth" style="<?php echo $oauth_needed ? '' : 'display:none;'; ?>">
                <a class="nav-link" href="#tab-oauth" data-target="#tab-oauth">
                    <i class="fas fa-fw fa-key mr-1"></i>OAuth
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#tab-from" data-target="#tab-from">
                    <i class="fas fa-fw fa-at mr-1"></i>From Addresses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#tab-tests" data-target="#tab-tests">
                    <i class="fas fa-fw fa-vial mr-1"></i>Tests
                </a>
            </li>
        </ul>

        <div class="tab-content pt-4">

            <!-- ============================ SENDING / SMTP ============================ -->
            <div class="tab-pane fade show active" id="tab-smtp" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label>SMTP Provider <small class="text-muted">— outbound</small></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span></div>
                            <select class="form-control" name="config_smtp_provider" id="config_smtp_provider">
                                <option value="" <?php if (empty($config_smtp_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                                <option value="standard_smtp" <?php if ($config_smtp_provider === 'standard_smtp') { echo 'selected'; } ?>>Standard SMTP (Username/Password)</option>
                                <option value="google_oauth" <?php if ($config_smtp_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                                <option value="microsoft_oauth" <?php if ($config_smtp_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (OAuth)</option>
                            </select>
                        </div>
                        <small class="form-text text-muted" id="smtp_provider_hint">Choose your outbound mail provider.</small>
                    </div>

                    <div id="smtp_conn_fields">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>SMTP Host</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-server"></i></span></div>
                                    <input type="text" class="form-control" name="config_smtp_host" placeholder="smtp.yourcompany.com" value="<?php echo escapeHtml($config_smtp_host); ?>" required>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Port</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span></div>
                                    <input type="text" class="form-control numeric-only" inputmode="numeric" pattern="[0-9]*" maxlength="5" name="config_smtp_port" placeholder="587 / 465 / 25" value="<?php echo !empty($config_smtp_port) ? intval($config_smtp_port) : ''; ?>" required>
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Encryption</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span></div>
                                    <select class="form-control" name="config_smtp_encryption">
                                        <option value="">None</option>
                                        <option <?php if ($config_smtp_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                        <option <?php if ($config_smtp_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6" id="smtp_user_group">
                            <label id="smtp_user_label">SMTP Username</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-user"></i></span></div>
                                <input type="text" class="form-control" name="config_smtp_username" id="config_smtp_username" placeholder="usually your full email address" value="<?php echo escapeHtml($config_smtp_username); ?>">
                            </div>
                            <small class="form-text text-muted" id="smtp_user_hint">Leave blank if no authentication is required.</small>
                        </div>
                        <div class="form-group col-md-6" id="smtp_pass_group">
                            <label>SMTP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_smtp_password" placeholder="mailbox or app password" value="<?php echo escapeHtml($config_smtp_password); ?>" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="smtp_oauth_pointer" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-fw fa-info-circle mr-2"></i>This provider uses OAuth — the password is ignored. Enter app credentials in the OAuth tab.</span>
                            <button type="button" class="btn btn-sm btn-outline-primary goto-oauth ml-3 text-nowrap"><i class="fas fa-fw fa-key mr-1"></i>Open OAuth</button>
                        </div>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_smtp_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Sending Settings</button>
                </form>
            </div>

            <!-- ============================ RECEIVING / IMAP ============================ -->
            <div class="tab-pane fade" id="tab-imap" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label>IMAP Provider <small class="text-muted">— inbound ticket inbox</small></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-cloud"></i></span></div>
                            <select class="form-control" name="config_imap_provider" id="config_imap_provider">
                                <option value="" <?php if (empty($config_imap_provider)) { echo 'selected'; } ?>>None (Disabled)</option>
                                <option value="standard_imap" <?php if ($config_imap_provider === 'standard_imap') { echo 'selected'; } ?>>Standard IMAP (Username/Password)</option>
                                <option value="google_oauth" <?php if ($config_imap_provider === 'google_oauth') { echo 'selected'; } ?>>Google Workspace (OAuth)</option>
                                <option value="microsoft_oauth" <?php if ($config_imap_provider === 'microsoft_oauth') { echo 'selected'; } ?>>Microsoft 365 (OAuth)</option>
                            </select>
                        </div>
                        <small class="form-text text-muted" id="imap_provider_hint">Select your mailbox provider.</small>
                    </div>

                    <div id="imap_conn_fields">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>IMAP Host</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-server"></i></span></div>
                                    <input type="text" class="form-control" name="config_imap_host" placeholder="imap.yourcompany.com" value="<?php echo escapeHtml($config_imap_host); ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Port</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-plug"></i></span></div>
                                    <input type="text" class="form-control numeric-only" inputmode="numeric" pattern="[0-9]*" maxlength="5" name="config_imap_port" placeholder="993 / 143" value="<?php echo !empty($config_imap_port) ? intval($config_imap_port) : ''; ?>">
                                </div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Encryption</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span></div>
                                    <select class="form-control" name="config_imap_encryption">
                                        <option value="">None</option>
                                        <option <?php if ($config_imap_encryption == 'tls') { echo "selected"; } ?> value="tls">TLS</option>
                                        <option <?php if ($config_imap_encryption == 'ssl') { echo "selected"; } ?> value="ssl">SSL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6" id="imap_user_group">
                            <label id="imap_user_label">IMAP Username</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-user"></i></span></div>
                                <input type="text" class="form-control" name="config_imap_username" placeholder="tickets@yourcompany.com" value="<?php echo escapeHtml($config_imap_username); ?>" required>
                            </div>
                            <small class="form-text text-muted" id="imap_user_hint">The mailbox address to monitor for incoming tickets.</small>
                        </div>
                        <div class="form-group col-md-6" id="imap_pass_group">
                            <label>IMAP Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_imap_password" placeholder="mailbox or app password" value="<?php echo escapeHtml($config_imap_password); ?>" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info" id="imap_oauth_pointer" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-fw fa-info-circle mr-2"></i>This provider uses OAuth — the password is ignored. Enter app credentials in the OAuth tab.</span>
                            <button type="button" class="btn btn-sm btn-outline-primary goto-oauth ml-3 text-nowrap"><i class="fas fa-fw fa-key mr-1"></i>Open OAuth</button>
                        </div>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_imap_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save Receiving Settings</button>
                </form>
            </div>

            <!-- ============================ OAUTH ============================ -->
            <div class="tab-pane fade" id="tab-oauth" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="alert alert-secondary" id="oauth_hint">
                        <i class="fas fa-fw fa-info-circle mr-2"></i>These credentials are shared by any Sending or Receiving provider set to Google / Microsoft OAuth.
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>OAuth Client ID</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-id-badge"></i></span></div>
                                <input type="text" class="form-control" name="config_mail_oauth_client_id" id="config_mail_oauth_client_id" placeholder="Application (client) ID" value="<?php echo escapeHtml($config_mail_oauth_client_id ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label>OAuth Client Secret</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-key"></i></span></div>
                                <input type="password" class="form-control" data-toggle="password" name="config_mail_oauth_client_secret" id="config_mail_oauth_client_secret" placeholder="Client secret value" value="<?php echo escapeHtml($config_mail_oauth_client_secret ?? ''); ?>" autocomplete="new-password">
                                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="tenant_row" style="display:none;">
                        <label>Tenant ID <small class="text-muted">— Microsoft 365 only</small></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-building"></i></span></div>
                            <input type="text" class="form-control" name="config_mail_oauth_tenant_id" placeholder="Directory (tenant) ID, e.g. 00000000-0000-0000-0000-000000000000" value="<?php echo escapeHtml($config_mail_oauth_tenant_id ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Refresh Token</label>
                            <textarea class="form-control" name="config_mail_oauth_refresh_token" rows="2" placeholder="Paste a refresh token, or use the Connect button below to fetch one"><?php echo escapeHtml($config_mail_oauth_refresh_token ?? ''); ?></textarea>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Access Token <small class="text-muted">— optional</small></label>
                            <textarea class="form-control" name="config_mail_oauth_access_token" rows="2" placeholder="Leave blank — auto-refreshed from the refresh token"><?php echo escapeHtml($config_mail_oauth_access_token ?? ''); ?></textarea>
                            <small class="form-text text-muted">Expires at: <?php echo !empty($config_mail_oauth_access_token_expires_at) ? htmlspecialchars($config_mail_oauth_access_token_expires_at) : 'n/a'; ?></small>
                        </div>
                    </div>

                    <div class="form-group" id="ms_connect_group" style="display:none;">
                        <label>Microsoft OAuth Connect (Web)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-fw fa-link"></i></span></div>
                            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($mail_oauth_callback_uri); ?>">
                            <div class="input-group-append">
                                <button type="submit" name="oauth_connect_microsoft_mail" class="btn btn-outline-primary">
                                    <i class="fab fa-fw fa-microsoft mr-2"></i>Connect Microsoft 365
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Add this callback URI in your Entra App Registration, save credentials, then click Connect to store the refresh token automatically.</small>
                    </div>

                    <hr>
                    <button type="submit" name="edit_mail_oauth_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save OAuth Credentials</button>
                </form>
            </div>

            <!-- ============================ FROM ADDRESSES ============================ -->
            <div class="tab-pane fade" id="tab-from" role="tabpanel">
                <form action="post.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <p class="text-muted">Each From address must be allowed to send on behalf of the SMTP user.</p>

                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width:26%">Purpose</th>
                                <th>From Email</th>
                                <th>From Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="align-middle">System Default<br><small class="text-muted">share links &amp; system tasks</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_mail_from_email" placeholder="noreply@yourcompany.com" value="<?php echo escapeHtml($config_mail_from_email); ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_mail_from_name" placeholder="YourCompany" value="<?php echo escapeHtml($config_mail_from_name); ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Invoices<br><small class="text-muted">sent when emailing invoices</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_invoice_from_email" placeholder="billing@yourcompany.com" value="<?php echo escapeHtml($config_invoice_from_email); ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_invoice_from_name" placeholder="YourCompany Billing" value="<?php echo escapeHtml($config_invoice_from_name); ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Quotes<br><small class="text-muted">sent when emailing quotes</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_quote_from_email" placeholder="sales@yourcompany.com" value="<?php echo escapeHtml($config_quote_from_email); ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_quote_from_name" placeholder="YourCompany Sales" value="<?php echo escapeHtml($config_quote_from_name); ?>"></td>
                            </tr>
                            <tr>
                                <td class="align-middle">Tickets<br><small class="text-muted">ticket creation &amp; client replies</small></td>
                                <td class="align-middle"><input type="email" class="form-control form-control-sm" name="config_ticket_from_email" placeholder="support@yourcompany.com" value="<?php echo escapeHtml($config_ticket_from_email); ?>"></td>
                                <td class="align-middle"><input type="text" class="form-control form-control-sm" name="config_ticket_from_name" placeholder="YourCompany Support" value="<?php echo escapeHtml($config_ticket_from_name); ?>"></td>
                            </tr>
                        </tbody>
                    </table>

                    <button type="submit" name="edit_mail_from_settings" class="btn btn-primary text-bold"><i class="fas fa-check mr-2"></i>Save From Addresses</button>
                </form>
            </div>

            <!-- ============================ TESTS ============================ -->
            <div class="tab-pane fade" id="tab-tests" role="tabpanel">

                <?php if (!$send_ready && !$imap_ready && !$oauth_has_required_fields) { ?>
                    <div class="alert alert-secondary mb-0">
                        <i class="fas fa-fw fa-info-circle mr-2"></i>Finish configuring Sending, Receiving, or OAuth (plus at least one From address) to unlock the tests.
                    </div>
                <?php } ?>

                <?php if ($send_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send a Test Email</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="input-group">
                            <select class="form-control select2" name="test_email" required>
                                <option value="">- Select a From address -</option>
                                <?php if ($config_mail_from_email) { ?><option value="1"><?php echo escapeHtml($config_mail_from_name); ?> (<?php echo escapeHtml($config_mail_from_email); ?>)</option><?php } ?>
                                <?php if ($config_invoice_from_email) { ?><option value="2"><?php echo escapeHtml($config_invoice_from_name); ?> (<?php echo escapeHtml($config_invoice_from_email); ?>)</option><?php } ?>
                                <?php if ($config_quote_from_email) { ?><option value="3"><?php echo escapeHtml($config_quote_from_name); ?> (<?php echo escapeHtml($config_quote_from_email); ?>)</option><?php } ?>
                                <?php if ($config_ticket_from_email) { ?><option value="4"><?php echo escapeHtml($config_ticket_from_name); ?> (<?php echo escapeHtml($config_ticket_from_email); ?>)</option><?php } ?>
                            </select>
                            <input type="email" class="form-control" name="email_to" placeholder="recipient@example.com">
                            <div class="input-group-append">
                                <button type="submit" name="test_email_smtp" class="btn btn-success"><i class="fas fa-fw fa-paper-plane mr-2"></i>Send</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php } ?>

                <?php if ($imap_ready) { ?>
                <div class="mb-4">
                    <h6 class="text-bold"><i class="fas fa-fw fa-plug mr-2"></i>Test IMAP Connection</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="test_email_imap" class="btn btn-success"><i class="fas fa-fw fa-inbox mr-2"></i>Test IMAP</button>
                    </form>
                </div>
                <?php } ?>

                <?php if ($oauth_has_required_fields) { ?>
                <div>
                    <h6 class="text-bold"><i class="fas fa-fw fa-sync-alt mr-2"></i>Test OAuth Token Refresh</h6>
                    <form action="post.php" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="oauth_provider" value="<?php echo htmlspecialchars($oauth_provider_for_test); ?>">
                        <p class="text-muted mb-2">Validates the refresh token and stores a new access token for <?php echo $oauth_provider_for_test === 'microsoft_oauth' ? 'Microsoft 365' : 'Google Workspace'; ?>.</p>
                        <button type="submit" name="test_oauth_token_refresh" class="btn btn-success"><i class="fas fa-fw fa-sync-alt mr-2"></i>Test OAuth Token Refresh</button>
                    </form>
                </div>
                <?php } ?>

            </div>

        </div>
    </div>
</div>

<script>
(function () {
    function setDisabled(c, d) { if (c) c.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !!d); }
    function show(el, v) { if (el) el.style.display = v ? '' : 'none'; }
    function toggle(el, v) { show(el, v); setDisabled(el, !v); }
    function val(s) { return (s && s.value) || ''; }
    function isStd(v) { return v === 'standard_imap' || v === 'standard_smtp'; }
    function isOauth(v) { return v === 'google_oauth' || v === 'microsoft_oauth'; }

    // ---- Numeric-only inputs (ports): strip anything that isn't a digit ----
    document.querySelectorAll('.numeric-only').forEach(function (el) {
        el.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
    });

    // ---- Self-contained tab controller (no dependency on the BS tab plugin) ----
    const navLinks = Array.from(document.querySelectorAll('#mailTabs .nav-link'));
    const panes = ['tab-smtp', 'tab-imap', 'tab-oauth', 'tab-from', 'tab-tests']
        .map(id => document.getElementById(id)).filter(Boolean);

    function activateTab(target) {
        navLinks.forEach(l => l.classList.toggle('active', l.getAttribute('data-target') === target));
        panes.forEach(p => {
            const on = ('#' + p.id) === target;
            p.classList.toggle('active', on);
            p.classList.toggle('show', on);
        });
    }
    navLinks.forEach(l => l.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(l.getAttribute('data-target'));
    }));

    // ---- Provider-driven field visibility ----
    const smtpSel = document.getElementById('config_smtp_provider');
    const imapSel = document.getElementById('config_imap_provider');

    const smtpConn = document.getElementById('smtp_conn_fields');
    const smtpUser = document.getElementById('smtp_user_group');
    const smtpPass = document.getElementById('smtp_pass_group');
    const smtpPtr  = document.getElementById('smtp_oauth_pointer');
    const smtpHint = document.getElementById('smtp_provider_hint');
    const smtpUserLb = document.getElementById('smtp_user_label');
    const smtpUserHt = document.getElementById('smtp_user_hint');
    const smtpUserIn = document.getElementById('config_smtp_username');

    const imapConn = document.getElementById('imap_conn_fields');
    const imapUser = document.getElementById('imap_user_group');
    const imapPass = document.getElementById('imap_pass_group');
    const imapPtr  = document.getElementById('imap_oauth_pointer');
    const imapHint = document.getElementById('imap_provider_hint');
    const imapUserLb = document.getElementById('imap_user_label');
    const imapUserHt = document.getElementById('imap_user_hint');

    const oauthTabItem = document.getElementById('tabitem-oauth');
    const tenantRow = document.getElementById('tenant_row');
    const msConnect = document.getElementById('ms_connect_group');
    const oauthHint = document.getElementById('oauth_hint');
    const oauthClientId = document.getElementById('config_mail_oauth_client_id');
    const oauthClientSecret = document.getElementById('config_mail_oauth_client_secret');

    function render() {
        const sv = val(smtpSel), iv = val(imapSel);

        toggle(smtpConn, isStd(sv));
        toggle(smtpUser, isStd(sv) || isOauth(sv));
        toggle(smtpPass, isStd(sv));
        show(smtpPtr, isOauth(sv));
        if (smtpUserLb) smtpUserLb.textContent = isOauth(sv) ? 'Authenticated User Email (licensed user)' : 'SMTP Username';
        if (smtpUserIn) smtpUserIn.placeholder = isOauth(sv) ? 'licensed.user@yourcompany.com' : 'usually your full email address';
        if (smtpUserHt) smtpUserHt.innerHTML = isOauth(sv)
            ? 'The licensed user that completed the OAuth flow &mdash; <strong>not</strong> the From / shared-mailbox address. Becomes the <code>user=</code> identity in the XOAUTH2 string.'
            : 'Leave blank if no authentication is required.';
        if (smtpHint) smtpHint.textContent = isOauth(sv) ? 'OAuth: set the authenticated user email here; app credentials live in the OAuth tab.'
            : isStd(sv) ? 'Standard: host, port, encryption, username & password.' : 'Disabled.';

        toggle(imapConn, isStd(iv));
        toggle(imapUser, isStd(iv) || isOauth(iv));
        toggle(imapPass, isStd(iv));
        show(imapPtr, isOauth(iv));
        if (imapUserLb) imapUserLb.textContent = isOauth(iv) ? 'Mailbox Email (monitored inbox)' : 'IMAP Username';
        if (imapUserHt) imapUserHt.textContent = isOauth(iv)
            ? 'The mailbox you monitor for tickets (the account the refresh token was issued for).'
            : 'The mailbox address to monitor for incoming tickets.';
        if (imapHint) imapHint.textContent = isOauth(iv) ? 'OAuth: set the mailbox here; app credentials live in the OAuth tab.'
            : isStd(iv) ? 'Standard: host, port, encryption, username & password.' : 'Disabled.';

        const anyOauth = isOauth(sv) || isOauth(iv);
        const anyMs = sv === 'microsoft_oauth' || iv === 'microsoft_oauth';

        show(oauthTabItem, anyOauth);
        toggle(tenantRow, anyMs);
        toggle(msConnect, anyMs);
        if (oauthClientId) oauthClientId.placeholder = anyMs
            ? 'Application (client) ID, e.g. 00000000-0000-0000-0000-000000000000'
            : 'xxxxxxxxxxxx.apps.googleusercontent.com';
        if (oauthClientSecret) oauthClientSecret.placeholder = anyMs ? 'Entra client secret value' : 'Google client secret';
        if (oauthHint) oauthHint.innerHTML = anyMs
            ? '<i class="fas fa-fw fa-info-circle mr-2"></i>Microsoft 365: Client ID / Secret / Tenant from Entra ID; refresh token via the Connect button below.'
            : anyOauth ? '<i class="fas fa-fw fa-info-circle mr-2"></i>Google Workspace: Client ID / Secret from Google Cloud; refresh token obtained via the consent flow.'
            : '<i class="fas fa-fw fa-info-circle mr-2"></i>These credentials are shared by any Sending or Receiving provider set to Google / Microsoft OAuth.';

        if (!anyOauth) {
            const oauthLink = document.querySelector('#mailTabs .nav-link[data-target="#tab-oauth"]');
            if (oauthLink && oauthLink.classList.contains('active')) activateTab('#tab-smtp');
        }
    }

    if (smtpSel) smtpSel.addEventListener('change', render);
    if (imapSel) imapSel.addEventListener('change', render);
    document.querySelectorAll('.goto-oauth').forEach(b => b.addEventListener('click', () => activateTab('#tab-oauth')));
    render();
})();
</script>

<?php require_once "../includes/footer.php"; ?>