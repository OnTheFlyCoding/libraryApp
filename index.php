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
    <main role="main" class="container">
      <div class="row justify-content-sm-center">
        <div class="col-sm-4">
          <h1>Login</h1>
          <form action="./php/authenticate.php" method="post">
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="exampleInputEmail1" aria-describedby="username" placeholder="Enter username" name="username" required>
            </div>
            <div class="input-group mb-3">
              <input type="password" class="form-control" id="exampleInputPassword1" placeholder="Enter Password" name="password" required>
            </div>
            
            <?php include_once (ROOT_PATH . '/db/error_rprt.php'); ?>
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