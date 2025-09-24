<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary d-print-none">

    <a class="pb-1 mt-1 brand-link" href="../<?php echo $config_start_page ?>">
        <p class="h5"><i class="nav-icon fas fa-arrow-left ml-3 mr-2"></i>
            <span class="brand-text ">Back | <strong>Custom</strong>
        </p>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">

        <!-- Sidebar Menu -->
        <nav>

            <ul class="nav nav-pills nav-sidebar flex-column mt-2" data-widget="treeview" data-accordion="false">

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







