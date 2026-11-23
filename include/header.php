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

/* Prints one navbar link. If it points to the page we're on ($_SERVER['SCRIPT_NAME']),
   it gets "active" (highlighted) and aria-current="page" (screen readers say "current page"). */
function nav_link($url, $text) {
    $current = ($url === $_SERVER['SCRIPT_NAME']);
    echo '<li class="nav-item"><a class="nav-link' . ($current ? ' active" aria-current="page' : '') . '" href="' . $url . '">' . $text . '</a></li>';
}
?>
<?php /* Skip link: hidden until a keyboard user presses Tab, then it lets them jump past the navbar.
         href="#main" goes to the element with id="main" (the <main> tag on each page). */ ?>
<a class="visually-hidden-focusable" href="#main">Skip to main content</a>
<header class="mb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $brand_url; ?>">
    <i class="bi bi-book" aria-hidden="true"></i> Library Prototype</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarText">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
          /* Show Home link only when user is NOT logged in */
          if (!isset($_SESSION['user_name'])) {
            nav_link(BASE_URL . '/index.php', 'Home');
          }
        ?>

        <?php
          /* Admin Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 1) {
            $dir = BASE_URL . '/admin/'; // full path to the admin folder, so links work from any page
            nav_link($dir . 'index.php', 'Admin Dashboard');
            nav_link($dir . 'books.php', 'Manage Books');
            nav_link($dir . 'loans.php', 'View All Loans');
            nav_link($dir . 'users.php', 'Manage Users');
            nav_link($dir . 'authors.php', 'Authors');
            nav_link($dir . 'publishers.php', 'Publishers');
          }
        ?>

        <?php
          /* Front End User Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) {
            $dir = BASE_URL . '/user/'; // full path to the user folder, so links work from any page
            nav_link($dir . 'index.php', 'Dashboard');
            nav_link($dir . 'books.php', 'View Available Books');
            nav_link($dir . 'loans.php', 'Active Loans');
            nav_link($dir . 'reservations.php', 'Reservations');
            nav_link($dir . 'account.php', 'My Account');
          }
        ?>

        <?php
          /* Guest Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 3) {
            /* Guests share the user books page (book.php hides Reserve/Borrow for them) */
            nav_link(BASE_URL . '/guest/index.php', 'Dashboard');
            nav_link(BASE_URL . '/user/books.php', 'View Available Books');
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