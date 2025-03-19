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
  theme: 'bootstrap4',
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
      { name: 'link', items: [ 'link'] },
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
    convert_urls: false,
    plugins: 'link image lists table code codesample fullscreen autoresize',
    license_key: 'gpl'
});

// Initialize TinyMCE
tinymce.init({
    selector: '.tinymceAI',
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
      { name: 'link', items: [ 'link'] },
      { name: 'lists', items: [ 'bullist', 'numlist' ] },
      { name: 'alignment', items: [ 'alignleft', 'aligncenter', 'alignright', 'alignjustify' ] },
      { name: 'indentation', items: [ 'outdent', 'indent' ] },
      { name: 'table', items: [ 'table' ] },
      { name: 'extra', items: [ 'code', 'fullscreen' ] },
      { name: 'ai', items: [ 'reword', 'undo', 'redo' ] }
    ],
    mobile: {
        menubar: false,
        plugins: 'autosave lists autolink',
        toolbar: 'bold italic styles'
    },
    convert_urls: false,
    plugins: 'link image lists table code codesample fullscreen autoresize',
    license_key: 'gpl',
    setup: function(editor) {
        var rewordButtonApi;

        editor.ui.registry.addButton('reword', {
            icon: 'ai',
            tooltip: 'Reword Text',
            onAction: function() {
                var content = editor.getContent();

                // Disable the Reword button
                rewordButtonApi.setEnabled(false);

                // Show the progress indicator
                editor.setProgressState(true);

                fetch('post.php?ai_reword', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ text: content }),
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    editor.undoManager.transact(function() {
                        editor.setContent(data.rewordedText || 'Error: Could not reword the text.');
                    });

                    // Hide the progress indicator
                    editor.setProgressState(false);

                    // Re-enable the Reword button
                    rewordButtonApi.setEnabled(true);

                    // Optional: Show a success notification
                    editor.notificationManager.open({
                        text: 'Text reworded successfully!',
                        type: 'success',
                        timeout: 3000
                    });
                })
                .catch(error => {
                    console.error('Error:', error);

                    // Hide the progress indicator
                    editor.setProgressState(false);

                    // Re-enable the Reword button
                    rewordButtonApi.setEnabled(true);

                    // Show an error notification
                    editor.notificationManager.open({
                        text: 'An error occurred while rewording the text.',
                        type: 'error',
                        timeout: 5000
                    });
                });
            },
            onSetup: function(buttonApi) {
                rewordButtonApi = buttonApi;
                return function() {
                    // Cleanup when the editor is destroyed (if necessary)
                };
            }
        });
    }
});

tinymce.init({
  selector: '.tinymceTicket', // Your selector
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
    { name: 'formatting', items: [ 'bold', 'italic', 'forecolor'] },
    { name: 'link', items: [ 'link'] },
    { name: 'lists', items: [ 'bullist', 'numlist' ] },
    { name: 'indentation', items: [ 'outdent', 'indent' ] },
    { name: 'custom', items: ['myRedactButton'] } // Add custom button to toolbar
  ],
  mobile: {
    menubar: false,
    plugins: 'autosave lists autolink',
    toolbar: 'bold italic styles'
  },
  convert_urls: false,
  plugins: 'link image lists table code fullscreen autoresize',
  license_key: 'gpl',
  setup: function(editor) {
    // Add custom toolbar button with Font Awesome icon
    editor.ui.registry.addButton('myRedactButton', {
      icon: 'permanent-pen',
      tooltip: 'Redact', // Tooltip text for the button
      onAction: function() {
        var selectedText = editor.selection.getContent({ format: 'text' });

        if (selectedText) {
          // Replace the selected text with [REDACTED] in bold red
          var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
          
          // Replace selected content with the new content
          editor.selection.setContent(newContent);
        } else {
          alert('Please select a word to redact');
        }
      }
    });
  }
});

// Initialize TinyMCE AI
tinymce.init({
  selector: '.tinymceTicketAI',
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
    { name: 'styles', items: ['styles'] },
    { name: 'formatting', items: ['bold', 'italic', 'forecolor'] },
    { name: 'link', items: ['link'] },
    { name: 'lists', items: ['bullist', 'numlist'] },
    { name: 'indentation', items: ['outdent', 'indent'] },
    { name: 'ai', items: ['reword', 'undo', 'redo'] },
    { name: 'custom', items: ['myRedactButton'] } // Add custom redact button to toolbar
  ],
  mobile: {
    menubar: false,
    toolbar: 'bold italic styles'
  },
  convert_urls: false,
  plugins: 'link image lists table code codesample fullscreen autoresize',
  license_key: 'gpl',
  setup: function(editor) {
    var rewordButtonApi;

    // Define the Reword button (AI-related button)
    editor.ui.registry.addButton('reword', {
      icon: 'ai', // Example icon for AI rewording
      tooltip: 'Reword Text',
      onAction: function() {
        var content = editor.getContent();

        // Disable the Reword button
        rewordButtonApi.setEnabled(false);

        // Show the progress indicator
        editor.setProgressState(true);

        fetch('post.php?ai_reword', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ text: content }),
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          editor.undoManager.transact(function() {
            editor.setContent(data.rewordedText || 'Error: Could not reword the text.');
          });

          // Hide the progress indicator
          editor.setProgressState(false);

          // Re-enable the Reword button
          rewordButtonApi.setEnabled(true);

          // Optional: Show a success notification
          editor.notificationManager.open({
            text: 'Text reworded successfully!',
            type: 'success',
            timeout: 3000
          });
        })
        .catch(error => {
          console.error('Error:', error);

          // Hide the progress indicator
          editor.setProgressState(false);

          // Re-enable the Reword button
          rewordButtonApi.setEnabled(true);

          // Show an error notification
          editor.notificationManager.open({
            text: 'An error occurred while rewording the text.',
            type: 'error',
            timeout: 5000
          });
        });
      },
      onSetup: function(buttonApi) {
        rewordButtonApi = buttonApi;
        return function() {
          // Cleanup when the editor is destroyed (if necessary)
        };
      }
    });

    // Add the Redact button
    editor.ui.registry.addButton('myRedactButton', {
      icon: 'permanent-pen',
      tooltip: 'Redact Text', // Tooltip text for the button
      onAction: function() {
        var selectedText = editor.selection.getContent({ format: 'text' });

        if (selectedText) {
          // Replace the selected text with [REDACTED] in bold red
          var newContent = '<span style="font-weight: bold; color: red;">[REDACTED]</span>';
          
          // Replace selected content with the new content
          editor.selection.setContent(newContent);
        } else {
          alert('Please select a word to redact');
        }
      }
    });
  }
});

// Initialize TinyMCE editor with only a redact button
tinymce.init({
    selector: '.tinymceTicketRedact',
    browser_spellcheck: false,
    contextmenu: false,
    resize: true,
    min_height: 300,
    max_height: 500,
    promotion: false,
    branding: false,
    menubar: false,
    statusbar: false,
    license_key: 'gpl',
    readonly: true,
    toolbar: '',
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