<?php
/**
 * My Loans Page (Front End Users)
 * Lists the logged-in user's borrowed books, with a Return button for active ones.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection (same as books.php) */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

$page_name = "loans";
$user_id   = $_SESSION['user_id'];
$max_loans = 5; // borrow limit, should match $max_loans in book.php
$message   = '';

/* ------------------------------------------------------------------
   1. RETURN BUTTON CLICKED?
   Each Return button is a small POST form that sends the loan_id.
   Filling in return_date is what marks the copy as free again for other users.
   "AND user_id = ?" makes sure users can only return THEIR OWN loans.
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = (int) ($_POST['loan_id'] ?? 0);

    $return = $db_connection->prepare("
        UPDATE Loans SET return_date = CURDATE(), loan_status = 'returned'
        WHERE loan_id = ? AND user_id = ? AND return_date IS NULL");
    $return->bind_param("ii", $loan_id, $user_id);
    $return->execute();

    // Only show the message if a row was actually changed
    if ($return->affected_rows > 0) {
        $message = 'Book returned.';
    }
    $return->close();
}

/* ------------------------------------------------------------------
   2. GET THIS USER'S LOANS, with the book title
   Loans point to a COPY, so we go Loans -> BookCopies -> Books to get the title.
   Returned loans only show for 30 days, so the list stays tidy (rows are NOT deleted).
   ORDER BY: not-returned loans first (return_date IS NULL = 1), then newest first.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT l.loan_id, l.checkout_date, l.due_date, l.return_date,
           b.book_id, b.title
    FROM Loans l
    INNER JOIN BookCopies c ON l.copy_id = c.copy_id
    INNER JOIN Books b      ON c.book_id = b.book_id
    WHERE l.user_id = ?
      AND (l.return_date IS NULL OR l.return_date >= CURDATE() - INTERVAL 30 DAY)
    ORDER BY (l.return_date IS NULL) DESC, l.checkout_date DESC, l.loan_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

// Count books still borrowed (no return_date yet) to show "X of 5" above the table
$active_count = 0;
foreach ($loans as $l) {
    if ($l['return_date'] === null) {
        $active_count++;
    }
}

/* Bootstrap badge colour for each status (green, red, grey) */
$badge = ['active' => 'success', 'overdue' => 'danger', 'returned' => 'secondary'];
$today = date('Y-m-d'); // used to spot overdue loans
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>My Loans - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main id="main" class="container pb-5">
      <h1 class="h2">Active Loans</h1>
      <p class="lead">
        Books you have borrowed. Loans last 14 days.
        You have <strong><?= $active_count ?> of <?= $max_loans ?></strong> books borrowed.
      </p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
      <?php endif; ?>

      <table class="table table-striped table-hover align-middle">
        <?php /* scope="col" tells screen readers each header names the column below it */ ?>
        <thead>
          <tr><th scope="col">Book</th><th scope="col">Borrowed On</th><th scope="col">Due Date</th><th scope="col">Returned On</th><th scope="col">Status</th><th scope="col">Action</th></tr>
        </thead>
        <tbody>
          <?php /* Repeat the row below once per loan; $loan holds the current one. */ ?>
          <?php foreach ($loans as $loan): ?>
            <?php
              /* Work out the status shown on the page (the database isn't changed):
                 returned if it has a return_date, overdue if the due date has passed, otherwise active. */
              if ($loan['return_date'] !== null) {
                  $status = 'returned';
              } elseif ($loan['due_date'] < $today) {
                  $status = 'overdue';
              } else {
                  $status = 'active';
              }
            ?>
            <tr>
              <?php /* Title links to that book's page; (int) and htmlspecialchars() stop bad data from breaking the link or page. */ ?>
              <td><a href="book.php?book_id=<?= (int) $loan['book_id'] ?>"><?= htmlspecialchars($loan['title']) ?></a></td>
              <?php /* The database gives "2026-11-16"; strtotime() + date('M j, Y') make it readable: "Nov 16, 2026". */ ?>
              <td><?= date('M j, Y', strtotime($loan['checkout_date'])) ?></td>
              <td><?= date('M j, Y', strtotime($loan['due_date'])) ?></td>
              <?php /* Not returned yet = show a dash instead of a date */ ?>
              <td><?= $loan['return_date'] ? date('M j, Y', strtotime($loan['return_date'])) : '-' ?></td>
              <td>
                <?php /* $badge (top of file) turns the status into a Bootstrap colour, e.g. 'overdue' -> text-bg-danger = red. */ ?>
                <span class="badge text-bg-<?= $badge[$status] ?>"><?= ucfirst($status) ?></span>
              </td>
              <td>
                <?php if ($status !== 'returned'): // active and overdue books can both be returned ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; choosing Cancel there stops the form from sending. */ ?>
                  <form method="post" onsubmit="return confirm('Return this book?');">
                    <?php /* Hidden input tells section 1 (top of file) WHICH loan to return. */ ?>
                    <input type="hidden" name="loan_id" value="<?= (int) $loan['loan_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Return</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($loans)): // if the user has no loans, show a message ?>
            <tr><td colspan="6" class="text-center">
              You have no borrowed books. <a href="books.php">Browse books</a>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
