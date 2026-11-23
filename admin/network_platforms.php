<?php

/*
 * Admin > Network Platforms - UniFi first (functions/rmm_unifi.php). Each platform brings in
 * the gateways, switches and access points of the sites linked to your clients, as assets.
 * It is the RMM Providers page (rmm_providers.php) for the network family's kinds, in its
 * own words - platforms and their sites.
 */

// Default Column Sortby Filter
$sort = "rmm_provider_name";
$order = "ASC";

$rmm_family = 'network';
require __DIR__ . '/rmm_providers.php';
