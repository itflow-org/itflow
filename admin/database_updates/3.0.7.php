<?php

/*
 * ITFlow - Database update to version 3.0.7 (from 3.0.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // What kind each account is (functions/accounts.php - 1 checking, 2
    // savings, 3 credit card, 4 cash, 5 payment processor, 6 other), from
    // its name for the ones already here. A credit card's balance reads as
    // owed, and a transfer into one as paying it. Changeable afterwards.
    // Accounts typed in 2023 carry the old account_types ids (10 to 39, the
    // table dropped in 1.5.1): a liability (20s) is money owed, as a card is.
    mysqli_query($mysqli, "UPDATE accounts SET account_type = CASE
            WHEN LOWER(account_name) REGEXP 'saving|reserve' THEN 2
            WHEN LOWER(account_name) REGEXP 'card|visa|amex|american express|mastercard|discover|credit' THEN 3
            WHEN LOWER(account_name) REGEXP 'cash|petty' THEN 4
            WHEN LOWER(account_name) REGEXP 'stripe|paypal|square|merchant|settlement|processor|gateway' THEN 5
            WHEN account_type BETWEEN 20 AND 29 THEN 3
            ELSE 1 END
        WHERE account_type IS NULL OR account_type NOT BETWEEN 1 AND 6");
