<?php
/**
 * Manage Books (Administrators)
 * Search books by title, ISBN or author, and see how many copies are on the shelf.
 * (Add / Delete Books will be added to this page later.)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminBooks";

/* SEARCH: $_GET reads the search from the URL, e.g. books.php?search=orwell */
$search = trim($_GET['search'] ?? '');

/* % is a wildcard in SQL LIKE: "%gat%" matches any text containing "gat".
   An empty search becomes "%%", which matches every book. */
$like = "%" . $search . "%";

/* ------------------------------------------------------------------
   1. GET BOOKS (matching the search) with publisher, author(s) and copy counts
   - Same joins as user/books.php: LEFT JOIN keeps books with no publisher/author.
   - GROUP_CONCAT joins several authors into one string, e.g. "A, B".
   - The two (SELECT COUNT(*) ...) are small queries run for each book:
       total_copies = how many copies the library owns
       on_loan      = how many of those copies are borrowed right now
   - HAVING (instead of WHERE) runs AFTER the authors are joined together,
     so the search can also match the author list, not just title and ISBN.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT b.book_id, b.title, b.isbn, b.publication_year, p.publisher_name,
           GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors,
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
   2. PAGINATION: 10 books per page (same code as user/books.php).
   min/max keep ?page= between 1 and the last page; array_slice() cuts out that page's rows.
   ------------------------------------------------------------------ */
$per_page      = 10;
$total_pages   = max(1, (int) ceil(count($books) / $per_page));
$page          = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$books_on_page = array_slice($books, ($page - 1) * $per_page, $per_page);
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>Manage Books - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main class="container pb-5">
      <h2>Manage Books</h2>
      <p class="lead">Search the library collection. <?= count($books) ?> book(s) found.</p>
      <hr class="mb-4">

      <?php /* Search form: method="get" reloads this page with ?search=... in the URL.
               value= keeps what the admin typed in the box. */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <input type="text" name="search" class="form-control" placeholder="Search by title, ISBN or author"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="books.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr><th>Title</th><th>Author(s)</th><th>ISBN</th><th>Publisher</th><th>Year</th><th>Copies</th></tr>
        </thead>
        <tbody>
          <?php foreach ($books_on_page as $book): // only this page's books ?>
            <?php $available = $book['total_copies'] - $book['on_loan']; // copies still on the shelf ?>
            <tr>
              <td><?= htmlspecialchars($book['title']) ?></td>
              <td><?= htmlspecialchars($book['authors'] ?? 'Unknown') ?></td>
              <td><?= htmlspecialchars($book['isbn']) ?></td>
              <td><?= htmlspecialchars($book['publisher_name'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($book['publication_year']) ?></td>
              <?php /* e.g. "1 of 2 available"; red when every copy is borrowed */ ?>
              <td class="<?= $available > 0 ? '' : 'text-danger' ?>">
                <?= $available ?> of <?= $book['total_copies'] ?> available
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($books)): // no rows: either nothing matches the search, or no books yet ?>
            <tr><td colspan="6" class="text-center">
              <?= $search !== '' ? 'No books match "' . htmlspecialchars($search) . '".' : 'No books found in the library database.' ?>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php include_once (ROOT_PATH . '/include/pagination.php'); // Previous / 1 2 3 / Next bar ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
