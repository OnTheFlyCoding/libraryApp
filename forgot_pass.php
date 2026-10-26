<?php
/**
 * Forgot Password  Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

  /* Start The Session */
  session_start(); 

?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once "./include/head.php"; ?>
  </head>
  <body>
    <?php include_once "./include/header.php"; ?>
    <main role="main" class="container">
      <div class="row justify-content-sm-center">
        <div class="col-sm-4">
          <h1>Account Recovery</h1>
          <form action="./db/recovery.php" method="post">
            <div class="input-group mb-3">
              <input type="email" class="form-control" id="email" aria-describedby="email" placeholder="Enter Email" name="email" required>
            </div>
            <?php
              /* Error Message */
              if (isset($error)) {
                // uses bootstrap alert style for error messages
                echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
              }
            ?>
            <button type="submit" class="btn btn-primary">Next</button>
          </form>
          <hr />
          <p class="text-center">
            <small>
              <a href="./create_account.php">Create An Account</a> | <a href="./">Login</a>
            </small>
          </p>
        </div>
      </div>
    </main>
    <?php include_once "./include/footer.php"; ?>
  </body>
</html>