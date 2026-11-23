<?php
/**
 * login Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
  /* Quick Paths */
  include_once (realpath(dirname(__FILE__).'/php/path.php'));


  /* Page Name */
  $page_name = "home";

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
    <main id="main" role="main" class="container">
      <div class="row justify-content-sm-center">
        <div class="col-sm-4">
          <h1>Login</h1>
          <form action="./php/authenticate.php" method="post">
            <?php /* label for="..." matches the input's id, so screen readers read the label
                     and clicking the label puts the cursor in the box */ ?>
            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" class="form-control" id="username" placeholder="Enter username" name="username" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" placeholder="Enter Password" name="password" required>
            </div>
            
            <?php include_once (ROOT_PATH . '/php/error_rprt.php'); // shows login error/warning messages ?>
            <button type="submit" class="btn btn-primary">Log In</button>
          </form>
          <hr />
          <p class="text-center">
              <small>
                <a href="./create_account.php">Create An Account</a> | <a href="./forgot_pass.php">Forgot Password</a>
              </small>
          </p>
        </div>
      </div>
    </main>
    <?php include_once "./include/footer.php"; ?>
  </body>
</html>