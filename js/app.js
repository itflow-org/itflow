//Prevents resubmit on forms
if(window.history.replaceState){
  window.history.replaceState(null, null, window.location.href);
}

// Slide alert up after 2 secs
$("#alert").fadeTo(2000, 500).slideUp(500, function(){
  $("#alert").slideUp(500);
});

// Initialize Select2 Elements
$('.select2').select2({
  theme: 'bootstrap4'
});

// Summernote
$('.summernote').summernote({
	height: 300
});

// DateTime
$('.datetimepicker').datetimepicker({
});

// Data Input Mask
$('[data-mask]').inputmask();

// Tooltip ClipboardJS

$('.clipboardjs').tooltip({
  trigger: 'click',
  placement: 'bottom'
});

function setTooltip(message) {
  $('.clipboardjs').tooltip('hide')
    .attr('data-original-title', message)
    .tooltip('show');
}

function hideTooltip() {
  setTimeout(function() {
    $('.clipboardjs').tooltip('hide');
  }, 1000);
}

// ClipboardJS

//Fix to allow Clipboard Copying within Bootstrap Modals
//For use in Bootstrap Modals or with any other library that changes the focus you'll want to set the focused element as the container value.
$.fn.modal.Constructor.prototype._enforceFocus = function() {};

var clipboard = new ClipboardJS('.clipboardjs');

clipboard.on('success', function(e) {
  setTooltip('Copied!');
  hideTooltip();
});

clipboard.on('error', function(e) {
  setTooltip('Failed!');
  hideTooltip();
});