<?php
/**
 * My Account Page (Front End Users)
 * Edit contact info (including email), change username, change password.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection (same as books.php) */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

$page_name = "account";
$user_id   = $_SESSION['user_id'];
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" so we know which form was sent. */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   1. EDIT ACCOUNT INFO (name, email, phone, address)
   This info lives in the Contacts table. Users only store a contact_id,
   so we join Users to update the contact that belongs to THIS user.
   ------------------------------------------------------------------ */
if ($action === 'info') {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $street_1 = trim($_POST['street_1'] ?? '');
    $street_2 = trim($_POST['street_2'] ?? '');
    $city  = trim($_POST['city'] ?? '');
    $state = trim($_POST['state_code'] ?? '');
    // post_code is a number column, so an empty box is saved as NULL instead of ''
    $zip   = ($_POST['post_code'] ?? '') === '' ? null : (int) $_POST['post_code'];

    if ($first === '' || $last === '' || $email === '' || $phone === '') {
        $error = 'First name, last name, email and phone are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $update = $db_connection->prepare("
            UPDATE Contacts c INNER JOIN Users u ON c.contact_id = u.contact_id
            SET c.first_name = ?, c.last_name = ?, c.email = ?, c.phone = ?,
                c.street_1 = ?, c.street_2 = ?, c.city = ?, c.state_code = ?, c.post_code = ?,
                c.updated = NOW()
            WHERE u.user_id = ?");
        $update->bind_param("ssssssssii", $first, $last, $email, $phone,
                            $street_1, $street_2, $city, $state, $zip, $user_id);
        $update->execute();
        $update->close();

        $_SESSION['user_first'] = $first; // so the navbar "Welcome ..." shows the new name right away
        $message = 'Account info updated.';
    }
}

/* ------------------------------------------------------------------
   2. CHANGE USERNAME
   Usernames must be unique (it's the primary key of Credentials),
   so we check nobody else has it first.
   ------------------------------------------------------------------ */
if ($action === 'username') {
    $new_username = trim($_POST['new_username'] ?? '');

    // Is the username already taken? (fetch() is true if a row was found)
    $check = $db_connection->prepare("SELECT user_id FROM Credentials WHERE username = ?");
    $check->bind_param("s", $new_username);
    $check->execute();
    $taken = $check->fetch();
    $check->close();

    if ($new_username === '') {
        $error = 'Username cannot be empty.';
    } elseif ($taken) {
        $error = 'That username is already taken.';
    } else {
        $update = $db_connection->prepare("UPDATE Credentials SET username = ? WHERE user_id = ?");
        $update->bind_param("si", $new_username, $user_id);
        $update->execute();
        $update->close();

        // session.php finds the user by 'login_user' on every page, so it must match the new name
        // or the user would be logged out on the next click.
        $_SESSION['login_user'] = $new_username;
        $_SESSION['user_name']  = $new_username;
        $message = 'Username changed.';
    }
}

/* ------------------------------------------------------------------
   3. CHANGE PASSWORD
   Passwords are stored "salted" with crypt(password, SALT), the same way
   create_account.php saves them and authenticate.php checks them at login.
   The current password is required so someone at an unlocked computer can't change it.
   ------------------------------------------------------------------ */
if ($action === 'password') {
    $current = crypt($_POST['current_password'] ?? '', SALT);
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Does the current password match what's saved for this user?
    $check = $db_connection->prepare("SELECT user_id FROM Credentials WHERE user_id = ? AND password_salted = ?");
    $check->bind_param("is", $user_id, $current);
    $check->execute();
    $correct = $check->fetch();
    $check->close();

    if (!$correct) {
        $error = 'Current password is wrong.';
    } elseif ($new === '') {
        $error = 'New password cannot be empty.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $salted = crypt($new, SALT);
        $update = $db_connection->prepare("UPDATE Credentials SET password_salted = ? WHERE user_id = ?");
        $update->bind_param("si", $salted, $user_id);
        $update->execute();
        $update->close();
        $message = 'Password changed.';
    }
}

/* ------------------------------------------------------------------
   4. GET THE USER'S CURRENT INFO to fill in the forms
   (done AFTER the updates above, so the forms show the new values)
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT c.first_name, c.last_name, c.email, c.phone, c.street_1, c.street_2,
           c.city, c.state_code, c.post_code, cr.username
    FROM Users u
    INNER JOIN Contacts c    ON u.contact_id = c.contact_id
    INNER JOIN Credentials cr ON u.user_id   = cr.user_id
    WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$db_connection->close();

/* Short helper: safely print a value from $user into an input box (empty if NULL) */
function val($value) {
    return htmlspecialchars($value ?? '');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>My Account - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main class="container pb-5">
      <h1 class="h2">My Account</h1>
      <p class="lead">Update your info, username or password.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <?php /* Form 1: account info. The hidden "action" tells section 1 (top of file) to handle it. */ ?>
      <div class="card mb-4">
        <div class="card-header"><h2 class="h4 mb-0">Account Info</h2></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="info">
            <div class="col-md-6">
              <label for="first_name" class="form-label">First Name</label>
              <input type="text" id="first_name" name="first_name" class="form-control" value="<?= val($user['first_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="last_name" class="form-label">Last Name</label>
              <input type="text" id="last_name" name="last_name" class="form-control" value="<?= val($user['last_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="<?= val($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" id="phone" name="phone" class="form-control" value="<?= val($user['phone']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="street_1" class="form-label">Street</label>
              <input type="text" id="street_1" name="street_1" class="form-control" value="<?= val($user['street_1']) ?>">
            </div>
            <div class="col-md-6">
              <label for="street_2" class="form-label">Apt / Suite</label>
              <input type="text" id="street_2" name="street_2" class="form-control" value="<?= val($user['street_2']) ?>">
            </div>
            <div class="col-md-6">
              <label for="city" class="form-label">City</label>
              <input type="text" id="city" name="city" class="form-control" value="<?= val($user['city']) ?>">
            </div>
            <div class="col-md-3">
              <label for="state_code" class="form-label">State</label>
              <input type="text" id="state_code" name="state_code" class="form-control" maxlength="2" value="<?= val($user['state_code']) ?>">
            </div>
            <div class="col-md-3">
              <label for="post_code" class="form-label">Zip</label>
              <input type="text" id="post_code" name="post_code" class="form-control" maxlength="5" pattern="[0-9]*" value="<?= val($user['post_code']) ?>">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Save Info</button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-4">
        <?php /* Form 2: change username (handled by section 2) */ ?>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><h2 class="h4 mb-0">Change Username</h2></div>
            <div class="card-body">
              <p>Current username: <strong><?= val($user['username']) ?></strong></p>
              <form method="post">
                <input type="hidden" name="action" value="username">
                <label for="new_username" class="form-label">New Username</label>
                <input type="text" id="new_username" name="new_username" class="form-control mb-3" required>
                <button type="submit" class="btn btn-primary">Change Username</button>
              </form>
            </div>
          </div>
        </div>

        <?php /* Form 3: change password (handled by section 3). type="password" hides what is typed. */ ?>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header"><h2 class="h4 mb-0">Change Password</h2></div>
            <div class="card-body">
              <form method="post">
                <input type="hidden" name="action" value="password">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control mb-3" required>
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control mb-3" required>
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control mb-3" required>
                <button type="submit" class="btn btn-primary">Change Password</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
