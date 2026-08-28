<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['load_starter_content'])) {

    validateCSRFToken();

    require_once 'starter_content_model.php';

    $starter_content_packs = starterContentPacks();
    $requested_pack = $_POST['load_starter_content'];

    // Only registry keys are accepted - the value goes nowhere near a query, but
    // an unknown pack should say so rather than silently do nothing
    if ($requested_pack === 'all') {
        $selected_packs = array_keys($starter_content_packs);
    } elseif (isset($starter_content_packs[$requested_pack])) {
        $selected_packs = [$requested_pack];
    } else {
        flashAlert("Unknown starter content pack", 'error');
        redirect();
    }

    // Registry order is load order - categories before products, ticket
    // templates before the project templates that link to them
    $items_added = 0;
    $summary = [];
    foreach ($selected_packs as $pack) {
        $pack_added = starterContentLoad($mysqli, $pack);
        if ($pack_added) {
            $items_added = $items_added + $pack_added;
            $summary[] = "$pack_added " . $starter_content_packs[$pack]['label'];
        }
    }

    if ($items_added) {
        $summary = implode(', ', $summary);
        logAudit("Starter Content", "Create", "$session_name loaded starter content - $summary");
        flashAlert("Added <strong>$items_added</strong> items: $summary");
    } else {
        flashAlert("Nothing to add - everything in that selection is already here", 'info');
    }

    redirect();

}

if (isset($_POST['load_demo_data'])) {

    validateCSRFToken();

    require_once 'demo_data_model.php';

    $counts = demoDataLoad($mysqli);

    if (!$counts['clients']) {
        flashAlert("Nothing to add - every demo client is already here", 'info');
        redirect();
    }

    $summary = "{$counts['clients']} clients, {$counts['contacts']} contacts, {$counts['assets']} assets, {$counts['documentation']} documentation records, {$counts['tickets']} tickets, {$counts['projects']} projects, {$counts['billing']} billing records, {$counts['company']} company records";


    logAudit("Demo Data", "Create", "$session_name loaded demo data - $summary");

    if ($counts['skipped_credentials']) {
        flashAlert("Added demo data: $summary. Credentials were skipped - the credential vault did not open for this session", 'warning');
    } else {
        flashAlert("Added demo data: $summary");
    }

    redirect();

}

if (isset($_POST['remove_demo_data'])) {

    validateCSRFToken();

    require_once 'demo_data_model.php';

    $clients_removed = demoDataRemove($mysqli);

    if ($clients_removed) {
        logAudit("Demo Data", "Deleted", "$session_name removed demo data - $clients_removed demo clients and all associated records");
        flashAlert("Removed <strong>$clients_removed</strong> demo clients and everything associated with them", 'error');
    } else {
        flashAlert("No demo clients found - nothing to remove", 'info');
    }

    redirect();

}
