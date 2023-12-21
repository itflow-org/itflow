<?php
// Main menu items
$menuItems = [
    [
        "title" => "Overview",
        "link" => "client_overview.php?client_id=".$client_id,
        "icon" => "fas fa-tachometer-alt",
        "subItemsKey" => "",
        "badge" => $num_overdue_tickets
    ],
    [
        "title" => "Contacts",
        "link" => "client_contacts.php?client_id=".$client_id,
        "icon" => "fas fa-users",
        "subItemsKey" => "",
        "badge" => $num_contacts
    ],
    [
        "title" => "Locations",
        "link" => "client_locations.php?client_id=".$client_id,
        "icon" => "fas fa-map-marker-alt",
        "subItemsKey" => "",
        "badge" => $num_locations
    ],
    [
        "title" => "Support",
        "link" => "",
        "icon" => "fas fa-life-ring",
        "subItemsKey" => "support",
        "badge" => $num_active_tickets + $num_scheduled_tickets + $num_vendors + $num_events
    ],
    [
        "title" => "Documentation",
        "link" => "",
        "icon" => "fas fa-book",
        "subItemsKey" => "documentation",
        "badge" => $num_assets + $num_software + $num_logins + $num_networks + $num_certificates + $num_domains + $num_documents + $num_files
    ],
    [
        "title" => "Accounting",
        "link" => "",
        "icon" => "fas fa-calculator",
        "subItemsKey" => "accounting",
        "badge" => $num_invoices_open
    ],
    [
        "title" => "Other",
        "link" => "",
        "icon" => "fas fa-ellipsis-h",
        "subItemsKey" => "other",
        "badge" => $num_shared_links + $num_trips
    ]
];

// Sub-items, organized by key
$subItems = [
    "support" => [
        [
            "title" => "Tickets",
            "link" => "client_tickets.php?client_id=".$client_id,
            "icon" => "fas fa-ticket-alt",
            "badge" => $num_active_tickets
        ],
        [
            "title" => "Scheduled Tickets",
            "link" => "client_scheduled_tickets.php?client_id=".$client_id,
            "icon" => "fas fa-calendar-alt",
            "badge" => $num_scheduled_tickets
        ],
        [
            "title" => "Vendors",
            "link" => "client_vendors.php?client_id=".$client_id,
            "icon" => "fas fa-truck",
            "badge" => $num_vendors
        ],
        [
            "title" => "Calendar",
            "link" => "client_events.php?client_id=".$client_id,
            "icon" => "fas fa-calendar",
            "badge" => $num_events
        ],
    ],
    "documentation" => [
        [
            "title" => "Assets",
            "link" => "client_assets.php?client_id=".$client_id,
            "icon" => "fas fa-desktop",
            "badge" => $num_assets
        ],
        [
            "title" => "Licenses",
            "link" => "client_software.php?client_id=".$client_id,
            "icon" => "fas fa-key",
            "badge" => $num_software
        ],
        [
            "title" => "Logins",
            "link" => "client_logins.php?client_id=".$client_id,
            "icon" => "fas fa-user-lock",
            "badge" => $num_logins
        ],
        [
            "title" => "Networks",
            "link" => "client_networks.php?client_id=".$client_id,
            "icon" => "fas fa-network-wired",
            "badge" => $num_networks
        ],
        [
            "title" => "Certificates",
            "link" => "client_certificates.php?client_id=".$client_id,
            "icon" => "fas fa-certificate",
            "badge" => $num_certificates
        ],
        [
            "title" => "Domains",
            "link" => "client_domains.php?client_id=".$client_id,
            "icon" => "fas fa-globe",
            "badge" => $num_domains
        ],
        [
            "title" => "Documents",
            "link" => "client_documents.php?client_id=".$client_id,
            "icon" => "fas fa-file",
            "badge" => $num_documents
        ],
        [
            "title" => "Files",
            "link" => "client_files.php?client_id=".$client_id,
            "icon" => "fas fa-file-alt",
            "badge" => $num_files
        ],
    ],
    "accounting" => [
        [
            "title" => "Quotes",
            "link" => "client_quotes.php?client_id=".$client_id,
            "icon" => "fas fa-file-invoice-dollar",
            "badge" => $num_quotes
        ],
        [
            "title" => "Invoices",
            "link" => "client_invoices.php?client_id=".$client_id,
            "icon" => "fas fa-file-invoice",
            "badge" => $num_invoices
        ],
        [
            "title" => "Payments",
            "link" => "client_payments.php?client_id=".$client_id,
            "icon" => "fas fa-money-check-alt",
            "badge" => $num_payments
        ],
        [
            "title" => "Statement",
            "link" => "client_statement.php?client_id=".$client_id,
            "icon" => "fas fa-balance-scale",
            "badge" => $num_invoices_open
        ],
    ],
    "other" => [
        [
            "title" => "Shared Links",
            "link" => "client_shared_items.php?client_id=".$client_id,
            "icon" => "fas fa-link",
            "badge" => $num_shared_links
        ],
        [
            "title" => "Audit Log",
            "link" => "client_logs.php?client_id=".$client_id,
            "icon" => "fas fa-history",
            "badge" => $num_logs
        ],
        [
            "title" => "Trips",
            "link" => "client_trips.php?client_id=".$client_id,
            "icon" => "fas fa-route",
            "badge" => $num_trips
        ]
    ]
];

function isActive($link) {
    $currentUri = $_SERVER["REQUEST_URI"];
    $link = "/" . ltrim($link, '/'); // Ensure both URLs are formatted consistently
    return $currentUri == $link;
}

function isCurrentPageInSubmenu($subItemsKey, $subItems) {
    if (!array_key_exists($subItemsKey, $subItems)) {
        return false;
    }

    $currentUri = $_SERVER["REQUEST_URI"];
    $currentUri = "/" . ltrim($currentUri, '/'); // Ensure consistent formatting

    foreach ($subItems[$subItemsKey] as $subItem) {
        if ($currentUri == "/" . ltrim($subItem['link'], '/')) {
            return true;
        }
    }
    return false;
}
?>

<aside class="main-sidebar sidebar-dark-<?php echo nullable_htmlentities($config_theme); ?> d-print-none">

    <a class="brand-link pb-1 mt-1" href="clients.php">
        <p class="h5"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            Back | <strong><?php echo shortenClient($client_name); ?></strong></p>
    </a>
    <div class="sidebar">
        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <?php foreach ($menuItems as $item) {
                $isActive = isActive($item['link']);
                $isCurrentInSubmenu = isCurrentPageInSubmenu($item['subItemsKey'], $subItems);
                // Check if the item has a 'badge' key and the badge count is greater than 0
                $showBadge = isset($item['badge']) && $item['badge'] > 0;
            ?>
                <li class="nav-item
                    <?php echo $isCurrentInSubmenu ? 'menu-open' : ''; ?>
                    <?php echo $isActive ? 'active' : ''; ?>">
                    <a href="<?php echo $item['link']; ?>" class="nav-link
                        <?php echo $isActive ? 'active' : ''; ?>">
                        <i class="nav-icon <?php echo $item['icon']; ?>"></i>
                        <p>
                            <?php echo $item['title']; ?>
                            <?php if ($showBadge) { ?>
                            <span
                                class="right badge <?php echo ($item['badge'] > 0) ? 'badge-secondary' : ''; ?> text-light"><?php echo $item['badge']; ?></span>
                            <?php } ?>
                            <?php if ($item['subItemsKey']) { ?>
                            <i class="right fas fa-angle-left"></i>
                            <?php } ?>
                        </p>
                    </a>
                    <?php if ($item['subItemsKey'] && array_key_exists($item['subItemsKey'], $subItems)) { ?>
                    <ul class="nav nav-treeview">
                        <?php foreach ($subItems[$item['subItemsKey']] as $subItem) {
                            $isSubItemActive = isActive($subItem['link']);
                            $showSubItemBadge = isset($subItem['badge']) && $subItem['badge'] > 0;
                            ?>
                        <li class="nav-item nav-sub-item">
                            <a href="<?php echo $subItem['link']; ?>"
                                class="nav-link <?php echo $isSubItemActive ? 'active' : ''; ?>">
                                <i class="nav-icon <?php echo $subItem['icon']; ?>"></i>
                                <p>
                                    <?php echo $subItem['title']; ?>
                                    <?php if ($showSubItemBadge) { ?>
                                    <span
                                        class="right badge <?php echo ($subItem['badge'] > 0) ? 'badge-secondary' : ''; ?> text-light"><?php echo $subItem['badge']; ?></span>
                                    <?php } ?>
                                </p>
                            </a>
                        </li>
                        <?php
                        } ?>
                    </ul>
                    <?php } ?>
                </li>
                <?php } ?>
            </ul>
        </nav>
    </div>
</aside>
