<!-- Sidebar -->

<ul class="sidebar navbar-nav d-print-none">
  
  <li class="nav-item my-3">
    <h2 class="text-white text-center"><?php echo $client_name; ?></h2>
  </li>

  <li class="nav-item">
    <a class="nav-link" href="clients.php">
      <button class="btn btn-outline-light btn-block">
      <i class="fas fa-fw fa-arrow-left"></i>
      <span>Back</span>
    </button>
    </a>
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "overview") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=overview">
      <i class="fas fa-fw fa-chart-bar mx-2"></i>
      <span>Overview</span>
    </a>
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "contacts") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=contacts">
      <i class="fas fa-fw fa-users mx-2"></i>
      <span>Contacts</span>
    </a>
  </li>

  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "locations") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=locations">
      <i class="fas fa-fw fa-map-marker mx-2"></i>
      <span>Locations</span> 
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "assets") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=assets">
      <i class="fas fa-fw fa-tag mx-2"></i>
      <span>Assets</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "vendors") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=vendors">
      <i class="fas fa-fw fa-building mx-2"></i>
      <span>Vendors</span></a>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "logins") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=logins">
      <i class="fas fa-fw fa-key mx-2"></i>
      <span>Logins</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "networks") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=networks">
      <i class="fas fa-fw fa-network-wired mx-2"></i>
      <span>Networks</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "domains") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=domains">
      <i class="fas fa-fw fa-globe mx-2"></i>
      <span>Domains</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "applications") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=applications">
      <i class="fas fa-fw fa-box mx-2"></i>
      <span>Applications</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "invoices") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=invoices">
      <i class="fas fa-fw fa-file mx-2"></i>
      <span>Invoices</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "recurring") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=recurring">
      <i class="fas fa-fw fa-copy mx-2"></i>
      <span>Recurring</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "quotes") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=quotes">
      <i class="fas fa-fw fa-file mx-2"></i>
      <span>Quotes</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "files") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=files">
      <i class="fas fa-fw fa-paperclip mx-2"></i>
      <span>Files</span>
    </a>
  </li>
  
  <li class="nav-item">
    <a class="nav-link <?php if($_GET['tab'] == "notes") { echo "active"; } ?>" 
      href="?client_id=<?php echo $client_id; ?>&tab=notes">
      <i class="fas fa-fw fa-edit mx-2"></i>
      <span>Notes</span>
    </a>
  </li>
</ul>