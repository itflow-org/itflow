<?php

/*
 * Setup - Database: the database ITFlow keeps everything in. Connects to the
 * one named, makes sure it is empty, creates ITFlow's tables in it and only
 * then writes config.php (setup_database.php) - or, when config.php can't be
 * written, shows what to put in it. With config.php already there, says
 * whether it connects and offers to create the tables it is missing.
 */

defined('FROM_SETUP') || die("Direct file access is not allowed");

$error_html = $database_error !== '' ? '<div class="alert alert-danger d-flex gap-2" role="alert" data-database-error><i class="fas fa-times-circle mt-1"></i><div>' . $database_error . '</div></div>' : '';

?>
<div class="card itflow-setup-card">
    <div class="card-body p-4">
        <h1 class="h4 fw-semibold mb-1"><i class="fas fa-database text-body-secondary me-2"></i>Database</h1>
        <p class="text-body-secondary mb-4">Where ITFlow keeps everything - an empty MySQL or MariaDB database.</p>

        <?php if ($config_manual !== '') { ?>

            <!-- The tables are made, but config.php could not be written -->
            <div class="alert alert-warning" data-config-manual>
                <i class="fas fa-exclamation-triangle me-2"></i><strong>Connected, and ITFlow's tables are made - but config.php could not be written.</strong>
                Create it yourself in <code><?= escapeHtml(dirname(__DIR__, 2)) ?></code> with what is below, then carry on.
            </div>
            <div class="position-relative">
                <pre class="bg-dark text-white rounded p-3 small mb-2" id="setupConfigManual"><?= escapeHtml($config_manual) ?></pre>
                <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2 clipboardjs" data-clipboard-text="<?= escapeHtml($config_manual) ?>"><i class="far fa-copy me-1"></i>Copy</button>
            </div>
            <p class="small text-body-secondary">It holds the database password - make it readable by the web server only.</p>
            <a href="?database" class="btn btn-primary">I have created it<i class="fas fa-arrow-right ms-2"></i></a>

        <?php } elseif (file_exists('../config.php')) { ?>

            <?php if (!$mysqli_available) { ?>
                <div class="alert alert-danger" data-database-state="broken">
                    <i class="fas fa-times-circle me-2"></i><strong>config.php is there, but it can't connect to the database</strong><?= $config_error !== '' ? ': ' . escapeHtml($config_error) : '' ?>.
                    Correct the details in config.php, then check again.
                </div>
                <a href="?database" class="btn btn-outline-secondary"><i class="fas fa-redo me-2"></i>Check again</a>
            <?php } elseif ($database_ready) { ?>
                <div class="d-flex gap-3 align-items-start mb-4" data-database-state="ready">
                    <i class="fas fa-check-circle text-success fa-lg mt-1"></i>
                    <div>
                        <div class="fw-semibold">Connected to <?= escapeHtml((string) ($database ?? '')) ?> on <?= escapeHtml((string) ($dbhost ?? '')) ?></div>
                        <div class="small text-body-secondary">ITFlow's tables are there. To change the database, edit config.php.</div>
                    </div>
                </div>
                <a href="?<?= $resume_step === 'database' ? 'user' : $resume_step ?>" class="btn btn-primary" data-setup-next>Continue<i class="fas fa-arrow-right ms-2"></i></a>
            <?php } else { ?>
                <?= $error_html ?>
                <div class="d-flex gap-3 align-items-start mb-4" data-database-state="empty">
                    <i class="fas fa-info-circle text-info fa-lg mt-1"></i>
                    <div>
                        <div class="fw-semibold">config.php connects to <?= escapeHtml((string) ($database ?? '')) ?> on <?= escapeHtml((string) ($dbhost ?? '')) ?></div>
                        <div class="small text-body-secondary">ITFlow's tables aren't in it yet.</div>
                    </div>
                </div>
                <form method="post" autocomplete="off">
                    <button type="submit" name="create_tables" class="btn btn-primary">Create the tables<i class="fas fa-arrow-right ms-2"></i></button>
                </form>
            <?php } ?>

        <?php } else { ?>

            <?= $error_html ?>
            <form method="post" autocomplete="off" data-database-form>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="setupDatabaseName">Database name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                            <input type="text" class="form-control" id="setupDatabaseName" name="database" value="<?= escapeHtml($database_form['database']) ?>" placeholder="itflow" maxlength="64" required <?= $database_error === '' ? 'autofocus' : '' ?>>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupDatabaseHost">Host <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                            <input type="text" class="form-control" id="setupDatabaseHost" name="host" value="<?= escapeHtml($database_form['host']) ?>" placeholder="localhost" maxlength="255" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupDatabaseUser">User <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <input type="text" class="form-control" id="setupDatabaseUser" name="username" value="<?= escapeHtml($database_form['username']) ?>" placeholder="itflow" maxlength="80" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="setupDatabasePassword">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                            <input type="password" class="form-control" id="setupDatabasePassword" data-toggle="password" name="password" placeholder="The user's password" autocomplete="new-password" <?= $database_error !== '' ? 'autofocus' : '' ?>>
                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-3">Setup checks it can connect and that the database is empty, creates ITFlow's tables, and only then writes the details to config.php.</div>
                <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
                    <a href="?checks" class="btn btn-link px-0 text-nowrap text-decoration-none"><i class="fas fa-arrow-left me-2"></i>Back</a>
                    <button type="submit" name="add_database" class="btn btn-primary" data-setup-next>Connect and create the tables<i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </form>

        <?php } ?>
    </div>
</div>
