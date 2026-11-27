<?php

/*
 * ITFlow - Database update to version 4.2.1 (from 4.2.0)
 * Included by admin/database_updates.php - do not access directly
 *
 * A credential's 2FA key is kept encrypted, as its username and password are
 * (functions/credential.php, credentialOtpEncrypt()), which makes it longer:
 *   credentials.credential_otp_secret varchar(200) -> varchar(500), as the username and password are
 *
 * The keys already there are encrypted by the first agent to open ITFlow after the update, with
 * their vault (credentialOtpVaultStored()) - an update has no vault to do it with.
 *
 * Re-runnable: widening it again changes nothing.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "ALTER TABLE `credentials` MODIFY `credential_otp_secret` varchar(500) DEFAULT NULL");
