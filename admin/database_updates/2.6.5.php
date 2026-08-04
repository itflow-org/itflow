<?php

/*
 * ITFlow - Database update to version 2.6.5 (from 2.6.4)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // credential_password was inherited as VARBINARY(200) when 2.0.0 renamed login_password.
    // What it actually stores is a 16-char IV followed by base64 AES-128-CBC ciphertext -
    // pure ASCII, the same shape as credential_username (varchar(500)) and
    // users.user_specific_encryption_ciphertext (varchar(200)). Nothing compares, indexes,
    // sorts or searches on the column, so binary semantics were never buying anything.
    //
    // The width was the real problem: base64 expands ~1.37x, so 200 bytes capped the
    // cleartext at 127 chars while the credential form offered 350. Anything longer
    // overflowed and errored the save. varchar(500) matches credential_username and makes
    // 350 the correct form limit for both fields.

    // Widen while still binary first. If the charset conversion below fails on an install
    // with unexpected bytes, the column is at least already wide enough and the app keeps
    // working - varbinary(500) holds the same values just fine.
    mysqli_query($mysqli, "ALTER TABLE `credentials` MODIFY `credential_password` varbinary(500) DEFAULT NULL");

    mysqli_query($mysqli, "ALTER TABLE `credentials` MODIFY `credential_password` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL");
