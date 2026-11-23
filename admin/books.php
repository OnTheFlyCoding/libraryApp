<?php
/**
 * Manage Books (Administrators)
 * Add a book (with its copies), delete a book, and search books by title, ISBN or author.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminBooks";
$message   = ''; // green box when something worked
$error     = ''; // red box when something went wrong

/* Each form has a hidden input name="action" ("add" or "delete") so we know which one was sent. */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   1. ADD A BOOK
   A book needs rows in 3 tables (4 if a category is picked):
     Books          = the title, ISBN, year, publisher
     BookAuthors    = links the book to its author
     BookCopies     = one row per physical copy (loans point to a copy)
     BookCategories = links the book to its category (optional)
   A transaction makes the inserts all-or-nothing: if one fails,
   rollback() undoes the others, so we never end up with half a book.
   ------------------------------------------------------------------ */
if ($action === 'add') {
    $title     = trim($_POST['title'] ?? '');
    $isbn      = trim($_POST['isbn'] ?? '');
    $volume    = trim($_POST['volume'] ?? '1');
    $year      = (int) ($_POST['year'] ?? 0);
    $pub_id    = (int) ($_POST['pub_id'] ?? 0);
    $author_id = (int) ($_POST['author_id'] ?? 0);
    $category_id = (int) ($_POST['category_id'] ?? 0); // 0 = "No category" was picked
    $copies    = (int) ($_POST['copies'] ?? 0);

    if ($title === '' || $isbn === '' || $pub_id === 0 || $author_id === 0) {
        $error = 'Title, ISBN, publisher and author are required.';
    } elseif ($year < 1901 || $year > 2155) {
        $error = 'Year must be between 1901 and 2155.'; // the limits of MySQL's YEAR column
    } elseif ($copies < 1 || $copies > 10) {
        $error = 'Number of copies must be between 1 and 10.';
    } else {
        try {
            $db_connection->begin_transaction();

            $insert = $db_connection->prepare("
                INSERT INTO Books (title, isbn, book_volume, publication_year, pub_id) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("sssii", $title, $isbn, $volume, $year, $pub_id);
            $insert->execute();
            $new_id = $insert->insert_id; // the book_id MySQL just gave the new book
            $insert->close();

            $insert = $db_connection->prepare("INSERT INTO BookAuthors (author_id, book_id) VALUES (?, ?)");
            $insert->bind_param("ii", $author_id, $new_id);
            $insert->execute();
            $insert->close();

            if ($category_id > 0) { // only link a category if one was picked
                $insert = $db_connection->prepare("INSERT INTO BookCategories (category_id, book_id) VALUES (?, ?)");
                $insert->bind_param("ii", $category_id, $new_id);
                $insert->execute();
                $insert->close();
            }

            // Barcodes must be unique, so new copies continue from the highest barcode so far
            $result  = $db_connection->query("SELECT COALESCE(MAX(barcode), 100000) FROM BookCopies");
            $barcode = (int) $result->fetch_row()[0];

            $insert = $db_connection->prepare("INSERT INTO BookCopies (barcode, book_id) VALUES (?, ?)");
            $insert->bind_param("ii", $barcode, $new_id); // bind once; the loop just changes $barcode
            for ($i = 1; $i <= $copies; $i++) {
                $barcode++;
                $insert->execute();
            }
            $insert->close();

            $db_connection->commit(); // all 3 worked, so save them
            $message = 'Added "' . htmlspecialchars($title) . '" with ' . $copies . ' copy(ies).';
        } catch (mysqli_sql_exception $e) {
            $db_connection->rollback(); // something failed, so undo everything from this add
            $error = 'Could not add the book.';
        }
    }
}

/* ------------------------------------------------------------------
   2. DELETE A BOOK
   Not allowed while any copy is borrowed (the user still has it).
   Otherwise, rows that point TO the book must be deleted first,
   or the foreign keys stop us from deleting the book itself:
     old Loans of its copies -> Reservations -> BookCategories
     -> BookAuthors -> BookCopies -> finally the Book.
   Same all-or-nothing transaction as adding.
   ------------------------------------------------------------------ */
if ($action === 'delete') {
    $book_id = (int) ($_POST['book_id'] ?? 0);

    // Is any copy of this book borrowed right now?
    $check = $db_connection->prepare("
        SELECT COUNT(*) FROM Loans l INNER JOIN BookCopies c ON l.copy_id = c.copy_id
        WHERE c.book_id = ? AND l.return_date IS NULL");
    $check->bind_param("i", $book_id);
    $check->execute();
    $check->bind_result($on_loan);
    $check->fetch();
    $check->close();

    if ($on_loan > 0) {
        $error = 'This book can\'t be deleted while a copy is borrowed.';
    } else {
        // Each query deletes this book's rows from one table, in the order explained above
        $deletes = [
            "DELETE l FROM Loans l INNER JOIN BookCopies c ON l.copy_id = c.copy_id WHERE c.book_id = ?",
            "DELETE FROM Reservations   WHERE book_id = ?",
            "DELETE FROM BookCategories WHERE book_id = ?",
            "DELETE FROM BookAuthors    WHERE book_id = ?",
            "DELETE FROM BookCopies     WHERE book_id = ?",
            "DELETE FROM Books          WHERE book_id = ?",
        ];
        try {
            $db_connection->begin_transaction();
            foreach ($deletes as $sql) {
                $delete = $db_connection->prepare($sql);
                $delete->bind_param("i", $book_id);
                $delete->execute();
                $deleted = $delete->affected_rows; // after the loop, this is from the Books delete
                $delete->close();
            }
            $db_connection->commit();
            $message = $deleted > 0 ? 'Book deleted.' : ''; // 0 = the book was already gone
        } catch (mysqli_sql_exception $e) {
            $db_connection->rollback();
            $error = 'Could not delete the book.';
        }
    }
}

/* ------------------------------------------------------------------
   3. GET PUBLISHERS, AUTHORS AND CATEGORIES for the Add form's dropdowns
   ------------------------------------------------------------------ */
$publishers = $db_connection->query("SELECT pub_id, publisher_name FROM Publishers ORDER BY publisher_name")->fetch_all(MYSQLI_ASSOC);
$authors    = $db_connection->query("SELECT author_id, first_name, last_name FROM Authors ORDER BY last_name, first_name")->fetch_all(MYSQLI_ASSOC);
$categories = $db_connection->query("SELECT category_id, category_name FROM Category ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

/* SEARCH: $_GET reads the search from the URL, e.g. books.php?search=orwell */
$search = trim($_GET['search'] ?? '');

/* % is a wildcard in SQL LIKE: "%gat%" matches any text containing "gat".
   An empty search becomes "%%", which matches every book. */
$like = "%" . $search . "%";

/* ------------------------------------------------------------------
   4. GET BOOKS (matching the search) with publisher, author(s) and copy counts
   - Same joins as user/books.php: LEFT JOIN keeps books with no publisher/author.
   - GROUP_CONCAT joins several authors into one string, e.g. "A, B".
   - The (SELECT ...) parts are small queries run for each book:
       categories   = the book's category name(s), or NULL if it has none
       total_copies = how many copies the library owns
       on_loan      = how many of those copies are borrowed right now
     (Done as small queries instead of more JOINs, so the author rows don't get repeated.)
   - HAVING (instead of WHERE) runs AFTER the authors are joined together,
     so the search can also match the author list, not just title and ISBN.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT b.book_id, b.title, b.isbn, b.publication_year, p.publisher_name,
           GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors,
           (SELECT GROUP_CONCAT(cat.category_name SEPARATOR ', ') FROM BookCategories bcat
              INNER JOIN Category cat ON bcat.category_id = cat.category_id
              WHERE bcat.book_id = b.book_id) AS categories,
           (SELECT COUNT(*) FROM BookCopies bc WHERE bc.book_id = b.book_id) AS total_copies,
           (SELECT COUNT(*) FROM BookCopies bc
              INNER JOIN Loans l ON l.copy_id = bc.copy_id
              WHERE bc.book_id = b.book_id AND l.return_date IS NULL) AS on_loan
    FROM Books b
    LEFT JOIN Publishers  p  ON b.pub_id     = p.pub_id
    LEFT JOIN BookAuthors ba ON b.book_id    = ba.book_id
    LEFT JOIN Authors     a  ON ba.author_id = a.author_id
    GROUP BY b.book_id
    HAVING b.title LIKE ? OR b.isbn LIKE ? OR authors LIKE ?
    ORDER BY b.title");
$stmt->bind_param("sss", $like, $like, $like); // fill the three ? marks with the search
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   5. PAGINATION: 10 books per page (same code as user/books.php).
   min/max keep ?page= between 1 and the last page; array_slice() cuts out that page's rows.
   ------------------------------------------------------------------ */
$per_page      = 10;
$total_pages   = max(1, (int) ceil(count($books) / $per_page));
$page          = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$books_on_page = array_slice($books, ($page - 1) * $per_page, $per_page);

/* Keep the Add form open if adding failed, so the admin can fix it without retyping */
$add_failed = ($action === 'add' && $error !== '');
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Books - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Manage Books</h1>
      <p class="lead">Add, delete and search books. <?= count($books) ?> book(s) found.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success" role="alert"><?= $message ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $error ?></div>
      <?php endif; ?>

      <?php /* Bootstrap "collapse": the button shows/hides the form with id="addBook".
               "show" makes it start open (only when adding failed). */ ?>
      <button class="btn btn-success mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addBook"
              aria-expanded="<?= $add_failed ? 'true' : 'false' ?>" aria-controls="addBook">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Add a Book
      </button>

      <div class="collapse <?= $add_failed ? 'show' : '' ?>" id="addBook">
        <div class="card card-body mb-4">
          <?php /* value="<?= ... ?>" refills the boxes after a failed add (htmlspecialchars keeps it safe) */ ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="add">
            <div class="col-md-6">
              <label for="title" class="form-label">Title</label>
              <input type="text" id="title" name="title" class="form-control" required
                     value="<?= $add_failed ? htmlspecialchars($_POST['title'] ?? '') : '' ?>">
            </div>
            <div class="col-md-6">
              <label for="isbn" class="form-label">ISBN</label>
              <input type="text" id="isbn" name="isbn" class="form-control" maxlength="20" required
                     value="<?= $add_failed ? htmlspecialchars($_POST['isbn'] ?? '') : '' ?>">
            </div>
            <div class="col-md-6">
              <label for="author_id" class="form-label">Author</label>
              <select id="author_id" name="author_id" class="form-select" required>
                <option value="">Choose an author...</option>
                <?php foreach ($authors as $a): ?>
                  <option value="<?= (int) $a['author_id'] ?>"><?= htmlspecialchars($a['last_name'] . ', ' . $a['first_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="pub_id" class="form-label">Publisher</label>
              <select id="pub_id" name="pub_id" class="form-select" required>
                <option value="">Choose a publisher...</option>
                <?php foreach ($publishers as $p): ?>
                  <option value="<?= (int) $p['pub_id'] ?>"><?= htmlspecialchars($p['publisher_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="category_id" class="form-label">Category</label>
              <select id="category_id" name="category_id" class="form-select">
                <option value="0">No category</option><?php // value 0 = don't link a category (optional) ?>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="year" class="form-label">Year</label>
              <input type="number" id="year" name="year" class="form-control" min="1901" max="2155" required
                     value="<?= $add_failed ? (int) ($_POST['year'] ?? 0) : '' ?>">
            </div>
            <div class="col-md-2">
              <label for="volume" class="form-label">Volume</label>
              <input type="text" id="volume" name="volume" class="form-control" maxlength="20" value="1">
            </div>
            <div class="col-md-3">
              <label for="copies" class="form-label">Number of Copies</label>
              <input type="number" id="copies" name="copies" class="form-control" min="1" max="10" value="1" required>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-success">Add Book</button>
            </div>
          </form>
        </div>
      </div>

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL.
               value= keeps what the admin typed in the box. */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <?php /* visually-hidden = label is invisible on screen but still read by screen readers */ ?>
        <label for="search" class="visually-hidden">Search books</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by title, ISBN or author"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="books.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <?php /* table-responsive: on small screens only the table scrolls sideways, not the whole page */ ?>
      <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Title</th><th scope="col">Author(s)</th><th scope="col">Category</th><th scope="col">ISBN</th><th scope="col">Publisher</th><th scope="col">Year</th><th scope="col">Copies</th><th scope="col">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($books_on_page as $book): // only this page's books ?>
            <?php $available = $book['total_copies'] - $book['on_loan']; // copies still on the shelf ?>
            <tr>
              <td><?= htmlspecialchars($book['title']) ?></td>
              <td><?= htmlspecialchars($book['authors'] ?? 'Unknown') ?></td>
              <td><?= htmlspecialchars($book['categories'] ?? '-') // '-' when the book has no category ?></td>
              <td><?= htmlspecialchars($book['isbn']) ?></td>
              <td><?= htmlspecialchars($book['publisher_name'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($book['publication_year']) ?></td>
              <?php /* e.g. "1 of 2 available"; red when every copy is borrowed */ ?>
              <td class="<?= $available > 0 ? '' : 'text-danger' ?>">
                <?= $available ?> of <?= $book['total_copies'] ?> available
              </td>
              <td>
                <?php if ($book['on_loan'] > 0): // section 2 would refuse anyway; this just explains why there's no button ?>
                  <span class="small text-muted">On loan</span>
                <?php else: ?>
                  <?php /* No action="" = the form posts back to this same URL, so the search and page stay.
                           confirm() shows an "Are you sure?" pop-up; Cancel stops the form from sending. */ ?>
                  <form method="post" onsubmit="return confirm('Delete this book and all its copies? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="book_id" value="<?= (int) $book['book_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($books)): // no rows: either nothing matches the search, or no books yet ?>
            <tr><td colspan="8" class="text-center">
              <?= $search !== '' ? 'No books match "' . htmlspecialchars($search) . '".' : 'No books found in the library database.' ?>
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
