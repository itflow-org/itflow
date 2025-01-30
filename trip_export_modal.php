<?php 

// Sprachdatei laden
include_once 'languages/lang.php';

// Sprache setzen
$selectedLang = $_SESSION['language'] ?? 'en'; // Standard: Englisch
$langArray = loadLanguage($selectedLang);

?>

<div class="modal" id="exportTripsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-fw fa-download mr-2"></i><?php echo lang('export_trips_csv'); ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="post.php" method="post" autocomplete="off">
                <div class="modal-body bg-white">

                    <div class="form-group">
                        <label><?php echo lang('date_from'); ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date_from" max="2999-12-31" value="<?php echo nullable_htmlentities($dtf); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo lang('date_to'); ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                            </div>
                            <input type="date" class="form-control" name="date_to" max="2999-12-31" value="<?php echo nullable_htmlentities($dtt); ?>">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-white">
                    <button type="submit" name="export_trips_csv" class="btn btn-primary text-bold">
                        <i class="fas fa-fw fa-download mr-2"></i><?php echo lang('download_csv'); ?>
                    </button>
                    <button type="button" class="btn btn-light" data-dismiss="modal">
                        <i class="fas fa-times mr-2"></i><?php echo lang('cancel'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
