<?php
/**
 * Manage Users (Administrators)
 * Search users, change a user's password, and delete a user.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminUsers";
$my_id     = $_SESSION['user_id']; // the admin who is logged in (they can't delete themselves)
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" ("password" or "delete") so we know which one was sent. */
$action  = $_POST['action'] ?? '';
$user_id = (int) ($_POST['user_id'] ?? 0); // which user the form is about

/* ------------------------------------------------------------------
   1. CHANGE A USER'S PASSWORD
   The admin is resetting it, so the old password isn't needed.
   Saved with crypt(password, SALT), the same way create_account.php saves it
   and authenticate.php checks it, so the user can log in with the new one.
   ------------------------------------------------------------------ */
if ($action === 'password') {
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '') {
        $error = 'New password cannot be empty.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
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
   2. DELETE A USER
   Not allowed for yourself (you'd be locked out), or while the user
   still has borrowed books (they need to be returned first).
   Rows that point TO the user must go first, or the foreign keys stop us:
     Loans -> Reservations -> Credentials -> the User -> their Contacts row
   (Contacts is last because the Users row points to it.)
   A transaction makes it all-or-nothing, like deleting a book.
   ------------------------------------------------------------------ */
if ($action === 'delete') {
    // How many books does this user still have (not returned)?
    $check = $db_connection->prepare("SELECT COUNT(*) FROM Loans WHERE user_id = ? AND return_date IS NULL");
    $check->bind_param("i", $user_id);
    $check->execute();
    $check->bind_result($on_loan);
    $check->fetch();
    $check->close();

    if ($user_id === $my_id) {
        $error = 'You can\'t delete your own account.';
    } elseif ($on_loan > 0) {
        $error = 'This user still has borrowed books. They must be returned first.';
    } else {
        try {
            $db_connection->begin_transaction();

            // Find the user's contact_id now, because the Users row is deleted before Contacts
            $find = $db_connection->prepare("SELECT contact_id FROM Users WHERE user_id = ?");
            $find->bind_param("i", $user_id);
            $find->execute();
            $find->bind_result($contact_id);
            $found = $find->fetch(); // false/null if there's no such user
            $find->close();

            if ($found) {
                // Each query deletes this user's rows from one table, in the order explained above
                $deletes = [
                    "DELETE FROM Loans        WHERE user_id = ?",
                    "DELETE FROM Reservations WHERE user_id = ?",
                    "DELETE FROM Credentials  WHERE user_id = ?",
                    "DELETE FROM Users        WHERE user_id = ?",
                ];
                foreach ($deletes as $sql) {
                    $delete = $db_connection->prepare($sql);
                    $delete->bind_param("i", $user_id);
                    $delete->execute();
                    $delete->close();
                }
                $delete = $db_connection->prepare("DELETE FROM Contacts WHERE contact_id = ?");
                $delete->bind_param("i", $contact_id);
                $delete->execute();
                $delete->close();
                $message = 'User deleted.';
            }
            $db_connection->commit();
        } catch (mysqli_sql_exception $e) {
            $db_connection->rollback(); // something failed, so undo everything from this delete
            $error = 'Could not delete the user.';
        }
    }
}

/* SEARCH: $_GET reads the search from the URL, e.g. users.php?search=smith */
$search = trim($_GET['search'] ?? '');
$like   = "%" . $search . "%"; // % = wildcard, so "smi" matches "Smith"

/* ------------------------------------------------------------------
   3. GET USERS (matching the search) with their name, email, role and loan count
   Users -> Contacts (name, email), Credentials (username), Roles (admin/user/guest).
   The search matches the full name, the username or the email.
   on_loan = small query counting the books each user still has.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT u.user_id, c.first_name, c.last_name, c.email, cr.username, r.role_type,
           (SELECT COUNT(*) FROM Loans l WHERE l.user_id = u.user_id AND l.return_date IS NULL) AS on_loan
    FROM Users u
    INNER JOIN Contacts    c  ON u.contact_id = c.contact_id
    INNER JOIN Credentials cr ON u.user_id    = cr.user_id
    INNER JOIN Roles       r  ON u.role_id    = r.role_id
    WHERE CONCAT(c.first_name, ' ', c.last_name) LIKE ? OR cr.username LIKE ? OR c.email LIKE ?
    ORDER BY c.last_name, c.first_name");
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   4. WHICH USER'S PASSWORD IS BEING CHANGED?
   Clicking "Change Password" in the table adds ?edit=ID to the URL,
   which shows the password form for that user above the table.
   It closes again once the password was changed.
   ------------------------------------------------------------------ */
$edit_id   = (int) ($_GET['edit'] ?? 0);
$edit_user = null;
foreach ($users as $u) {
    if ($u['user_id'] === $edit_id) {
        $edit_user = $u;
    }
}
if ($action === 'password' && $error === '') {
    $edit_user = null; // done, so hide the form
}

/* ------------------------------------------------------------------
   5. PAGINATION: 10 users per page (same code as the books pages).
   ------------------------------------------------------------------ */
$per_page      = 10;
$total_pages   = max(1, (int) ceil(count($users) / $per_page));
$page          = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$users_on_page = array_slice($users, ($page - 1) * $per_page, $per_page);

/* Builds a link to this page that keeps the current search and page, plus any extra values */
function users_link($extra = []) {
    return 'users.php?' . http_build_query(array_merge(['search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? 1], $extra));
}
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Users - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Manage Users</h1>
      <p class="lead">Search users, reset passwords and delete accounts. <?= count($users) ?> user(s) found.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <?php if ($edit_user): // only shown after clicking "Change Password" on a row ?>
        <div class="card mb-4">
          <div class="card-header">
            <h2 class="h5 mb-0">Change password for <?= htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name']) ?>
              (<?= htmlspecialchars($edit_user['username']) ?>)</h2>
          </div>
          <div class="card-body">
            <?php /* Hidden user_id tells section 1 (top of file) WHOSE password to change */ ?>
            <form method="post" class="row g-3">
              <input type="hidden" name="action" value="password">
              <input type="hidden" name="user_id" value="<?= (int) $edit_user['user_id'] ?>">
              <div class="col-md-5">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
              </div>
              <div class="col-md-5">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
              </div>
              <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="<?= htmlspecialchars(users_link()) ?>" class="btn btn-outline-secondary">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <label for="search" class="visually-hidden">Search users</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, username or email"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="users.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Name</th><th scope="col">Username</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Borrowed</th><th scope="col">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users_on_page as $u): // only this page's users ?>
            <tr>
              <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><?= htmlspecialchars(ucfirst($u['role_type'])) ?></td>
              <td><?= (int) $u['on_loan'] ?></td>
              <td class="d-flex gap-2">
                <?php /* users_link(['edit' => ID]) = this page + ?edit=ID, which opens the password form (section 4) */ ?>
                <a href="<?= htmlspecialchars(users_link(['edit' => $u['user_id']])) ?>" class="btn btn-sm btn-outline-primary">Change Password</a>

                <?php if ($u['user_id'] === $my_id): // section 2 refuses these too; this just explains the missing button ?>
                  <span class="small text-muted">You</span>
                <?php elseif ($u['on_loan'] > 0): ?>
                  <span class="small text-muted">Has books</span>
                <?php else: ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; Cancel stops the form from sending */ ?>
                  <form method="post" onsubmit="return confirm('Delete this user and all their loans and reservations? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($users)): // nothing matches the search ?>
            <tr><td colspan="6" class="text-center">
              <?= $search !== '' ? 'No users match "' . htmlspecialchars($search) . '".' : 'No users found.' ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php include_once (ROOT_PATH . '/include/pagination.php'); // Previous / 1 2 3 / Next bar ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
