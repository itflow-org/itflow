<!-- Main Sidebar Container -->
<aside class="app-sidebar shadow d-print-none" data-bs-theme="dark">

    <a class="pb-1 mt-1 brand-link" href="../<?= $config_start_page ?>">
        <p class="h5"><i class="nav-icon fas fa-arrow-left ms-3 me-2"></i>
            <span class="brand-text ">Back | <strong>Custom</strong>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills sidebar-menu flex-column mt-2" data-lte-toggle="treeview" data-accordion="false">

                <li class="nav-header">CUSTOM HEADER</li>
         
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php if (basename($_SERVER["PHP_SELF"]) == "index.php") { echo "active"; } ?>">
                        <i class="far fa-circle nav-icon"></i>
                        <p>custom</p>
                    </a>
                </li>
        
            </ul>

        </nav>
        <!-- /.sidebar-menu -->

        <div class="sidebar-custom mb-3">

        </div>

    </div>
    <!-- /.sidebar -->
</aside>







