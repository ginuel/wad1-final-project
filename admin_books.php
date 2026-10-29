<?php
session_start();
require_once 'db.php';

// Auth check: Ensure user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: enduser_dashboard.php");
    exit;
}

$isbnError = '';
$copiesError = '';
$editBook = null;

// Helper function to validate ISBN format (10 or 13 digits, allowing hyphens)
function isValidISBN($isbn) {
    $cleanIsbn = str_replace(['-', ' '], '', $isbn);
    return preg_match('/^(?:\d{9}[\dX]|\d{13})$/i', $cleanIsbn);
}

// --- 1. CREATE BOOK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title            = trim($_POST['title']);
    $author           = trim($_POST['author']);
    $isbn             = trim($_POST['isbn']);
    $category_id      = (int)$_POST['category_id'];
    $total_copies     = (int)$_POST['total_copies'];
    $available_copies = $total_copies; // Automatically matches total on creation

    $hasError = false;

    if (!isValidISBN($isbn)) {
        $isbnError = "Invalid ISBN format! Please enter a valid 10-digit or 13-digit ISBN.";
        $hasError = true;
    }

    if ($hasError) {
        $editBook = [
            'title'            => $title,
            'author'           => $author,
            'isbn'             => $isbn,
            'category_id'      => $category_id,
            'total_copies'     => $total_copies,
            'available_copies' => $available_copies
        ];
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, category_id, total_copies, available_copies) VALUES (:title, :author, :isbn, :category_id, :total_copies, :available_copies)");
            $stmt->execute([
                ':title'            => $title,
                ':author'           => $author,
                ':isbn'             => $isbn,
                ':category_id'      => $category_id,
                ':total_copies'     => $total_copies,
                ':available_copies' => $available_copies
            ]);
            header("Location: admin_books.php?msg=added");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $isbnError = "This ISBN is already registered to another book.";
            } else {
                $isbnError = "Database error: " . $e->getMessage();
            }
            $editBook = [
                'title'            => $title,
                'author'           => $author,
                'isbn'             => $isbn,
                'category_id'      => $category_id,
                'total_copies'     => $total_copies,
                'available_copies' => $available_copies
            ];
        }
    }
}

// --- 2. UPDATE BOOK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $book_id          = (int)$_POST['book_id'];
    $title            = trim($_POST['title']);
    $author           = trim($_POST['author']);
    $isbn             = trim($_POST['isbn']);
    $category_id      = (int)$_POST['category_id'];
    $total_copies     = (int)$_POST['total_copies'];
    $available_copies = (int)$_POST['available_copies'];

    $hasError = false;

    // Validation 1: ISBN
    if (!isValidISBN($isbn)) {
        $isbnError = "Invalid ISBN format! Please enter a valid 10-digit or 13-digit ISBN.";
        $hasError = true;
    }

    // Validation 2: Available copies cannot exceed Total copies
    if ($available_copies > $total_copies) {
        $copiesError = "Available copies cannot be greater than total copies ($total_copies).";
        $hasError = true;
    }

    if ($hasError) {
        $editBook = [
            'book_id'          => $book_id,
            'title'            => $title,
            'author'           => $author,
            'isbn'             => $isbn,
            'category_id'      => $category_id,
            'total_copies'     => $total_copies,
            'available_copies' => $available_copies
        ];
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE books SET title = :title, author = :author, isbn = :isbn, category_id = :category_id, total_copies = :total_copies, available_copies = :available_copies WHERE book_id = :book_id");
            $stmt->execute([
                ':title'            => $title,
                ':author'           => $author,
                ':isbn'             => $isbn,
                ':category_id'      => $category_id,
                ':total_copies'     => $total_copies,
                ':available_copies' => $available_copies,
                ':book_id'          => $book_id
            ]);
            header("Location: admin_books.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $isbnError = "This ISBN is already assigned to another book.";
            } else {
                $isbnError = "Database error: " . $e->getMessage();
            }
            $editBook = [
                'book_id'          => $book_id,
                'title'            => $title,
                'author'           => $author,
                'isbn'             => $isbn,
                'category_id'      => $category_id,
                'total_copies'     => $total_copies,
                'available_copies' => $available_copies
            ];
        }
    }
}

// --- DELETE AND FETCH LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $book_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $book_id]);
    header("Location: admin_books.php?msg=deleted");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $book_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $book_id]);
    $editBook = $stmt->fetch();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$query = "
    SELECT books.*, categories.name AS category_name 
    FROM books 
    LEFT JOIN categories ON books.category_id = categories.category_id
    ORDER BY books.book_id DESC
";
$books = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <p>
            Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (<?= ucfirst(htmlspecialchars($_SESSION['role'])) ?>) | 
            <a href="enduser_dashboard.php">Dashboard</a> | 
            <?php if ($_SESSION['role'] === 'librarian'): ?>
                <a href="admin_books.php">Manage Books (CRUD)</a> | 
                <a href="users.php">View Registered Users</a> | 
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </p>
    </header>

    <hr>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <p style="color: green;"><strong>Success:</strong> New book successfully added.</p>
        <?php elseif ($_GET['msg'] === 'updated'): ?>
            <p style="color: green;"><strong>Success:</strong> Book details updated successfully.</p>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <p style="color: green;"><strong>Success:</strong> Book record removed.</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2><?= (isset($_POST['action']) && $_POST['action'] === 'update') || (isset($_GET['action']) && $_GET['action'] === 'edit') ? "Edit Book" : "Add New Book" ?></h2>

    <form method="POST" action="admin_books.php">
        <input type="hidden" name="action" value="<?= isset($editBook['book_id']) ? 'update' : 'create' ?>">
        <?php if (isset($editBook['book_id'])): ?>
            <input type="hidden" name="book_id" value="<?= $editBook['book_id'] ?>">
        <?php endif; ?>

        <label>Title:</label><br>
        <input type="text" name="title" value="<?= htmlspecialchars($editBook['title'] ?? '') ?>" required><br><br>

        <label>Author:</label><br>
        <input type="text" name="author" value="<?= htmlspecialchars($editBook['author'] ?? '') ?>" required><br><br>

        <label>ISBN (10 or 13 digits):</label><br>
        <input type="text" 
               name="isbn" 
               value="<?= htmlspecialchars($editBook['isbn'] ?? '') ?>" 
               pattern="^(?:\d{10}|\d{13}|[\d-]{13,17})$" 
               placeholder="e.g. 978-0-307-47427-8" 
               title="Please enter a valid 10 or 13 digit ISBN" 
               required><br>
        <?php if ($isbnError): ?>
            <small style="color: red; display: block; margin-top: 4px;"><?= htmlspecialchars($isbnError) ?></small>
        <?php endif; ?>
        <br>

        <label>Category:</label><br>
        <select name="category_id" required>
            <option value="">Select Category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= (isset($editBook) && $editBook['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Total Copies:</label><br>
        <input type="number" name="total_copies" value="<?= htmlspecialchars($editBook['total_copies'] ?? '1') ?>" min="1" required><br><br>

        <?php if (isset($editBook['book_id'])): ?>
            <label>Available Copies:</label><br>
            <input type="number" name="available_copies" value="<?= htmlspecialchars($editBook['available_copies']) ?>" min="0" required><br>
            <?php if ($copiesError): ?>
                <small style="color: red; display: block; margin-top: 4px;"><?= htmlspecialchars($copiesError) ?></small>
            <?php endif; ?>
            <br>
        <?php endif; ?>

        <button type="submit"><?= isset($editBook['book_id']) ? "Update Book" : "Add Book" ?></button>
        <?php if (isset($editBook)): ?>
            <a href="admin_books.php">Cancel</a>
        <?php endif; ?>
    </form>

    <hr>

    <h2>Book Catalog List</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Author</th>
                <th>ISBN</th>
                <th>Category</th>
                <th>Total Copies</th>
                <th>Available Copies</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $b): ?>
                <tr>
                    <td><?= $b['book_id'] ?></td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td><?= htmlspecialchars($b['author']) ?></td>
                    <td><?= htmlspecialchars($b['isbn']) ?></td>
                    <td><?= htmlspecialchars($b['category_name'] ?? 'Uncategorized') ?></td>
                    <td><?= $b['total_copies'] ?></td>
                    <td><?= $b['available_copies'] ?></td>
                    <td>
                        <a href="admin_books.php?action=edit&id=<?= $b['book_id'] ?>">Edit</a> | 
                        <a href="admin_books.php?action=delete&id=<?= $b['book_id'] ?>" onclick="return confirm('Are you sure you want to delete this book?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>