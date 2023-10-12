<?php require_once("inc_all.php"); ?>

<!-- Breadcrumbs-->
<ol class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="index.html">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Blank Page</li>
</ol>

<!-- Page Content -->
<h1>Blank Page</h1>
<hr>
<p>This is a great starting point for new custom pages.</p>

<?php

$start_date = date('Y') . "-10-10";

echo "<H1>$start_date</H1>";


?>
<br>

<?php echo randomString(100); ?>
<br>
<form>
    <?php
    $timezones = DateTimeZone::listIdentifiers();
    echo '<select name="timezone">';
    foreach ($timezones as $timezone) {
        echo '<option value="' . $timezone . '">' . $timezone . '</option>';
    }
    echo '</select>';

    ?>
</form>


<script>
    toastr.success('Have Fun Wozz!!')
</script>

<?php require_once("footer.php"); ?>