<?php
/**
 * Manage Categories (Administrators)
 * Add a category, delete a category, and search categories by name.
 * (Built the same way as admin/authors.php and admin/publishers.php.)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminCategories";
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" ("add" or "delete") so we know which one was sent. */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   1. ADD A CATEGORY
   Only one table is involved (Category), so no transaction is needed.
   Both name and description are required (the database column is NOT NULL).
   We check for the same name first, so there are no duplicate categories.
   ------------------------------------------------------------------ */
if ($action === 'add') {
    $name        = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['category_description'] ?? '');

    // Does a category with this name already exist? (fetch() is true if a row was found)
    $check = $db_connection->prepare("SELECT category_id FROM Category WHERE category_name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    $exists = $check->fetch();
    $check->close();

    if ($name === '' || $description === '') {
        $error = 'Name and description are required.';
    } elseif ($exists) {
        $error = htmlspecialchars($name) . ' is already in the list.';
    } else {
        $insert = $db_connection->prepare("INSERT INTO Category (category_name, category_description) VALUES (?, ?)");
        $insert->bind_param("ss", $name, $description);
        $insert->execute();
        $insert->close();
        $message = 'Added ' . htmlspecialchars($name) . '.';
    }
}

/* ------------------------------------------------------------------
   2. DELETE A CATEGORY
   Not allowed while any book is in it (in BookCategories),
   same rule as authors and publishers.
   ------------------------------------------------------------------ */
if ($action === 'delete') {
    $category_id = (int) ($_POST['category_id'] ?? 0);

    // How many books are in this category?
    $check = $db_connection->prepare("SELECT COUNT(*) FROM BookCategories WHERE category_id = ?");
    $check->bind_param("i", $category_id);
    $check->execute();
    $check->bind_result($book_count);
    $check->fetch();
    $check->close();

    if ($book_count > 0) {
        $error = 'This category still has books in it.';
    } else {
        $delete = $db_connection->prepare("DELETE FROM Category WHERE category_id = ?");
        $delete->bind_param("i", $category_id);
        $delete->execute();
        // affected_rows = 1 if it was deleted, 0 if it was already gone
        $message = $delete->affected_rows > 0 ? 'Category deleted.' : '';
        $delete->close();
    }
}

/* SEARCH: $_GET reads the search from the URL, e.g. categories.php?search=fiction */
$search = trim($_GET['search'] ?? '');
$like   = "%" . $search . "%"; // % = wildcard, so "fan" matches "Fantasy"

/* ------------------------------------------------------------------
   3. GET CATEGORIES (matching the search), with how many books each one has
   LEFT JOIN keeps categories with no books (they show 0).
   COUNT(bc.book_id) counts only real books, so those categories get 0, not 1.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT c.category_id, c.category_name, c.category_description, COUNT(bc.book_id) AS book_count
    FROM Category c
    LEFT JOIN BookCategories bc ON c.category_id = bc.category_id
    WHERE c.category_name LIKE ?
    GROUP BY c.category_id
    ORDER BY c.category_name");
$stmt->bind_param("s", $like);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   4. PAGINATION: 10 categories per page (same code as the other admin pages).
   ------------------------------------------------------------------ */
$per_page           = 10;
$total_pages        = max(1, (int) ceil(count($categories) / $per_page));
$page               = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$categories_on_page = array_slice($categories, ($page - 1) * $per_page, $per_page);
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Categories - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Manage Categories</h1>
      <p class="lead">Add, delete and search categories. <?= count($categories) ?> category(ies) found.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <?php /* Add form: only 2 fields, so it's always shown (no collapse needed) */ ?>
      <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Add a Category</h2></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
              <label for="category_name" class="form-label">Name</label>
              <input type="text" id="category_name" name="category_name" class="form-control" maxlength="255" required>
            </div>
            <div class="col-md-6">
              <label for="category_description" class="form-label">Description</label>
              <input type="text" id="category_description" name="category_description" class="form-control" maxlength="255" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-success w-100">Add Category</button>
            </div>
          </form>
        </div>
      </div>

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <label for="search" class="visually-hidden">Search categories</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by name"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="categories.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Name</th><th scope="col">Description</th><th scope="col">Books</th><th scope="col">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categories_on_page as $c): // only this page's categories ?>
            <tr>
              <td><?= htmlspecialchars($c['category_name']) ?></td>
              <td><?= htmlspecialchars($c['category_description']) ?></td>
              <td><?= (int) $c['book_count'] ?></td>
              <td>
                <?php if ($c['book_count'] > 0): // section 2 refuses these too; this just explains the missing button ?>
                  <span class="small text-muted">Has books</span>
                <?php else: ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; Cancel stops the form from sending */ ?>
                  <form method="post" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($categories)): // nothing matches the search, or no categories yet ?>
            <tr><td colspan="4" class="text-center">
              <?= $search !== '' ? 'No categories match "' . htmlspecialchars($search) . '".' : 'No categories yet.' ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php include_once (ROOT_PATH . '/include/pagination.php'); // Previous / 1 2 3 / Next bar ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
