<?php
/**
 * View Available Books Page (Front End Users)
 */

/* Error settings: E_ALL = report every kind of error.
   display_errors 0 = don't show them on the page (switch to 1 while debugging). */
error_reporting(E_ALL);
ini_set('display_errors', 0);

/* include_once pulls another PHP file into this one (only once, even if asked twice).
   dirname(__FILE__, 2) = go up 2 folders from this file -> the csc235_login folder.
   session.php starts the session, checks the user is logged in, and also loads
   path.php (BASE_URL, ROOT_PATH) and config.php (gives us $db_connection). */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Used as the <body> class below, handy for page-specific CSS */
$page_name = "books";

/* SEARCH: $_GET reads values from the URL, e.g. books.php?search=gatsby
   trim() removes extra spaces. If there is no search, use an empty string ''. */
$search = trim($_GET['search'] ?? '');

/* % is a wildcard in SQL LIKE: "%gat%" matches any text containing "gat".
   An empty search becomes "%%", which matches every book. */
$like = "%" . $search . "%";

/* ------------------------------------------------------------------
   DATABASE: get books (matching the search) with publisher and author(s)
   - b, p, ba, a are short nicknames (aliases) for the tables.
   - LEFT JOIN keeps a book even if it has no publisher/author yet.
   - WHERE ... LIKE ? filters by title OR isbn. The ? marks are filled in below.
   - GROUP_CONCAT joins several authors into one string, e.g. "A, B".
   - GROUP BY b.book_id = one row per book (needed for GROUP_CONCAT).
   ------------------------------------------------------------------ */
$query = "
    SELECT b.book_id, b.title, b.isbn, b.publication_year, p.publisher_name,
           GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
    FROM Books b
    LEFT JOIN Publishers  p  ON b.pub_id     = p.pub_id
    LEFT JOIN BookAuthors ba ON b.book_id    = ba.book_id
    LEFT JOIN Authors     a  ON ba.author_id = a.author_id
    WHERE b.title LIKE ? OR b.isbn LIKE ?
    GROUP BY b.book_id
    ORDER BY b.title";

/* Prepared statement: the user's search is sent separately from the SQL,
   so it can never change the query itself (prevents SQL injection).
   bind_param("ss", ...) = fill the two ? marks with two strings ("s" = string). */
$stmt = $db_connection->prepare($query);
$stmt->bind_param("ss", $like, $like);
$stmt->execute();

/* get_result() gets the answer, fetch_all(MYSQLI_ASSOC) turns ALL rows into an array.
   MYSQLI_ASSOC = each row uses column names as keys, e.g. $book['title']. */
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* We already have the data stored in $books, so close the database connection */
$db_connection->close();

/* ------------------------------------------------------------------
   PAGINATION: show 10 books per page instead of one very long table.
   ?page=2 in the URL picks the page. min/max keep it between 1 and the last page,
   so a made-up value like ?page=999 can't break anything.
   array_slice() cuts out just the 10 rows for that page.
   The Previous / 1 2 3 / Next bar is in include/pagination.php.
   ------------------------------------------------------------------ */
$per_page      = 10;
$total_pages   = max(1, (int) ceil(count($books) / $per_page)); // e.g. 25 books -> 3 pages
$page          = min(max(1, (int) ($_GET['page'] ?? 1)), $total_pages);
$books_on_page = array_slice($books, ($page - 1) * $per_page, $per_page);

/* The ?> below ends PHP. Everything after it is normal HTML,
   with small bits of PHP dropped in wherever we need data. */
?>
<!doctype html>
<html lang="en">
  <head>
    <?php /* ROOT_PATH = full folder path on disk to csc235_login (from path.php) */ ?>
    <?php include_once (ROOT_PATH . '/include/head.php'); ?>
    <title>Available Books - Library Prototype</title>
  </head>
  <?php /* <?= something ?> is short for <?php echo something; ?> (prints a value into the HTML) */ ?>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); // the navbar ?>

    <main class="container">
      <h1 class="h2">Available Books</h1>
      <p class="lead">Browse the library collection below.</p>
      <hr class="mb-4">

      <?php /* Search form: method="get" puts the search in the URL (books.php?search=...)
               and reloads this same page. value= keeps what the user typed in the box. */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <?php /* visually-hidden = label is invisible on screen but still read by screen readers */ ?>
        <label for="search" class="visually-hidden">Search books</label>
        <input type="text" id="search" name="search" class="form-control" placeholder="Search by title or ISBN"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
        <?php if ($search !== ''): // only show "Clear" while searching ?>
          <a href="books.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr>
            <th scope="col">Title</th><th scope="col">Author(s)</th><th scope="col">ISBN</th><th scope="col">Publisher</th><th scope="col">Year</th><th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          /* foreach: loop through $books, one $book (row) at a time.
             htmlspecialchars(): turns < > & " into safe text so the browser
             displays them instead of running them as code (prevents XSS attacks).
             ?? : use the right side if the left side is empty, e.g. 'Unknown'. */
          ?>
          <?php foreach ($books_on_page as $book): // only this page's books ?>
            <tr>
              <td><?= htmlspecialchars($book['title']) ?></td>
              <td><?= htmlspecialchars($book['authors'] ?? 'Unknown') ?></td>
              <td><?= htmlspecialchars($book['isbn']) ?></td>
              <td><?= htmlspecialchars($book['publisher_name'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($book['publication_year']) ?></td>
              <td>
                <?php /* (int) forces book_id to be a whole number, so nothing harmful can sneak into the link */ ?>
                <a href="book.php?book_id=<?= (int) $book['book_id'] ?>" class="btn btn-sm btn-primary">Reserve / View</a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php /* empty() is true when $books has no rows, so show a message instead of a blank table */ ?>
          <?php if (empty($books)): ?>
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
