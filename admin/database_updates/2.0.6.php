<?php

/*
 * ITFlow - Database update to version 2.0.6 (from 2.0.5)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // CONVERT All tables TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci

    $tables = [
        'accounts', 'api_keys', 'app_logs', 'asset_credentials', 'asset_custom', 'asset_documents',
        'asset_files', 'asset_history', 'asset_interface_links', 'asset_interfaces', 'asset_notes', 'assets',
        'auth_logs', 'budget', 'calendar_event_attendees', 'calendar_events', 'calendars', 'categories',
        'certificate_history', 'certificates', 'client_notes', 'client_stripe', 'client_tags', 'clients',
        'companies', 'contact_assets', 'contact_credentials', 'contact_documents', 'contact_files', 'contact_notes',
        'contact_tags', 'contacts', 'credential_tags', 'credentials', 'custom_fields', 'custom_links',
        'custom_values', 'document_files', 'documents', 'domain_history', 'domains', 'email_queue', 'expenses',
        'files', 'folders', 'history', 'invoice_items', 'invoices', 'location_tags', 'locations', 'logs',
        'modules', 'networks', 'notifications', 'payments', 'products', 'project_template_ticket_templates',
        'project_templates', 'projects', 'quote_files', 'quotes', 'rack_units', 'racks', 'records',
        'recurring_expenses', 'recurring_invoices', 'recurring_payments', 'recurring_ticket_assets', 'recurring_tickets',
        'remember_tokens', 'revenues', 'service_assets', 'service_certificates', 'service_contacts', 'service_credentials',
        'service_documents', 'service_domains', 'service_vendors', 'services', 'settings', 'shared_items',
        'software', 'software_assets', 'software_contacts', 'software_credentials', 'software_documents', 'software_files',
        'tags', 'task_templates', 'tasks', 'taxes', 'ticket_assets', 'ticket_attachments', 'ticket_history', 'ticket_replies',
        'ticket_statuses', 'ticket_templates', 'ticket_views', 'ticket_watchers', 'tickets', 'transfers', 'trips',
        'user_client_permissions', 'user_role_permissions', 'user_roles', 'user_settings', 'users', 'vendor_credentials',
        'vendor_documents', 'vendor_files', 'vendors'
    ];

    foreach ($tables as $table) {
        $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
        mysqli_query($mysqli, $sql);
    }
