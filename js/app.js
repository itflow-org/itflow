//Prevents resubmit on forms
if(window.history.replaceState){
  window.history.replaceState(null, null, window.location.href);
}

// Slide alert up after 4 secs
$("#alert").fadeTo(5000, 500).slideUp(500, function(){
  $("#alert").slideUp(500);
});

// Initialize Select2 Elements
$('.select2').select2({
  theme: 'bootstrap4'
});

// Initialize TinyMCE
tinymce.init({
    selector: '.tinymce',
    browser_spellcheck: true,
    contextmenu: false,
    resize: true,
    min_height: 300,
    max_height: 600,
    promotion: false,
    branding: false,
    menubar: false,
    statusbar: false,
    toolbar: [
      { name: 'styles', items: [ 'styles' ] },
      { name: 'formatting', items: [ 'bold', 'italic', 'forecolor' ] },
      { name: 'lists', items: [ 'bullist', 'numlist' ] },
      { name: 'alignment', items: [ 'alignleft', 'aligncenter', 'alignright', 'alignjustify' ] },
      { name: 'indentation', items: [ 'outdent', 'indent' ] },
      { name: 'table', items: [ 'table' ] },
      { name: 'extra', items: [ 'code', 'fullscreen' ] }
    ],
    mobile: {
        menubar: false,
        plugins: 'autosave lists autolink',
        toolbar: 'bold italic styles'
    },
    plugins: 'link image lists table code codesample fullscreen autoresize',
});

// Initialize TinyMCE
tinymce.init({
    selector: '.tinymcePreview',
    resize: false,
    promotion: false,
    branding: false,
    menubar: false,
    toolbar: false,
    statusbar: false,
    readonly: false,
    plugins: 'autoresize',
});

// DateTime
$('.datetimepicker').datetimepicker({
});

// Data Input Mask
$('[data-mask]').inputmask();

// ClipboardJS

//Fix to allow Clipboard Copying within Bootstrap Modals
//For use in Bootstrap Modals or with any other library that changes the focus you'll want to set the focused element as the container value.
$.fn.modal.Constructor.prototype._enforceFocus = function() {};

// Tooltip

$('button').tooltip({
  trigger: 'click',
  placement: 'bottom'
});

function setTooltip(btn, message) {
  $(btn).tooltip('hide')
    .attr('data-original-title', message)
    .tooltip('show');
}

function hideTooltip(btn) {
  setTimeout(function() {
    $(btn).tooltip('hide');
  }, 1000);
}

// Clipboard

var clipboard = new ClipboardJS('.clipboardjs');

clipboard.on('success', function(e) {
  setTooltip(e.trigger, 'Copied!');
  hideTooltip(e.trigger);
});

clipboard.on('error', function(e) {
  setTooltip(e.trigger, 'Failed!');
  hideTooltip(e.trigger);
});

// Enable Popovers
$(function () {
  $('[data-toggle="popover"]').popover()
});

$(document).ready(function() {
  $("a.confirm-link").click(function(e) {
      e.preventDefault();

      // Save the link reference to use after confirmation
      var linkReference = this;

      // Show the confirmation modal
      $("#confirmationModal").modal('show');

      // When the submission is confirmed via the modal
      $("#confirmSubmitBtn").off('click').on('click', function() {
          window.location.href = $(linkReference).attr('href');
      });
  });
});
