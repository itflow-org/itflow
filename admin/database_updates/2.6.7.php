<?php

/*
 * ITFlow - Database update to version 2.6.7 (from 2.6.6)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // The AI endpoints used to send a hardcoded temperature (0.5, or 0.3 for ticket
    // summaries). Newer OpenAI models accept nothing but their own default and reject
    // the request outright, which surfaced as "Failed to get a response from the AI API".
    //
    // Temperature is now per-model and optional: NULL means don't send the parameter
    // at all, which is the setting that works on every provider. Existing rows get
    // NULL so they stop sending it.

    mysqli_query($mysqli, "ALTER TABLE `ai_models` ADD COLUMN IF NOT EXISTS `ai_model_temperature` decimal(3,2) DEFAULT NULL AFTER `ai_model_use_case`");
