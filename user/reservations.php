<?php
/**
 * My Reservations Page (Front End Users)
 * Lists the logged-in user's reservations, with a Cancel button for active ones.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection (same as books.php) */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

$page_name = "reservations";
$user_id   = $_SESSION['user_id'];
$message   = '';

/* ------------------------------------------------------------------
   1. CANCEL BUTTON CLICKED?
   Each Cancel button is a small POST form that sends the reservation_id.
   We don't delete the row, we just change its status to 'cancelled' (keeps history).
   "AND user_id = ?" makes sure users can only cancel THEIR OWN reservations.
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = (int) ($_POST['reservation_id'] ?? 0);

    $cancel = $db_connection->prepare("
        UPDATE Reservations SET reservation_status = 'cancelled'
        WHERE reservation_id = ? AND user_id = ? AND reservation_status = 'active'");
    $cancel->bind_param("ii", $reservation_id, $user_id);
    $cancel->execute();

    /* affected_rows = how many rows were changed (1 if it worked, 0 if not) */
    if ($cancel->affected_rows > 0) {
        $message = 'Reservation cancelled.';
    }
    $cancel->close();
}

/* ------------------------------------------------------------------
   2. GET THIS USER'S RESERVATIONS (newest first), with the book title
   Only show reservations that expired less than 30 days ago (or haven't expired yet).
   Active ones always show, since they haven't expired. Old cancelled/expired ones
   are hidden so the list stays tidy (the rows are NOT deleted).
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT r.reservation_id, r.reservation_date, r.expire_date, r.reservation_status,
           b.book_id, b.title
    FROM Reservations r
    INNER JOIN Books b ON r.book_id = b.book_id
    WHERE r.user_id = ?
      AND r.expire_date >= CURDATE() - INTERVAL 30 DAY
    ORDER BY r.reservation_date DESC, r.reservation_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* Bootstrap badge colour for each status (green, grey, yellow) */
$badge = ['active' => 'success', 'cancelled' => 'secondary', 'expired' => 'warning'];
$today = date('Y-m-d'); // e.g. 2026-11-11, used to spot expired reservations
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>My Reservations - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main class="container pb-5">
      <h2>My Reservations</h2>
      <p class="lead">Books you have reserved. Reservations last 7 days.</p>
      <hr class="mb-4">

      <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
      <?php endif; ?>

      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr><th>Book</th><th>Reserved On</th><th>Expires</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php /* Repeat the row below once per reservation; $r holds the current one (short for "reservation"). */ ?>
          <?php foreach ($reservations as $r): ?>
            <?php
              /* Copy the status into $status so we can relabel it for display without changing the database.
                 An 'active' reservation past its expire date is shown as 'expired' ("2026-11-10" < "2026-11-11" works as text). */
              $status = $r['reservation_status'];
              if ($status === 'active' && $r['expire_date'] < $today) {
                  $status = 'expired';
              }
            ?>
            <tr>
              <?php /* Title links to that book's page; (int) and htmlspecialchars() stop bad data from breaking the link or page. */ ?>
              <td><a href="book.php?book_id=<?= (int) $r['book_id'] ?>"><?= htmlspecialchars($r['title']) ?></a></td>
              <?php /* The database gives "2026-11-11"; strtotime() + date('M j, Y') make it readable: "Nov 11, 2026". */ ?>
              <td><?= date('M j, Y', strtotime($r['reservation_date'])) ?></td>
              <td><?= date('M j, Y', strtotime($r['expire_date'])) ?></td>
              <td>
                <?php /* $badge (top of file) turns the status into a Bootstrap colour, e.g. 'active' -> text-bg-success = green. */ ?>
                <span class="badge text-bg-<?= $badge[$status] ?? 'secondary' ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
              </td>
              <td>
                <?php if ($status === 'active'): // only active reservations can be cancelled ?>
                  <?php /* confirm() shows an "Are you sure?" pop-up; choosing Cancel there stops the form from sending. */ ?>
                  <form method="post" onsubmit="return confirm('Cancel this reservation?');">
                    <?php /* Hidden input tells section 1 (top of file) WHICH reservation to cancel. */ ?>
                    <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (count($reservations) >= 3): /* If user is trying to reserve more than 3 books, 
          show a warning message*/  ?>
            <tr>
              <td colspan="5" class="text-center">
                <div class="alert alert-warning" role="alert">
                  You have reserved more than 3 books. Please manage your reservations.
                </div>
              </td>
            </tr>
          <?php endif; ?>

          <?php if (empty($reservations)): // if the user's reservations list is empty, show a message ?>
            <tr><td colspan="5" class="text-center">
              You have no reservations yet. <a href="books.php">Browse books</a>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
