<?php
/**
 * Footer Include File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
/* Load BASE_URL (from php/path.php) so the main.js link below works from any folder */
include_once (dirname(__FILE__, 2) . '/php/path.php');

/* pe-none (Bootstrap "pointer-events: none") lets clicks pass through the footer
   to the buttons behind it, since fixed-bottom keeps it on top of the page. */
?>
<footer class="footer fixed-bottom pe-none">
  <div class="container py-2">
    <div class="row">
      <div class="col-sm fs-6 text-center">
        <small>&copy; <?php echo date("Y"); // Shows current year ?> CSC 235 Student Name </small>
      </div>
    </div>
  </div>
</footer>

<!-- Core JavaScript
================================================== -->
<!-- Placed at the end of the document so the pages load faster -->


<!-- Required for bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

<!-- Custom app javascript -->
<script src="<?= BASE_URL ?>/js/main.js"></script>