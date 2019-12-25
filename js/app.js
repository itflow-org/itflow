//Prevents resubmit on forms
if(window.history.replaceState){
  window.history.replaceState(null, null, window.location.href);
}

//Slide alert up after 2 secs
$("#alert").fadeTo(2000, 500).slideUp(500, function(){
  $("#alert").slideUp(500);
});

//Initialize Select2 Elements
$('.select2').select2({
  theme: 'bootstrap4'
})

// Summernote
$('.summernote').summernote({
	height: 300
});