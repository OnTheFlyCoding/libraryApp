<?php
/**
 * Reserve / View Book Page (Front End Users)
 * Opened from books.php, e.g. reserve.php?book_id=3
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection (same as books.php) */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

$page_name = "reserve";

/* Which book? (int) turns the URL value into a whole number (0 if missing/invalid). */
$book_id = (int) ($_GET['book_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$message = ''; // success message shown after reserving

/* ------------------------------------------------------------------
   1. GET THE BOOK (same joins as books.php, but for ONE book)
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT b.book_id, b.title, b.isbn, b.book_volume, b.publication_year, p.publisher_name,
           GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS authors
    FROM Books b
    LEFT JOIN Publishers  p  ON b.pub_id     = p.pub_id
    LEFT JOIN BookAuthors ba ON b.book_id    = ba.book_id
    LEFT JOIN Authors     a  ON ba.author_id = a.author_id
    WHERE b.book_id = ?
    GROUP BY b.book_id");
$stmt->bind_param("i", $book_id); // "i" = integer
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc(); // fetch_assoc() = just ONE row (null if no book found)
$stmt->close();

/* ------------------------------------------------------------------
   2. DOES THIS USER ALREADY HAVE AN ACTIVE RESERVATION FOR IT?
   CURDATE() = today's date in MySQL, so expired reservations don't count.
   ------------------------------------------------------------------ */
$check = $db_connection->prepare("
    SELECT expire_date FROM Reservations
    WHERE user_id = ? AND book_id = ? AND reservation_status = 'active' AND expire_date >= CURDATE()");
$check->bind_param("ii", $user_id, $book_id);
$check->execute();
$check->bind_result($expire_date);  // put the expire_date column into $expire_date
$has_reservation = $check->fetch(); // true if a row was found, otherwise null
$check->close();

/* ------------------------------------------------------------------
   3. RESERVE BUTTON CLICKED?
   The form below uses method="post", so clicking it reloads this page as a POST.
   Only regular users (role 2) can reserve, the book must exist,
   and they can't reserve the same book twice.
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_role'] == 2 && $book && !$has_reservation) {
    /* DATE_ADD(..., INTERVAL 7 DAY) = the reservation lasts one week */
    $insert = $db_connection->prepare("
        INSERT INTO Reservations (user_id, book_id, reservation_date, expire_date, reservation_status)
        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active')");
    $insert->bind_param("ii", $user_id, $book_id);
    $insert->execute();
    $insert->close();

    /* Update our variables so the page shows the new reservation right away */
    $has_reservation = true;
    $expire_date = date('Y-m-d', strtotime('+7 days'));
    $message = 'Book reserved!';
}

$db_connection->close();
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); ?>
    <title>Reserve Book - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main class="container pb-5">
      <a href="books.php" class="btn btn-link px-0 mb-3"><i class="bi bi-arrow-left"></i> Back to books</a>

      <?php if (!$book): // no book with that id ?>
        <div class="alert alert-warning">Book not found.</div>

      <?php else: ?>
        <?php if ($message): // green box after reserving ?>
          <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php /* Bootstrap "card" = a bordered box with a header and body */ ?>
        <div class="card">
          <div class="card-header"><h3 class="mb-0"><?= htmlspecialchars($book['title']) ?></h3></div>
          <div class="card-body">
            <?php /* dl/dt/dd = a list of labels (dt) and values (dd); row/col-sm make it 2 columns */ ?>
            <dl class="row mb-4">
              <dt class="col-sm-3">Author(s)</dt> <dd class="col-sm-9"><?= htmlspecialchars($book['authors'] ?? 'Unknown') ?></dd>
              <dt class="col-sm-3">ISBN</dt>      <dd class="col-sm-9"><?= htmlspecialchars($book['isbn']) ?></dd>
              <dt class="col-sm-3">Publisher</dt> <dd class="col-sm-9"><?= htmlspecialchars($book['publisher_name'] ?? 'N/A') ?></dd>
              <dt class="col-sm-3">Year</dt>      <dd class="col-sm-9"><?= htmlspecialchars($book['publication_year']) ?></dd>
              <dt class="col-sm-3">Volume</dt>    <dd class="col-sm-9"><?= htmlspecialchars($book['book_volume']) ?></dd>
            </dl>

            <?php if ($has_reservation): ?>
              <?php /* date('M j, Y', ...) formats 2026-11-18 as "Nov 18, 2026" */ ?>
              <p class="text-success mb-0">
                <i class="bi bi-check-circle"></i>
                You have this book reserved until <?= date('M j, Y', strtotime($expire_date)) ?>.
              </p>
            <?php elseif ($_SESSION['user_role'] == 2): // only regular users get the button ?>
              <form method="post">
                <button type="submit" class="btn btn-primary"><i class="bi bi-bookmark-plus"></i> Reserve this book</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
