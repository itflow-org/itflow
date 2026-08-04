<?php

/*
 * ITFlow - Database update to version 2.4.4 (from 2.4.3)
 * Included by admin/database_updates.php - do not access directly
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    // Asset Status
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Ready to Deploy', category_description = 'Asset is configured and ready to be assigned', category_type = 'asset_status', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Deployed', category_description = 'Asset is actively in use and assigned to a client or location', category_type = 'asset_status', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Out for Repair', category_description = 'Asset has been sent out for servicing or repair', category_type = 'asset_status', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Lost', category_description = 'Asset location is unknown and cannot be accounted for', category_type = 'asset_status', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Stolen', category_description = 'Asset has been reported stolen', category_type = 'asset_status', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Retired', category_description = 'Asset has been decommissioned and is no longer in service', category_type = 'asset_status', category_order = 6"); // 6

    // Contact note types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Call', category_description = 'Phone call with a client or contact', category_icon = 'fa-phone-alt', category_type = 'contact_note_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Email', category_description = 'Email correspondence with a client or contact', category_icon = 'fa-envelope', category_type = 'contact_note_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Meeting', category_description = 'Scheduled meeting with a client or contact', category_icon = 'fa-handshake', category_type = 'contact_note_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'In Person', category_description = 'In person visit or on-site interaction', category_icon = 'fa-people-arrows', category_type = 'contact_note_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Note', category_description = 'General note or internal comment', category_icon = 'fa-sticky-note', category_type = 'contact_note_type', category_order = 5"); // 5

    // Rack Types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '2-Post Open Frame', category_description = 'Two-post open frame rack for patch panels and lightweight equipment', category_type = 'rack_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '4-Post Open Frame', category_description = 'Four-post open frame rack for servers and heavier equipment', category_type = 'rack_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = '4-Post Enclosed Cabinet', category_description = 'Four-post enclosed cabinet with doors and sides for secure equipment housing', category_type = 'rack_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Wall-Mount Open', category_description = 'Open frame rack mounted directly to a wall for small deployments', category_type = 'rack_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Wall-Mount Enclosed', category_description = 'Enclosed cabinet rack mounted to a wall with a locking door', category_type = 'rack_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Other', category_description = 'Rack type does not fit any standard category', category_type = 'rack_type', category_order = 6"); // 6

    // Software Types
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Software as a Service (SaaS)', category_description = 'Cloud-hosted software accessed via a web browser or API', category_type = 'software_type', category_order = 1"); // 1
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Productivity Suite', category_description = 'Bundled office and collaboration tools such as Microsoft 365 or Google Workspace', category_type = 'software_type', category_order = 2"); // 2
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Web Application', category_description = 'Application hosted on a web server and accessed through a browser', category_type = 'software_type', category_order = 3"); // 3
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Desktop Application', category_description = 'Application installed and run locally on a workstation or laptop', category_type = 'software_type', category_order = 4"); // 4
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Mobile Application', category_description = 'Application installed and run on a mobile device or tablet', category_type = 'software_type', category_order = 5"); // 5
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Security Software', category_description = 'Software providing antivirus, endpoint protection, or security monitoring', category_type = 'software_type', category_order = 6"); // 6
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'System Software', category_description = 'Low-level software managing hardware resources and system operations', category_type = 'software_type', category_order = 7"); // 7
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Operating System', category_description = 'Core software managing hardware and providing a platform for applications', category_type = 'software_type', category_order = 8"); // 8
    mysqli_query($mysqli, "INSERT INTO categories SET category_name = 'Other', category_description = 'Software type does not fit any standard category', category_type = 'software_type', category_order = 9"); // 9
