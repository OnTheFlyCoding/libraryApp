<?php

/**
 * Check Guest Role
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
// Check if user role is 2 (guest user)

if($user_role == 2){
  // Everything is working
  // See Console Output
  echo '<script>console.log("Everything is Working");</script>';
} else {
  // Access Denied
  $_SESSION['message'] = "Access Denied";
  // Redirect to logout
  header("location: " . SRC_PATH . "/logout.php");
}

?>