<?php
session_start();
require_once 'db.php';
require_once 'helpers.php';

$loggedIn = isset($_SESSION['user_id']);

// Fetching books with their category name (READ operation)
$query = "
    SELECT
        books.*,
        categories.name AS category_name
    FROM books
    LEFT JOIN categories ON books.category_id = categories.category_id
    ORDER BY books.title ASC
";

$stmt = $pdo->query($query);
$books = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Browse Books - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="<?= $loggedIn ? 'enduser_dashboard.php' : 'login.php' ?>"><span class="brand-mark">📚</span> Campus Library</a>
            <nav class="nav-links">
                <?php if ($loggedIn): ?>
                    <a href="enduser_dashboard.php">Dashboard</a>
                    <?php if ($_SESSION['role'] === 'librarian'): ?>
                        <a href="admin_books.php">Manage Books</a>
                        <a href="users.php">Users</a>
                    <?php endif; ?>
                    <a href="items.php" class="active">Browse Books</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="items.php" class="active">Browse Books</a>
                    <a href="login.php">Log in</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </nav>
            <?php if ($loggedIn): ?>
                <div class="user-chip">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                    <span class="role-tag"><?= ucfirst(htmlspecialchars($_SESSION['role'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">
        <div class="page-title">
            <h1>Browse books</h1>
            <p><?= count($books) ?> title<?= count($books) === 1 ? '' : 's' ?> in the library.<?= $loggedIn ? '' : ' <a href="login.php">Log in</a> to reserve a book.' ?></p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="4" class="empty">No books in the catalog yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><div class="book-cell"><?= coverImage($book['image_path'] ?? null, $book['title']) ?><span class="cell-title"><?= htmlspecialchars($book['title']) ?></span></div></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></td>
                                <td><span class="stock <?= $book['available_copies'] > 0 ? '' : 'stock-out' ?>"><?= $book['available_copies'] > 0 ? $book['available_copies'] . ' available' : 'Out of stock' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer class="site-footer">Campus Library &copy; <?= date('Y') ?></footer>
</body>
</html>