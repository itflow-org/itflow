<?php

/*
 * ITFlow - Database update to version 3.3.8 (from 3.3.7)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column = function ($table, $column) use ($mysqli) {
        $result = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    };

    // AI providers - which kind each is (how it is called and its models listed),
    // and whether passwords and keys are taken out of what is sent to it. Room for
    // the longer keys and Azure's longer addresses.
    if (!$itflow_column('ai_providers', 'ai_provider_kind')) {
        mysqli_query($mysqli, "ALTER TABLE `ai_providers` ADD `ai_provider_kind` varchar(50) NOT NULL DEFAULT 'custom' AFTER `ai_provider_name`");
        // Those set up already, by where they point
        foreach ([
            'api.openai.com' => 'openai', 'openai.azure.com' => 'azure', 'api.anthropic.com' => 'anthropic', 'generativelanguage.googleapis.com' => 'gemini',
            'api.mistral.ai' => 'mistral', 'api.groq.com' => 'groq', 'openrouter.ai' => 'openrouter', ':11434' => 'ollama', ':1234' => 'lmstudio',
        ] as $itflow_host => $itflow_kind) {
            mysqli_query($mysqli, "UPDATE `ai_providers` SET `ai_provider_kind` = '$itflow_kind' WHERE `ai_provider_kind` = 'custom' AND `ai_provider_api_url` LIKE '%$itflow_host%'");
        }
    }
    if (!$itflow_column('ai_providers', 'ai_provider_redact')) {
        mysqli_query($mysqli, "ALTER TABLE `ai_providers` ADD `ai_provider_redact` tinyint(1) NOT NULL DEFAULT 1 AFTER `ai_provider_api_key`");
    }
    mysqli_query($mysqli, "ALTER TABLE `ai_providers` MODIFY `ai_provider_api_url` varchar(500) NOT NULL, MODIFY `ai_provider_api_key` varchar(500) DEFAULT NULL");

    // Each AI request - what it was for, who asked, the model, tokens and how long it took
    mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `ai_usage` (
        `ai_usage_id` int(11) NOT NULL AUTO_INCREMENT,
        `ai_usage_feature` varchar(50) NOT NULL,
        `ai_usage_use_case` varchar(50) DEFAULT NULL,
        `ai_usage_provider_id` int(11) NOT NULL DEFAULT 0,
        `ai_usage_provider_name` varchar(200) DEFAULT NULL,
        `ai_usage_model_id` int(11) NOT NULL DEFAULT 0,
        `ai_usage_model` varchar(200) DEFAULT NULL,
        `ai_usage_user_id` int(11) NOT NULL DEFAULT 0,
        `ai_usage_client_id` int(11) NOT NULL DEFAULT 0,
        `ai_usage_tokens_in` int(11) NOT NULL DEFAULT 0,
        `ai_usage_tokens_out` int(11) NOT NULL DEFAULT 0,
        `ai_usage_ms` int(11) NOT NULL DEFAULT 0,
        `ai_usage_redacted` int(11) NOT NULL DEFAULT 0,
        `ai_usage_ok` tinyint(1) NOT NULL DEFAULT 1,
        `ai_usage_error` varchar(255) DEFAULT NULL,
        `ai_usage_created_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`ai_usage_id`),
        KEY `ai_usage_created_at` (`ai_usage_created_at`),
        KEY `ai_usage_provider_id` (`ai_usage_provider_id`),
        KEY `ai_usage_model_id` (`ai_usage_model_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Using AI is a permission of its own - every role keeps it, as anyone could use it before
    $itflow_module = mysqli_fetch_row(mysqli_query($mysqli, "SELECT module_id FROM modules WHERE module_name = 'module_ai' LIMIT 1"));
    if (!$itflow_module) {
        mysqli_query($mysqli, "INSERT INTO modules SET module_name = 'module_ai', module_description = 'Using AI - rewording, summaries and drafts'");
        $itflow_module_id = intval(mysqli_insert_id($mysqli));
        mysqli_query($mysqli, "INSERT INTO user_role_permissions (user_role_id, module_id, user_role_permission_level)
            SELECT role_id, $itflow_module_id, 1 FROM user_roles WHERE role_is_admin = 0");
    }

    unset($itflow_column, $itflow_host, $itflow_kind, $itflow_module, $itflow_module_id);
