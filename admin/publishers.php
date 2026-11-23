<?php
/**
 * Manage Publishers (Administrators)
 * Add a publisher, delete a publisher, and search publishers by name.
 * New publishers then show up in the Publisher dropdown on admin/books.php.
 * (Built the same way as admin/authors.php.)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminPublishers";
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" ("add" or "delete") so we know which one was sent. */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   1. ADD A PUBLISHER
   Only one table is involved (Publishers), so no transaction is needed.
   We check for the same name first, so the dropdown on the books page
   doesn't end up with the same publisher twice. The address is optional.
   ------------------------------------------------------------------ */
if ($action === 'add') {
    $name    = trim($_POST['publisher_name'] ?? '');
    $address = trim($_POST['publisher_address'] ?? '');
    $address = ($address === '') ? null : $address; // empty box = save NULL (no address)

    // Does a publisher with this name already exist? (fetch() is true if a row was found)
    $check = $db_connection->prepare("SELECT pub_id FROM Publishers WHERE publisher_name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    $exists = $check->fetch();
    $check->close();

    if ($name === '') {
        $error = 'Publisher name is required.';
    } elseif ($exists) {
        $error = htmlspecialchars($name) . ' is already in the list.';
    } else {
        $insert = $db_connection->prepare("INSERT INTO Publishers (publisher_name, publisher_address) VALUES (?, ?)");
        $insert->bind_param("ss", $name, $address);
        $insert->execute();
        $insert->close();
        $message = 'Added ' . htmlspecialchars($name) . '.';
    }
}

/* ------------------------------------------------------------------
   2. DELETE A PUBLISHER
   Not allowed while any book uses them (Books.pub_id), because every
   book must have a publisher. Delete those books first.
   ------------------------------------------------------------------ */
if ($action === 'delete') {
    $pub_id = (int) ($_POST['pub_id'] ?? 0);

    // How many books use this publisher?
    $check = $db_connection->prepare("SELECT COUNT(*) FROM Books WHERE pub_id = ?");
    $check->bind_param("i", $pub_id);
    $check->execute();
    $check->bind_result($book_count);
    $check->fetch();
    $check->close();

    if ($book_count > 0) {
        $error = 'This publisher still has books. Delete their books first.';
    } else {
        $delete = $db_connection->prepare("DELETE FROM Publishers WHERE pub_id = ?");
        $delete->bind_param("i", $pub_id);
        $delete->execute();
        // affected_rows = 1 if it was deleted, 0 if it was already gone
        $message = $delete->affected_rows > 0 ? 'Publisher deleted.' : '';
        $delete->close();
    }
}

/* SEARCH: $_GET reads the search from the URL, e.g. publishers.php?search=penguin */
$search = trim($_GET['search'] ?? '');
$like   = "%" . $search . "%"; // % = wildcard, so "harp" matches "HarperCollins"

/* ------------------------------------------------------------------
   3. GET PUBLISHERS (matching the search), with how many books each one has
   LEFT JOIN keeps publishers with no books (they show 0).
   COUNT(b.book_id) counts only real books, so those publishers get 0, not 1.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT p.pub_id, p.publisher_name, p.publisher_address, COUNT(b.book_id) AS book_count
    FROM Publishers p
    LEFT JOIN Books b ON p.pub_id = b.pub_id
    WHERE p.publisher_name LIKE ?
    GROUP BY p.pub_id
    ORDER BY p.publisher_name");
$stmt->bind_param("s", $like);
$stmt->execute();
$publishers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   4. PAGINATION: 10 publishers per page (same code as the other admin pages).
   ------------------------------------------------------------------ */
$per_page           = 10;
$total_pages        = max(1, (int) ceil(count($publishers) / $per_page));
$page               = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$publishers_on_page = array_slice($publishers, ($page - 1) * $per_page, $per_page);
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Publishers - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Manage Publishers</h1>
      <p class="lead">Add, delete and search publishers. <?= count($publishers) ?> publisher(s) found.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <?php /* Add form: only 2 fields, so it's always shown (no collapse needed) */ ?>
      <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0">Add a Publisher</h2></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
              <label for="publisher_name" class="form-label">Name</label>
              <input type="text" id="publisher_name" name="publisher_name" class="form-control" maxlength="255" required>
            </div>
            <div class="col-md-6">
              <label for="publisher_address" class="form-label">Address (optional)</label>
              <input type="text" id="publisher_address" name="publisher_address" class="form-control" maxlength="255">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-success w-100">Add Publisher</button>
            </div>
          </form>
        </div>
      </div>

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <label for="search" class="visually-hidden">Search publishers</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by name"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="publishers.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <?php /* table-responsive: on small screens only the table scrolls sideways, not the whole page */ ?>
      <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Name</th><th scope="col">Address</th><th scope="col">Books</th><th scope="col">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($publishers_on_page as $p): // only this page's publishers ?>
            <tr>
              <td><?= htmlspecialchars($p['publisher_name']) ?></td>
              <td><?= htmlspecialchars($p['publisher_address'] ?? '-') // '-' when there's no address ?></td>
              <td><?= (int) $p['book_count'] ?></td>
              <td>
                <?php if ($p['book_count'] > 0): // section 2 refuses these too; this just explains the missing button ?>
                  <span class="small text-muted">Has books</span>
                <?php else: ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; Cancel stops the form from sending */ ?>
                  <form method="post" onsubmit="return confirm('Delete this publisher?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="pub_id" value="<?= (int) $p['pub_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($publishers)): // nothing matches the search, or no publishers yet ?>
            <tr><td colspan="4" class="text-center">
              <?= $search !== '' ? 'No publishers match "' . htmlspecialchars($search) . '".' : 'No publishers yet.' ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>

      <?php include_once (ROOT_PATH . '/include/pagination.php'); // Previous / 1 2 3 / Next bar ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
