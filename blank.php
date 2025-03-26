<?php require_once "includes/inc_all.php"; ?>

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
    <h1><?php echo $session_user_role; ?></h1>
    <?php validateAdminRole(); ?>

<?php

$start_date = date('Y') . "-10-10";

echo "<H1>$start_date</H1>";

echo "<H2>User Agent</H2>";
echo getUserAgent();


?>
    <br>

    <input type="tel" name="phone" id="phone">

    <dl>
        <dt>Requester</dt>
        <dd>Sam Adams</dd>

        <dt>Created</dt>
        <dd><time datetime="2024-04-11T17:52:30+00:00" title="2024-04-11 13:52" data-datetime="calendar">Today at 13:52</time></dd>

        <dt>Last activity</dt>
        <dd><time datetime="2024-04-11T18:08:55+00:00" title="2024-04-11 14:08" data-datetime="calendar">Today at 14:08</time></dd>
    </dl>

    <?php echo randomString(100); ?>
    <br>
    <textarea class="tinymceTest"></textarea>

    <textarea class="tinymce"></textarea>

    <textarea class="tinymceTicket"></textarea>
<?php
// show the current Date and Time
$date_time = date('Y-m-d H:i:s');
echo "Current Date and Time: <strong>$date_time</strong>";
?>

<script>toastr.success('Have Fun Wozz!!')</script>

<?php require_once "includes/footer.php";
