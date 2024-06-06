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

// Initialize TinyMCE AI
tinymce.init({
    selector: '.tinymceai',
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
        'styles bold italic forecolor bullist numlist alignleft aligncenter alignright alignjustify outdent indent table code fullscreen'
    ],
    mobile: {
        menubar: false,
        toolbar: 'bold italic styles'
    },
    plugins: 'link image lists table code codesample fullscreen autoresize',
    setup: function(editor) {
        var previousContent = ''; // Initialize previousContent outside the event listener
        document.getElementById('rewordButton').addEventListener('click', function() {
            var content = editor.getContent();
            previousContent = content; // Store the current content before rewording
            var rewordButton = document.getElementById('rewordButton');
            var undoButton = document.getElementById('undoButton');

            // Disable the Reword button and show loading state
            rewordButton.disabled = true;
            rewordButton.innerText = 'Processing...';

            fetch('post.php?ai_reword', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ text: content }),
            })
            .then(response => response.json())
            .then(data => {
                editor.setContent(data.rewordedText || 'Error: Could not reword the text.');
                rewordButton.disabled = false;
                rewordButton.innerText = 'Reword'; // Reset button text
                undoButton.style.display = 'inline'; // Show the Undo button
            })
            .catch(error => {
                console.error('Error:', error);
                rewordButton.disabled = false;
                rewordButton.innerText = 'Reword'; // Reset button text
            });

            // Setup the Undo button click event only once, not every time the reword button is clicked
            undoButton.onclick = function() {
                editor.setContent(previousContent);
                this.style.display = 'none'; // Hide the Undo button again
            };
        });
    }
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

// Data Tables
new DataTable('.dataTables');