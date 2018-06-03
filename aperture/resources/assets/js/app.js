require('./bootstrap');

window.csrf_token = function() {
  return $('meta[name="csrf-token"]').attr('content');
}

window.reload_window = function() {
  window.location.reload(true);
}

document.addEventListener('DOMContentLoaded', function() {

  // Get all "navbar-burger" elements
  var $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

  // Check if there are any navbar burgers
  if ($navbarBurgers.length > 0) {

    // Add a click event on each of them
    $navbarBurgers.forEach(function ($el) {
      $el.addEventListener('click', function () {

        // Get the target from the "data-target" attribute
        var target = $el.dataset.target;
        var $target = document.getElementById(target);

        // Toggle the class on both the "navbar-burger" and the "navbar-menu"
        $el.classList.toggle('is-active');
        $target.classList.toggle('is-active');

      });
    });
  }

  function closeActiveModal() {
    $(".modal.is-active").removeClass("is-active");
  }

  $(document).keyup(function(e){
    if(e.keyCode == 27) {
      closeActiveModal();
    }
  });

  $(".modal-background, .modal button.delete").click(function(e){
    closeActiveModal();
    e.preventDefault();
  });

  $("input[type=url]").on("blur", function(){
    if(!$(this).val().match(/^https?:/)) {
      $(this).val("http://"+$(this).val());
    }
  });

});
