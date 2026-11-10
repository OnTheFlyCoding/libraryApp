<?php
/**
 * Header Include File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
if (!isset($_SESSION)) {
    // Only include session if not already started
    include_once (realpath(dirname(__FILE__).'/path.php'));
    // Include session file
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
            echo '<li class="nav-item"><a class="nav-link" href="index.php">Admin Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="books.php">Manage Books</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="loans.php">View All Loans</a></li>';
          }
        ?>

        <?php
          /* Front End User Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { 
            echo '<li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="books.php">View Available Books</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="loans.php">Active Loans</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="reservations.php">Reservations</a></li>';
          }
        ?>

        <?php
          /* Guest Navigation Links */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 3) { 
            echo '<li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="books.php">View Available Books</a></li>';
          }
        ?>
      </ul>
      <span class="navbar-text">
        <?php
            /* User Information & Logout */
            if (isset($_SESSION['user_first']) && isset($_SESSION['user_type'])) {
                echo 'Welcome ' . htmlspecialchars($_SESSION['user_first']) . ' (' . htmlspecialchars($_SESSION['user_type']) . ')';
                echo ' | <a href="../php/logout.php">Logout</a>';
            } else {
                echo 'Not Logged In';
            }
        ?>
      </span>
    </div>
  </div>
</nav>
</header>