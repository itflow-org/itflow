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

<?php echo CURRENT_DATABASE_VERSION; ?>
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


<?php
echo date('Y-m-d');
?>

<script>toastr.success('Have Fun Wozz!!')</script>

<?php require_once("footer.php"); ?>
