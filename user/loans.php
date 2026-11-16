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
