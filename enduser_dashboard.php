<?php
session_start();
require_once 'db.php';

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

// --- HANDLE APPROVE / REJECT ACTIONS (For Librarians) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    if ($role === 'librarian') {
        $record_id = (int)$_POST['record_id'];
        $newStatus = ($_POST['action'] === 'approve') ? 'Approved' : 'Rejected';

        // Update record status
        $stmt = $pdo->prepare("UPDATE borrow_records SET status = :status WHERE record_id = :record_id");
        $stmt->execute([':status' => $newStatus, ':record_id' => $record_id]);

        // If rejected, restore book's available copy count
        if ($newStatus === 'Rejected') {
            $stmtBook = $pdo->prepare("SELECT book_id FROM borrow_records WHERE record_id = :record_id");
            $stmtBook->execute([':record_id' => $record_id]);
            $rec = $stmtBook->fetch();
            if ($rec) {
                $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = :book_id")->execute([':book_id' => $rec['book_id']]);
            }
        }

        header("Location: enduser_dashboard.php?msg=status_updated");
        exit;
    }
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
        SELECT borrow_records.*, users.username, books.title AS book_title 
        FROM borrow_records 
        JOIN users ON borrow_records.user_id = users.user_id 
        JOIN books ON borrow_records.book_id = books.book_id 
        ORDER BY borrow_records.borrow_date DESC
    ");
    $records = $stmtRecords->fetchAll();
} else {
    // Students/Teachers only see their own reservation requests
    $stmtRecords = $pdo->prepare("
        SELECT borrow_records.*, books.title AS book_title 
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
    <title>End-User Dashboard - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <h1>Campus Library Portal</h1>
        <p>Logged in as: <strong><?= htmlspecialchars($username) ?></strong> (<?= ucfirst(htmlspecialchars($role)) ?>)</p>
        <nav>
            <a href="enduser_dashboard.php">Dashboard Home</a> | 
            <?php if ($role === 'librarian'): ?>
                <a href="admin_books.php">Manage Book Catalog (CRUD)</a> | 
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <hr>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'reserved'): ?>
        <p style="color: green;"><strong>Success:</strong> Book reservation submitted!</p>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
        <p style="color: green;"><strong>Success:</strong> Reservation status updated!</p>
    <?php endif; ?>

    <!-- SECTION 1: RESERVATION REQUESTS -->
    <?php if ($role === 'librarian'): ?>
        <h2>All Reservation Requests (Librarian Approval)</h2>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>User</th>
                    <th>Book Title</th>
                    <th>Date Reserved</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7">No reservation records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= $r['record_id'] ?></td>
                            <td><?= htmlspecialchars($r['username']) ?></td>
                            <td><?= htmlspecialchars($r['book_title']) ?></td>
                            <td><?= $r['borrow_date'] ?></td>
                            <td><?= $r['due_date'] ?></td>
                            <td><strong><?= $r['status'] ?></strong></td>
                            <td>
                                <?php if ($r['status'] === 'Pending'): ?>
                                    <form method="POST" action="enduser_dashboard.php" style="display:inline;">
                                        <input type="hidden" name="record_id" value="<?= $r['record_id'] ?>">
                                        <button type="submit" name="action" value="approve">Approve</button>
                                        <button type="submit" name="action" value="reject">Reject</button>
                                    </form>
                                <?php else: ?>
                                    Processed
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>

        <h2>My Reservations</h2>
        <table border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Reservation Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="4">You have no active reservations.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['book_title']) ?></td>
                            <td><?= $r['borrow_date'] ?></td>
                            <td><?= $r['due_date'] ?></td>
                            <td><strong><?= $r['status'] ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php endif; ?>

    <hr>

    <!-- SECTION 2: BOOK CATALOG -->
    <h2>Library Book Catalog</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Title</th>
                <th>Author</th>
                <th>Category</th>
                <th>Available Copies</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td><?= htmlspecialchars($b['author']) ?></td>
                    <td><?= htmlspecialchars($b['category_name'] ?? 'Uncategorized') ?></td>
                    <td><?= $b['available_copies'] ?> / <?= $b['total_copies'] ?></td>
                    <td>
                        <?php if ($b['available_copies'] > 0): ?>
                            <form method="POST" action="enduser_dashboard.php">
                                <input type="hidden" name="book_id" value="<?= $b['book_id'] ?>">
                                <button type="submit" name="action" value="reserve">Reserve Book</button>
                            </form>
                        <?php else: ?>
                            <span style="color: red;">Out of Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>