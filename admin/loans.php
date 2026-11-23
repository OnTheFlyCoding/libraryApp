<?php
/**
 * View All Loans (Administrators)
 * Lists every loan from every user, with a search box and a status filter.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 while debugging

/* session.php = session + login check + $db_connection */
include_once (realpath(dirname(__FILE__, 2) . '/php/session.php'));

/* Only admins (role 1) can see this page; everyone else is sent to logout */
include_once (ROOT_SRC_PATH . '/check_admin.php');

$page_name = "adminLoans";

/* The search box and status dropdown use method="get", so their values come from the URL */
$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? ''; // '' = show all, or 'active' / 'overdue' / 'returned'
$like   = "%" . $search . "%";

/* ------------------------------------------------------------------
   1. GET ALL LOANS (matching the search), with the member and book info
   Loans -> BookCopies -> Books gives the title (a loan points to a COPY).
   Loans -> Users -> Contacts / Credentials gives the member's name and username.
   ORDER BY: not-returned loans first, then the soonest due date first.
   ------------------------------------------------------------------ */
$stmt = $db_connection->prepare("
    SELECT l.loan_id, l.checkout_date, l.due_date, l.return_date,
           b.title, c.barcode,
           ct.first_name, ct.last_name, cr.username
    FROM Loans l
    INNER JOIN BookCopies  c  ON l.copy_id    = c.copy_id
    INNER JOIN Books       b  ON c.book_id    = b.book_id
    INNER JOIN Users       u  ON l.user_id    = u.user_id
    INNER JOIN Contacts    ct ON u.contact_id = ct.contact_id
    INNER JOIN Credentials cr ON u.user_id    = cr.user_id
    WHERE b.title LIKE ? OR cr.username LIKE ? OR CONCAT(ct.first_name, ' ', ct.last_name) LIKE ?
    ORDER BY (l.return_date IS NULL) DESC, l.due_date ASC");
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$loans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$db_connection->close();

/* ------------------------------------------------------------------
   2. WORK OUT EACH LOAN'S STATUS, then keep only the ones matching the filter
   The status isn't stored as "overdue" in the database, so we work it out here
   (same rules as user/loans.php). We also count each status for the summary.
   ------------------------------------------------------------------ */
$today  = date('Y-m-d');
$counts = ['active' => 0, 'overdue' => 0, 'returned' => 0];
$shown  = []; // the loans that will appear in the table

foreach ($loans as $loan) {
    if ($loan['return_date'] !== null) {
        $loan['status'] = 'returned';
    } elseif ($loan['due_date'] < $today) {
        $loan['status'] = 'overdue';
    } else {
        $loan['status'] = 'active';
    }
    $counts[$loan['status']]++;

    // Keep it if no filter is picked, or if it matches the picked status
    if ($filter === '' || $loan['status'] === $filter) {
        $shown[] = $loan;
    }
}

/* Bootstrap badge colour for each status (green, red, grey) */
$badge = ['active' => 'success', 'overdue' => 'danger', 'returned' => 'secondary'];
?>
<!doctype html>
<html lang="en">
  <head>
    <?php include_once (ROOT_PATH . '/include/head.php'); // loads meta tags, fonts and Bootstrap CSS ?>
    <title>All Loans - Library Prototype</title>
  </head>
  <body class="<?= $page_name ?>">
    <?php include_once (ROOT_PATH . '/include/header.php'); ?>

    <main class="container pb-5">
      <h2>All Loans</h2>
      <p class="lead">
        Every book borrowed by every user.
        <span class="badge text-bg-success"><?= $counts['active'] ?> active</span>
        <span class="badge text-bg-danger"><?= $counts['overdue'] ?> overdue</span>
        <span class="badge text-bg-secondary"><?= $counts['returned'] ?> returned</span>
      </p>
      <hr class="mb-4">

      <?php /* Search + status filter in one GET form, so both stay in the URL together */ ?>
      <form method="get" class="d-flex gap-2 mb-4">
        <input type="text" name="search" class="form-control" placeholder="Search by book title, member name or username"
               value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-select w-auto">
          <option value="">All statuses</option>
          <?php foreach (['active', 'overdue', 'returned'] as $s): // 'selected' keeps the picked option after the page reloads ?>
            <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
        <?php if ($search !== '' || $filter !== ''): // only show "Clear" while searching/filtering ?>
          <a href="loans.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>

      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr><th>Member</th><th>Book</th><th>Copy</th><th>Borrowed On</th><th>Due Date</th><th>Returned On</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($shown as $loan): ?>
            <tr>
              <td>
                <?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?>
                <div class="small text-muted"><?= htmlspecialchars($loan['username']) ?></div>
              </td>
              <td><?= htmlspecialchars($loan['title']) ?></td>
              <?php /* The barcode tells the librarian WHICH physical copy is out */ ?>
              <td><?= htmlspecialchars($loan['barcode']) ?></td>
              <td><?= date('M j, Y', strtotime($loan['checkout_date'])) ?></td>
              <td><?= date('M j, Y', strtotime($loan['due_date'])) ?></td>
              <td><?= $loan['return_date'] ? date('M j, Y', strtotime($loan['return_date'])) : '-' ?></td>
              <td><span class="badge text-bg-<?= $badge[$loan['status']] ?>"><?= ucfirst($loan['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($shown)): // nothing to show (no loans yet, or nothing matches) ?>
            <tr><td colspan="7" class="text-center">No loans found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </main>

    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
