<?php
/**
 * Manage Authors (Administrators)
 * Add an author, delete an author, and search authors by name.
 * New authors then show up in the Author dropdown on admin/books.php.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminAuthors";
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" ("add" or "delete") so we know which one was sent. */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   1. ADD AN AUTHOR
   Only one table is involved (Authors), so no transaction is needed.
   We check for the same first + last name first, so the dropdown
   on the books page doesn't end up with the same author twice.
   ------------------------------------------------------------------ */
if ($action === 'add') {
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');

    // Does an author with this exact name already exist? (fetch() is true if a row was found)
    $check = $db_connection->prepare("SELECT author_id FROM Authors WHERE first_name = ? AND last_name = ?");
    $check->bind_param("ss", $first, $last);
    $check->execute();
    $exists = $check->fetch();
    $check->close();

    if ($first === '' || $last === '') {
        $error = 'First and last name are required.';
    } elseif ($exists) {
        $error = htmlspecialchars("$first $last") . ' is already in the list.';
    } else {
        $insert = $db_connection->prepare("INSERT INTO Authors (first_name, last_name) VALUES (?, ?)");
        $insert->bind_param("ss", $first, $last);
        $insert->execute();
        $insert->close();
        $message = 'Added ' . htmlspecialchars("$first $last") . '.';
    }
}

/* ------------------------------------------------------------------
   2. DELETE AN AUTHOR
   Not allowed while any book is linked to them (in BookAuthors),
   or those books would lose their author. Delete or change the books first.
   ------------------------------------------------------------------ */
if ($action === 'delete') {
    $author_id = (int) ($_POST['author_id'] ?? 0);

    // How many books is this author linked to?
    $check = $db_connection->prepare("SELECT COUNT(*) FROM BookAuthors WHERE author_id = ?");
    $check->bind_param("i", $author_id);
    $check->execute();
    $check->bind_result($book_count);
    $check->fetch();
    $check->close();

    if ($book_count > 0) {
        $error = 'This author still has books. Delete their books first.';
    } else {
        $delete = $db_connection->prepare("DELETE FROM Authors WHERE author_id = ?");
        $delete->bind_param("i", $author_id);
        $delete->execute();
        // affected_rows = 1 if it was deleted, 0 if it was already gone
        $message = $delete->affected_rows > 0 ? 'Author deleted.' : '';
        $delete->close();
    }
}

/* SEARCH: $_GET reads the search from the URL, e.g. authors.php?search=king */
$search = trim($_GET['search'] ?? '');
$like   = "%" . $search . "%"; // % = wildcard, so "orw" matches "Orwell"

/* ------------------------------------------------------------------
   3. GET AUTHORS (matching the search), with how many books each one has
   LEFT JOIN keeps authors with no books (they show 0).
   COUNT(ba.book_id) counts only real links, so those authors get 0, not 1.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT a.author_id, a.first_name, a.last_name, COUNT(ba.book_id) AS book_count
    FROM Authors a
    LEFT JOIN BookAuthors ba ON a.author_id = ba.author_id
    WHERE CONCAT(a.first_name, ' ', a.last_name) LIKE ?
    GROUP BY a.author_id
    ORDER BY a.last_name, a.first_name");
$stmt->bind_param("s", $like);
$stmt->execute();
$authors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   4. PAGINATION: 10 authors per page (same code as the books pages).
   ------------------------------------------------------------------ */
$per_page        = 10;
$total_pages     = max(1, (int) ceil(count($authors) / $per_page));
$page            = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$authors_on_page = array_slice($authors, ($page - 1) * $per_page, $per_page);
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Authors - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Manage Authors</h1>
      <p class="lead">Add, delete and search authors. <?= count($authors) ?> author(s) found.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <?php /* Add form: only 2 fields, so it's always shown (no collapse needed) */ ?>
      <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Add an Author</h2></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-5">
              <label for="first_name" class="form-label">First Name</label>
              <input type="text" id="first_name" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label for="last_name" class="form-label">Last Name</label>
              <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-success w-100">Add Author</button>
            </div>
          </form>
        </div>
      </div>

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <label for="search" class="visually-hidden">Search authors</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by name"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="authors.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Last Name</th><th scope="col">First Name</th><th scope="col">Books</th><th scope="col">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($authors_on_page as $a): // only this page's authors ?>
            <tr>
              <td><?= htmlspecialchars($a['last_name']) ?></td>
              <td><?= htmlspecialchars($a['first_name']) ?></td>
              <td><?= (int) $a['book_count'] ?></td>
              <td>
                <?php if ($a['book_count'] > 0): // section 2 refuses these too; this just explains the missing button ?>
                  <span class="small text-muted">Has books</span>
                <?php else: ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; Cancel stops the form from sending */ ?>
                  <form method="post" onsubmit="return confirm('Delete this author?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="author_id" value="<?= (int) $a['author_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($authors)): // nothing matches the search, or no authors yet ?>
            <tr><td colspan="4" class="text-center">
              <?= $search !== '' ? 'No authors match "' . htmlspecialchars($search) . '".' : 'No authors yet.' ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php include_once (ROOT_PATH . '/include/pagination.php'); // Previous / 1 2 3 / Next bar ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
