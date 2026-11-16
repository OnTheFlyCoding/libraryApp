<?php
/**
 * Header Include File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
/* Load BASE_URL and ROOT_PATH (from php/path.php) so the links below work on every page */
include_once (dirname(__FILE__, 2) . '/php/path.php');

if (!isset($_SESSION)) {
    // Only include session if not already started
    include_once (ROOT_PATH . '/php/session.php');
}

/* Brand link: send each role to its own dashboard, or to the login page if not logged in */
$dashboards = [1 => '/admin/index.php', 2 => '/user/index.php', 3 => '/guest/index.php'];
$brand_url  = BASE_URL . ($dashboards[$_SESSION['user_role'] ?? 0] ?? '/index.php');
?>
<header class="mb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $brand_url; ?>">
    <i class="bi bi-book"></i> Library Prototype</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarText">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
          /* Show Home link only when user is NOT logged in */
          if (!isset($_SESSION['user_name'])) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link active" aria-current="page" href="' . BASE_URL . '/index.php">Home</a>';
            echo '</li>';
          }
        ?>
        
        <?php
          /* Admin Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 1) { 
            $dir = BASE_URL . '/admin/'; // full path to the admin folder, so links work from any page
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'index.php">Admin Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'books.php">Manage Books</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'loans.php">View All Loans</a></li>';
          }
        ?>

        <?php
          /* Front End User Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { 
            $dir = BASE_URL . '/user/'; // full path to the user folder, so links work from any page
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'index.php">Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'books.php">View Available Books</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'loans.php">Active Loans</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . $dir . 'reservations.php">Reservations</a></li>';
          }
        ?>

        <?php
          /* Guest Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 3) { 
            /* Guests share the user books page (book.php hides Reserve/Borrow for them) */
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/guest/index.php">Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/books.php">View Available Books</a></li>';
          }
        ?>
      </ul>
      <span class="navbar-text">
        <?php
            /* User Information & Logout */
            if (isset($_SESSION['user_first']) && isset($_SESSION['user_type'])) {
                echo 'Welcome ' . htmlspecialchars($_SESSION['user_first']) . ' (' . htmlspecialchars($_SESSION['user_type']) . ')';
                echo ' | <a href="' . BASE_URL . '/php/logout.php">Logout</a>'; // BASE_URL so it works from any folder
            } else {
                echo 'Not Logged In';
            }
        ?>
      </span>
    </div>
  </div>
</nav>
</header>