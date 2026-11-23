<?php
/**
 * Pagination Bar (shared by user/books.php, admin/books.php, admin/loans.php)
 * Shows: « Previous  1  2  3  Next »  under a table.
 *
 * The page that includes this must set two variables first:
 *   $page        = the page number being shown right now
 *   $total_pages = how many pages there are in total
 */

/* Only 1 page? Then there's nothing to click, so show no bar at all. */
if ($total_pages <= 1) {
    return; // return here = stop this include file and go back to the page
}

/* Builds the link for page $n, KEEPING the current search/filter in the URL.
   $_GET holds the current URL values (e.g. search=orwell); we copy them and swap
   in the new page number, so going to page 2 doesn't lose the search. */
function page_link($n) {
    return '?' . http_build_query(array_merge($_GET, ['page' => $n]));
}
?>
<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php /* "disabled" greys out Previous on page 1 (nowhere to go back to).
             tabindex="-1" + aria-disabled also skip it for keyboard and screen reader users. */ ?>
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= htmlspecialchars(page_link($page - 1)) ?>"
         <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>&laquo; Previous</a>
    </li>

    <?php for ($n = 1; $n <= $total_pages; $n++): // one numbered button per page; "active" + aria-current mark the current one ?>
      <li class="page-item <?= $n === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= htmlspecialchars(page_link($n)) ?>" <?= $n === $page ? 'aria-current="page"' : '' ?>><?= $n ?></a>
      </li>
    <?php endfor; ?>

    <?php /* Same as Previous: greyed out and skipped on the last page */ ?>
    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= htmlspecialchars(page_link($page + 1)) ?>"
         <?= $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next &raquo;</a>
    </li>
  </ul>
</nav>
