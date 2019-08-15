//Prevents resubmit on forms
if(window.history.replaceState){
  window.history.replaceState(null, null, window.location.href);
}

//Slide alert up after 2 secs
$("#alert").fadeTo(2000, 500).slideUp(500, function(){
  $("#alert").slideUp(500);
});

new EasyMDE({
  autoDownloadFontAwesome: false,
  element: document.getElementById('addClientNote')
});

new EasyMDE({
  autoDownloadFontAwesome: false,
  autofocus: true,
  forceSync: true,
  element: document.getElementById('editClientNote')    
});