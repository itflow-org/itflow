//Prevents resubmit on forms
if ( window.history.replaceState ) {
    window.history.replaceState( null, null, window.location.href );
}

// Call the dataTables jQuery plugin

$('#dataTable').dataTable( {
  order: [],
  language: {
    search: "_INPUT_",
    searchPlaceholder: "Search",
    sLengthMenu: "_MENU_",
    sInfo: "_START_-_END_ of _TOTAL_",
    paginate: {
      previous: '<i class="fa fa-angle-left"></i>',
      next: '<i class="fa fa-angle-right"></i>'
    }
  }
});

new EasyMDE({
  autoDownloadFontAwesome: false,
  element: document.getElementById('addClientNote')
});

new EasyMDE({
    autoDownloadFontAwesome: false,
    element: document.getElementById('editClientNote')
});