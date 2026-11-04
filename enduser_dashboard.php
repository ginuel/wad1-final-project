<?php
session_start();
require_once 'db.php';
require_once 'helpers.php';

// Auth check: Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$role      = $_SESSION['role'];

// --- HANDLE RESERVATION ACTION (For Students/Teachers) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    $book_id = (int)$_POST['book_id'];

    // 1. Check if book has available copies
    $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $book_id]);
    $book = $stmt->fetch();

    if ($book && $book['available_copies'] > 0) {
        // Calculate due date (7 days from now)
        $dueDate = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Insert reservation record
        $stmtInsert = $pdo->prepare("INSERT INTO borrow_records (user_id, book_id, due_date, status) VALUES (:user_id, :book_id, :due_date, 'Pending')");
        $stmtInsert->execute([
            ':user_id'  => $user_id,
            ':book_id'  => $book_id,
            ':due_date' => $dueDate
        ]);

        // Decrement available copies count
        $stmtUpdate = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = :book_id");
        $stmtUpdate->execute([':book_id' => $book_id]);

        header("Location: enduser_dashboard.php?msg=reserved");
        exit;
    }
}

// --- HANDLE APPROVE / REJECT / RETURN ACTIONS (For Librarians) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject', 'return'])) {
    if ($role === 'librarian') {
        $record_id = (int)$_POST['record_id'];
        $action    = $_POST['action'];

        $stmtRec = $pdo->prepare("SELECT book_id, status FROM borrow_records WHERE record_id = :record_id");
        $stmtRec->execute([':record_id' => $record_id]);
        $rec = $stmtRec->fetch();

        $msg = 'status_updated';

        if ($rec) {
            if ($action === 'approve' && $rec['status'] === 'Pending') {
                // Approving starts the loan: the due date counts from today
                $due = date('Y-m-d H:i:s', strtotime('+' . LOAN_DAYS . ' days'));
                $pdo->prepare("UPDATE borrow_records SET status = 'Approved', due_date = :due WHERE record_id = :record_id")
                    ->execute([':due' => $due, ':record_id' => $record_id]);

            } elseif ($action === 'reject' && $rec['status'] === 'Pending') {
                $pdo->prepare("UPDATE borrow_records SET status = 'Rejected' WHERE record_id = :record_id")
                    ->execute([':record_id' => $record_id]);
                // Restore the held copy
                $pdo->prepare("UPDATE books SET available_copies = MIN(available_copies + 1, total_copies) WHERE book_id = :book_id")
                    ->execute([':book_id' => $rec['book_id']]);

            } elseif ($action === 'return' && $rec['status'] === 'Approved') {
                $pdo->prepare("UPDATE borrow_records SET status = 'Returned', return_date = :now WHERE record_id = :record_id")
                    ->execute([':now' => date('Y-m-d H:i:s'), ':record_id' => $record_id]);
                // Put the copy back on the shelf
                $pdo->prepare("UPDATE books SET available_copies = MIN(available_copies + 1, total_copies) WHERE book_id = :book_id")
                    ->execute([':book_id' => $rec['book_id']]);
                $msg = 'returned';
            }
        }

        header("Location: enduser_dashboard.php?msg=" . $msg);
        exit;
    }
}

// --- HANDLE RENEWAL (Students/Teachers renew their own loans) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'renew' && $role !== 'librarian') {
    $record_id = (int)$_POST['record_id'];

    $stmtRec = $pdo->prepare("SELECT * FROM borrow_records WHERE record_id = :record_id AND user_id = :user_id");
    $stmtRec->execute([':record_id' => $record_id, ':user_id' => $user_id]);
    $rec = $stmtRec->fetch();

    $msg = 'renew_failed';

    if ($rec && $rec['status'] === 'Approved') {
        if (isOverdue($rec)) {
            $msg = 'renew_overdue';
        } elseif ((int)$rec['renewal_count'] >= MAX_RENEWALS) {
            $msg = 'renew_limit';
        } else {
            $newDue = date('Y-m-d H:i:s', strtotime($rec['due_date'] . ' +' . LOAN_DAYS . ' days'));
            $pdo->prepare("UPDATE borrow_records SET due_date = :due, renewal_count = renewal_count + 1 WHERE record_id = :record_id")
                ->execute([':due' => $newDue, ':record_id' => $record_id]);
            $msg = 'renewed';
        }
    }

    header("Location: enduser_dashboard.php?msg=" . $msg);
    exit;
}

// --- FETCH DATA FOR DASHBOARD VIEWS ---

// 1. Fetch catalog books for browsing/reserving
$books = $pdo->query("
    SELECT books.*, categories.name AS category_name 
    FROM books 
    LEFT JOIN categories ON books.category_id = categories.category_id 
    ORDER BY books.title ASC
")->fetchAll();

// 2. Fetch borrowing history/records
if ($role === 'librarian') {
    // Librarians see all reservation requests
    $stmtRecords = $pdo->query("
        SELECT borrow_records.*, users.username, books.title AS book_title, books.image_path AS book_image 
        FROM borrow_records 
        JOIN users ON borrow_records.user_id = users.user_id 
        JOIN books ON borrow_records.book_id = books.book_id 
        ORDER BY borrow_records.borrow_date DESC
    ");
    $records = $stmtRecords->fetchAll();
} else {
    // Students/Teachers only see their own reservation requests
    $stmtRecords = $pdo->prepare("
        SELECT borrow_records.*, books.title AS book_title, books.image_path AS book_image 
        FROM borrow_records 
        JOIN books ON borrow_records.book_id = books.book_id 
        WHERE borrow_records.user_id = :user_id 
        ORDER BY borrow_records.borrow_date DESC
    ");
    $stmtRecords->execute([':user_id' => $user_id]);
    $records = $stmtRecords->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="enduser_dashboard.php"><span class="brand-mark">📚</span> Campus Library</a>
            <nav class="nav-links">
                <a href="enduser_dashboard.php" class="active">Dashboard</a>
                <?php if ($_SESSION['role'] === 'librarian'): ?>
                    <a href="admin_books.php">Manage Books</a>
                    <a href="users.php">Users</a>
                <?php endif; ?>
                <a href="items.php">Browse Books</a>
                <a href="logout.php">Logout</a>
            </nav>
            <div class="user-chip">
                <?= htmlspecialchars($_SESSION['username']) ?>
                <span class="role-tag"><?= ucfirst(htmlspecialchars($_SESSION['role'])) ?></span>
            </div>
        </div>
    </header>

    <?php
        $pendingCount = count(array_filter($records, fn($r) => $r['status'] === 'Pending'));
        $onLoanCount  = count(array_filter($records, fn($r) => $r['status'] === 'Approved'));
        $overdueCount = count(array_filter($records, 'isOverdue'));
    ?>

    <main class="container">
        <div class="page-title">
            <h1>Welcome, <?= htmlspecialchars($username) ?></h1>
            <p><?= $role === 'librarian' ? 'Review reservation requests and keep an eye on the catalog.' : 'Browse the catalog and reserve the books you need.' ?></p>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reserved'): ?>
            <div class="alert alert-success">Book reserved. The librarian will review your request.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
            <div class="alert alert-success">Reservation status updated.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'returned'): ?>
            <div class="alert alert-success">Book marked as returned. The copy is available again.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'renewed'): ?>
            <div class="alert alert-success">Loan renewed. Your new due date is shown below.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'renew_limit'): ?>
            <div class="alert alert-error">This loan has already been renewed <?= MAX_RENEWALS ?> times. Please return the book.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'renew_overdue'): ?>
            <div class="alert alert-error">Overdue loans can't be renewed. Please return the book to the library.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'renew_failed'): ?>
            <div class="alert alert-error">That loan can't be renewed.</div>
        <?php endif; ?>

        <div class="stats">
            <?php if ($role === 'librarian'): ?>
                <div class="stat"><span class="stat-number"><?= $pendingCount ?></span><span class="stat-label">Requests waiting for review</span></div>
                <div class="stat"><span class="stat-number"><?= $onLoanCount ?></span><span class="stat-label">Books on loan</span></div>
                <div class="stat"><span class="stat-number"><?= $overdueCount ?></span><span class="stat-label">Overdue loans</span></div>
                <div class="stat"><span class="stat-number"><?= count($books) ?></span><span class="stat-label">Titles in the catalog</span></div>
            <?php else: ?>
                <div class="stat"><span class="stat-number"><?= count($records) ?></span><span class="stat-label">My reservations</span></div>
                <div class="stat"><span class="stat-number"><?= $pendingCount ?></span><span class="stat-label">Waiting for approval</span></div>
                <div class="stat"><span class="stat-number"><?= $onLoanCount ?></span><span class="stat-label">Books on loan</span></div>
                <div class="stat"><span class="stat-number"><?= $overdueCount ?></span><span class="stat-label">Overdue loans</span></div>
            <?php endif; ?>
        </div>

        <?php if ($role === 'librarian'): ?>

            <h2 class="section-title">Reservation requests</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Reserved on</th>
                            <th>Due date</th>
                            <th>Returned on</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8" class="empty">No reservation requests yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><?= $r['record_id'] ?></td>
                                    <td><?= htmlspecialchars($r['username']) ?></td>
                                    <td><div class="book-cell"><?= coverImage($r['book_image'] ?? null, $r['book_title']) ?><span class="cell-title"><?= htmlspecialchars($r['book_title']) ?></span></div></td>
                                    <td><?= htmlspecialchars(substr((string)$r['borrow_date'], 0, 10)) ?></td>
                                    <td><?= shortDate($r['due_date']) ?></td>
                                    <td><?= shortDate($r['return_date']) ?></td>
                                    <td><?= statusBadge($r) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'Pending'): ?>
                                            <form method="POST" action="enduser_dashboard.php" class="actions">
                                                <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-navy">Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                            </form>
                                        <?php elseif ($r['status'] === 'Approved'): ?>
                                            <form method="POST" action="enduser_dashboard.php" class="actions">
                                                <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                                                <button type="submit" name="action" value="return" class="btn btn-sm" onclick="return confirm('Mark this book as returned?');">Mark returned</button>
                                            </form>
                                        <?php else: ?>
                                            Closed
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <h2 class="section-title">My reservations</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Reserved on</th>
                            <th>Due date</th>
                            <th>Returned on</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="6" class="empty">You have not reserved any books yet. Pick one from the catalog below.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><div class="book-cell"><?= coverImage($r['book_image'] ?? null, $r['book_title']) ?><span class="cell-title"><?= htmlspecialchars($r['book_title']) ?></span></div></td>
                                    <td><?= htmlspecialchars(substr((string)$r['borrow_date'], 0, 10)) ?></td>
                                    <td><?= shortDate($r['due_date']) ?></td>
                                    <td><?= shortDate($r['return_date']) ?></td>
                                    <td><?= statusBadge($r) ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'Approved'): ?>
                                            <?php if (isOverdue($r)): ?>
                                                <span class="hint-text">Return this book to the library.</span>
                                            <?php elseif ((int)$r['renewal_count'] >= MAX_RENEWALS): ?>
                                                <span class="hint-text">Renewed <?= MAX_RENEWALS ?> of <?= MAX_RENEWALS ?>. Return by the due date.</span>
                                            <?php else: ?>
                                                <form method="POST" action="enduser_dashboard.php" class="actions">
                                                    <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                                                    <button type="submit" name="action" value="renew" class="btn btn-sm btn-navy">Renew +<?= LOAN_DAYS ?> days</button>
                                                    <span class="hint-text">Renewed <?= (int)$r['renewal_count'] ?> of <?= MAX_RENEWALS ?></span>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>

        <h2 class="section-title">Book catalog</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Available</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): ?>
                        <tr>
                            <td><div class="book-cell"><?= coverImage($b['image_path'] ?? null, $b['title']) ?><span class="cell-title"><?= htmlspecialchars($b['title']) ?></span></div></td>
                            <td><?= htmlspecialchars($b['author']) ?></td>
                            <td><?= htmlspecialchars($b['category_name'] ?? 'Uncategorized') ?></td>
                            <td><span class="stock <?= $b['available_copies'] > 0 ? '' : 'stock-out' ?>"><?= $b['available_copies'] ?> of <?= $b['total_copies'] ?></span></td>
                            <td>
                                <?php if ($b['available_copies'] > 0): ?>
                                    <form method="POST" action="enduser_dashboard.php">
                                        <input type="hidden" name="book_id" value="<?= $b['book_id'] ?>">
                                        <button type="submit" name="action" value="reserve" class="btn btn-sm">Reserve</button>
                                    </form>
                                <?php else: ?>
                                    <span class="stock stock-out">Out of stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <footer class="site-footer">Campus Library &copy; <?= date('Y') ?></footer>

</body>
</html>