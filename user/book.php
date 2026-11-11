<?php
/**
 * Book Page: view a book, then Reserve or Borrow it (Front End Users)
 * Opened from books.php, e.g. book.php?book_id=3
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
   3. LOAN CHECKS (a loan is "active" while its return_date is still empty/NULL)
   ------------------------------------------------------------------ */
$max_loans = 5; // most books one user can have borrowed at the same time

/* 3a. Does this user already have this book borrowed?
   Loans point to a COPY, so we join BookCopies to find which book the copy belongs to. */
$check = $db_connection->prepare("
    SELECT l.due_date FROM Loans l
    INNER JOIN BookCopies c ON l.copy_id = c.copy_id
    WHERE l.user_id = ? AND c.book_id = ? AND l.return_date IS NULL");
$check->bind_param("ii", $user_id, $book_id);
$check->execute();
$check->bind_result($due_date);
$has_loan = $check->fetch(); // true if they already borrowed it
$check->close();

/* 3b. Find ONE copy of this book that nobody has borrowed right now.
   NOT IN (...) = skip copies that appear in an active loan. LIMIT 1 = we only need one. */
$check = $db_connection->prepare("
    SELECT copy_id FROM BookCopies
    WHERE book_id = ? AND copy_id NOT IN (SELECT copy_id FROM Loans WHERE return_date IS NULL)
    LIMIT 1");
$check->bind_param("i", $book_id);
$check->execute();
$check->bind_result($copy_id);
$has_copy = $check->fetch(); // true if a free copy was found (its id is in $copy_id)
$check->close();

/* 3c. How many books does this user have borrowed right now? (for the $max_loans limit) */
$check = $db_connection->prepare("SELECT COUNT(*) FROM Loans WHERE user_id = ? AND return_date IS NULL");
$check->bind_param("i", $user_id);
$check->execute();
$check->bind_result($loan_count);
$check->fetch();
$check->close();

/* Which button was clicked? Each button sends name="action" with value "reserve" or "borrow". */
$action = $_POST['action'] ?? '';

/* ------------------------------------------------------------------
   4. RESERVE BUTTON CLICKED?
   The form below uses method="post", so clicking it reloads this page as a POST.
   Only regular users (role 2) can reserve, the book must exist,
   they can't reserve the same book twice, or reserve a book they already borrowed.
   ------------------------------------------------------------------ */
if ($action === 'reserve' && $_SESSION['user_role'] == 2 && $book && !$has_reservation && !$has_loan) {
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

/* ------------------------------------------------------------------
   5. BORROW BUTTON CLICKED?
   Allowed if: regular user, book exists, not already borrowed,
   a free copy exists, and they are under the $max_loans limit.
   ------------------------------------------------------------------ */
if ($action === 'borrow' && $_SESSION['user_role'] == 2 && $book && !$has_loan && $has_copy && $loan_count < $max_loans) {
    /* Create the loan for the free copy. Due in 14 days. return_date stays NULL until returned. */
    $insert = $db_connection->prepare("
        INSERT INTO Loans (user_id, copy_id, checkout_date, due_date, loan_status)
        VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'active')");
    $insert->bind_param("ii", $user_id, $copy_id);
    $insert->execute();
    $insert->close();

    /* If they had this book reserved, the reservation is now done ('fulfilled') */
    $update = $db_connection->prepare("
        UPDATE Reservations SET reservation_status = 'fulfilled'
        WHERE user_id = ? AND book_id = ? AND reservation_status = 'active'");
    $update->bind_param("ii", $user_id, $book_id);
    $update->execute();
    $update->close();

    /* Update our variables so the page shows the loan right away */
    $has_loan = true;
    $has_reservation = false;
    $due_date = date('Y-m-d', strtotime('+14 days'));
    $message = 'Book borrowed!';
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

            <?php /* date('M j, Y', ...) formats 2026-11-18 as "Nov 18, 2026" */ ?>
            <?php if ($has_loan): // already borrowed: no buttons needed ?>
              <p class="text-success mb-0">
                <i class="bi bi-check-circle"></i>
                You have this book borrowed. Due back <?= date('M j, Y', strtotime($due_date)) ?>.
              </p>

            <?php elseif ($_SESSION['user_role'] == 2): // only regular users get the buttons ?>
              <?php if ($has_reservation): ?>
                <p class="text-success">
                  <i class="bi bi-check-circle"></i>
                  You have this book reserved until <?= date('M j, Y', strtotime($expire_date)) ?>.
                </p>
              <?php endif; ?>

              <?php /* One form, two buttons. name="action" + value tells PHP which one was clicked. */ ?>
              <form method="post" class="d-flex gap-2">
                <?php if ($has_copy && $loan_count < $max_loans): ?>
                  <button type="submit" name="action" value="borrow" class="btn btn-success"><i class="bi bi-box-arrow-right"></i> Borrow this book</button>
                <?php endif; ?>
                <?php if (!$has_reservation): ?>
                  <button type="submit" name="action" value="reserve" class="btn btn-primary"><i class="bi bi-bookmark-plus"></i> Reserve this book</button>
                <?php endif; ?>
              </form>

              <?php /* Explain why the Borrow button is missing */ ?>
              <?php if (!$has_copy): ?>
                <p class="text-muted mt-2 mb-0">No copies available right now. You can reserve it instead.</p>
              <?php elseif ($loan_count >= $max_loans): ?>
                <p class="text-muted mt-2 mb-0">You have reached the limit of <?= $max_loans ?> borrowed books. Return one to borrow more.</p>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
