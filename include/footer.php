<?php
/**
 * Footer Include File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
?>
<footer class="footer fixed-bottom">
  <div class="container py-2">
    <div class="row">
      <div class="col-sm fs-6 text-center">
        <small>&copy; <?php echo date("Y"); // Shows current year ?> CSC 235 Giovanni Gonzalez, Julian Rus, Jael Reyes </small>
      </div>
    </div>
  </div>
<style>
  /* Style the active nav link in green */
  .navbar-nav .nav-link.active {
    color: #b0c03d !important; /* Bootstrap success green */
    font-weight: bold;
  }
</style>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');

    navLinks.forEach(link => {
      // Remove any hardcoded 'active' classes first (like on the Home link)
      link.classList.remove('active');
      link.removeAttribute('aria-current');

      // Check if the link's path matches the current browser URL
      if (link.pathname === currentPath) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
  });
</script>


</footer>

<!-- Core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->


<!-- Required for bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

<!-- Custom app javascript -->
<script src="./js/main.js"></script>