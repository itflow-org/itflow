<?php
// Main menu items
$menuItems = [
    [
        "title" => "Overview",
        "link" => "client_overview.php?client_id=".$client_id,
        "icon" => "fas fa-tachometer-alt",
        "subItemsKey" => ""
    ],
    [
        "title" => "Contacts",
        "link" => "client_contacts.php?client_id=".$client_id,
        "icon" => "fas fa-users",
        "subItemsKey" => ""
    ],
    [
        "title" => "Locations",
        "link" => "client_locations.php?client_id=".$client_id,
        "icon" => "fas fa-map-marker-alt",
        "subItemsKey" => ""
    ],
    [
        "title" => "Support",
        "link" => "",
        "icon" => "fas fa-life-ring",
        "subItemsKey" => "support"
    ],
    [
        "title" => "Documentation",
        "link" => "",
        "icon" => "fas fa-book",
        "subItemsKey" => "documentation"
    ],
    [
        "title" => "Accounting",
        "link" => "",
        "icon" => "fas fa-calculator",
        "subItemsKey" => "accounting"
    ],
    [
        "title" => "Other",
        "link" => "",
        "icon" => "fas fa-ellipsis-h",
        "subItemsKey" => "other"
    ]
];

// Sub-items, organized by key
$subItems = [
    "support" => [
        [
            "title" => "Tickets",
            "link" => "client_tickets.php?client_id=".$client_id,
            "icon" => "fas fa-ticket-alt"
        ],
        [
            "title" => "Scheduled Tickets",
            "link" => "client_scheduled_tickets.php?client_id=".$client_id,
            "icon" => "fas fa-calendar-alt"
        ],
        [
            "title" => "Vendors",
            "link" => "client_vendors.php?client_id=".$client_id,
            "icon" => "fas fa-truck"
        ],
        [
            "title" => "Calendar",
            "link" => "client_events.php?client_id=".$client_id,
            "icon" => "fas fa-calendar"
        ],
    ],
    "documentation" => [
        [
            "title" => "Assets",
            "link" => "client_assets.php?client_id=".$client_id,
            "icon" => "fas fa-desktop"
        ],
        [
            "title" => "Licenses",
            "link" => "client_software.php?client_id=".$client_id,
            "icon" => "fas fa-key"
        ],
        [
            "title" => "Logins",
            "link" => "client_logins.php?client_id=".$client_id,
            "icon" => "fas fa-user-lock"
        ],
        [
            "title" => "Networks",
            "link" => "client_networks.php?client_id=".$client_id,
            "icon" => "fas fa-network-wired"
        ],
        [
            "title" => "Certificates",
            "link" => "client_certificates.php?client_id=".$client_id,
            "icon" => "fas fa-certificate"
        ],
        [
            "title" => "Domains",
            "link" => "client_domains.php?client_id=".$client_id,
            "icon" => "fas fa-globe"
        ],
        [
            "title" => "Documents",
            "link" => "client_documents.php?client_id=".$client_id,
            "icon" => "fas fa-file"
        ],
        [
            "title" => "Files",
            "link" => "client_files.php?client_id=".$client_id,
            "icon" => "fas fa-file-alt"
        ],
    ],
    "accounting" => [
        [
            "title" => "Quotes",
            "link" => "client_quotes.php?client_id=".$client_id,
            "icon" => "fas fa-file-invoice-dollar"
        ],
        [
            "title" => "Invoices",
            "link" => "client_invoices.php?client_id=".$client_id,
            "icon" => "fas fa-file-invoice"
        ],
        [
            "title" => "Payments",
            "link" => "client_payments.php?client_id=".$client_id,
            "icon" => "fas fa-money-check-alt"
        ],
        [
            "title" => "Statement",
            "link" => "client_statement.php?client_id=".$client_id,
            "icon" => "fas fa-balance-scale"
        ],
    ],
    "other" => [
        [
            "title" => "Shared Links",
            "link" => "client_shared_items.php?client_id=".$client_id,
            "icon" => "fas fa-link"
        ],
        [
            "title" => "Audit Log",
            "link" => "client_logs.php?client_id=".$client_id,
            "icon" => "fas fa-history"
        ],
        [
            "title" => "Trips",
            "link" => "client_trips.php?client_id=".$client_id,
            "icon" => "fas fa-route"
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
                    $isCurrentInSubmenu = isCurrentPageInSubmenu($item['subItemsKey'], $subItems);
                    $isActive = isActive($item['link']);
                ?>
                    <li class="nav-item
                        <?php echo $isCurrentInSubmenu ? 'menu-open' : ''; ?>
                        <?php echo $isActive ? 'active' : ''; ?>"
                    >
                        <a href="<?php echo $item['link']; ?>" class="nav-link
                            <?php echo $isActive ? 'active' : ''; ?>"
                        >
                            <i class="nav-icon <?php echo $item['icon']; ?>"></i>
                            <p>
                                <?php echo $item['title']; ?>
                                <?php if ($item['subItemsKey']) { ?>
                                    <i class="right fas fa-angle-left"></i>
                                <?php } ?>
                            </p>
                        </a>
                        <?php if ($item['subItemsKey'] && array_key_exists($item['subItemsKey'], $subItems)) { ?>
                            <ul class="nav nav-treeview">
                            <?php foreach ($subItems[$item['subItemsKey']] as $subItem) {
                                $isSubItemActive = isActive($subItem['link']);
                            ?>
                                <li class="nav-item nav-sub-item">
                                    <a href="<?php echo $subItem['link']; ?>" class="nav-link
                                        <?php echo $isSubItemActive ? 'active' : ''; ?>"
                                    >
                                        <i class="nav-icon <?php echo $subItem['icon']; ?>"></i>
                                        <p><?php echo $subItem['title']; ?></p>
                                    </a>
                                </li>
                            <?php } ?>
                            </ul>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </nav>
    </div>
</aside>
