<?php
/**
 * create account Page
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
          <h1>Create Account</h1>
          <form action="./php/create_account.php" method="post">
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="first_name" placeholder="Enter First Name" name="first_name" required>
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="last_name" placeholder="Enter Last Name" name="last_name" required>
            </div>
            <div class="input-group mb-3">
              <input type="email" class="form-control" id="email" placeholder="Enter Email Address" name="email" required>
            </div>
            <div class="input-group mb-3">
              <input type="tel" class="form-control" id="phone" placeholder="Enter Phone Number" name="phone" required>
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="username" aria-describedby="username" placeholder="Enter username" name="username" required>
            </div>
            <div class="input-group mb-3">
              <input type="password" class="form-control" id="exampleInputPassword1" placeholder="Password" name="password" required>
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="street" placeholder="Enter Street Address" name="street" >
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="street_additional" placeholder="Enter Street Additional" name="street_additional">
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="city" placeholder="Enter City" name="city" >
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="state" placeholder="Enter State" name="state" >
            </div>
            <div class="input-group mb-3">
              <input type="text" class="form-control" id="zip" placeholder="Enter Zip Code" name="zip" >
            </div>
            <?php
              /* Error Message */
              if (isset($error)) {
                // uses bootstrap alert style for error messages
                echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
              }
            ?>
            <button type="submit" class="btn btn-primary">Create</button>
          </form>
          <hr />
          <p class="text-center">
              <small>
                <a href="./index.php">Login</a> | <a href="./forgot_pass.php">Forgot Password</a>
              </small>
          </p>
        </div>
      </div>
    </main>
    <?php include_once "./include/footer.php"; ?>
  </body>
</html>