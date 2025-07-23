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

     <div class="form-group">
                  <label>Minimal</label>
                  <select class="form-control select2 select2-hidden-accessible" style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true">
                    <option selected="selected" data-select2-id="3">Alabama</option>
                    <option data-select2-id="35">Alaska</option>
                    <option data-select2-id="36">California</option>
                    <option data-select2-id="37">Delaware</option>
                    <option data-select2-id="38">Tennessee</option>
                    <option data-select2-id="39">Texas</option>
                    <option data-select2-id="40">Washington</option>
                  </select><span class="select2 select2-container select2-container--default select2-container--below" dir="ltr" data-select2-id="2" style="width: 100%;"><span class="selection"><span class="select2-selection select2-selection--single" role="combobox" aria-haspopup="true" aria-expanded="false" tabindex="0" aria-disabled="false" aria-labelledby="select2-nbex-container"><span class="select2-selection__rendered" id="select2-nbex-container" role="textbox" aria-readonly="true" title="Alabama">Alabama</span><span class="select2-selection__arrow" role="presentation"><b role="presentation"></b></span></span></span><span class="dropdown-wrapper" aria-hidden="true"></span></span>
                </div>

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
