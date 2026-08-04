<?php

/*
 * ITFlow - Database update to version 2.2.2 (from 2.2.1)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    mysqli_query($mysqli, "CREATE TABLE `ai_providers` (
        `ai_provider_id` INT(11) NOT NULL AUTO_INCREMENT,
        `ai_provider_name` VARCHAR(200) NOT NULL,
        `ai_provider_api_url` VARCHAR(200) NOT NULL,
        `ai_provider_api_key` VARCHAR(200) DEFAULT NULL,
        `ai_provider_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ai_provider_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ai_provider_id`)
    )");

    mysqli_query($mysqli, "
        CREATE TABLE `ai_models` (
            `ai_model_id` INT(11) NOT NULL AUTO_INCREMENT,
            `ai_model_name` VARCHAR(200) NOT NULL,
            `ai_model_prompt` TEXT DEFAULT NULL,
            `ai_model_use_case` VARCHAR(200) DEFAULT NULL,
            `ai_model_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ai_model_updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            `ai_model_ai_provider_id` INT(11) NOT NULL,
            PRIMARY KEY (`ai_model_id`),
            FOREIGN KEY (`ai_model_ai_provider_id`)
                REFERENCES `ai_providers`(`ai_provider_id`)
                ON DELETE CASCADE
        )
    ");
