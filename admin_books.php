<?php
session_start();
require_once 'db.php';
require_once 'helpers.php';

// Auth check: Ensure user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: enduser_dashboard.php");
    exit;
}

$isbnError = '';
$copiesError = '';
$imageError = '';
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

    // Cover image (optional)
    $image_path = null;
    if (!$hasError) {
        $image_path = saveCoverImage($_FILES['cover'] ?? [], $imageError);
        if ($imageError) {
            $hasError = true;
        }
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
            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, category_id, total_copies, available_copies, image_path) VALUES (:title, :author, :isbn, :category_id, :total_copies, :available_copies, :image_path)");
            $stmt->execute([
                ':title'            => $title,
                ':author'           => $author,
                ':isbn'             => $isbn,
                ':category_id'      => $category_id,
                ':total_copies'     => $total_copies,
                ':available_copies' => $available_copies,
                ':image_path'       => $image_path
            ]);
            header("Location: admin_books.php?msg=added");
            exit;
        } catch (PDOException $e) {
            deleteCoverImage($image_path); // don't leave an orphan file behind
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

    // Current cover (read from the database, not from the form)
    $curStmt = $pdo->prepare("SELECT image_path FROM books WHERE book_id = :book_id");
    $curStmt->execute([':book_id' => $book_id]);
    $currentImage = $curStmt->fetchColumn() ?: null;
    $newImage     = null;

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

    // Cover image (optional): new upload replaces the old one
    if (!$hasError) {
        $newImage = saveCoverImage($_FILES['cover'] ?? [], $imageError);
        if ($imageError) {
            $hasError = true;
        }
    }
    $image_path = $currentImage;
    if ($newImage) {
        $image_path = $newImage;
    } elseif (!empty($_POST['remove_image'])) {
        $image_path = null;
    }

    if ($hasError) {
        $editBook = [
            'book_id'          => $book_id,
            'image_path'       => $currentImage,
            'title'            => $title,
            'author'           => $author,
            'isbn'             => $isbn,
            'category_id'      => $category_id,
            'total_copies'     => $total_copies,
            'available_copies' => $available_copies
        ];
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE books SET title = :title, author = :author, isbn = :isbn, category_id = :category_id, total_copies = :total_copies, available_copies = :available_copies, image_path = :image_path WHERE book_id = :book_id");
            $stmt->execute([
                ':title'            => $title,
                ':author'           => $author,
                ':isbn'             => $isbn,
                ':category_id'      => $category_id,
                ':total_copies'     => $total_copies,
                ':available_copies' => $available_copies,
                ':image_path'       => $image_path,
                ':book_id'          => $book_id
            ]);
            if ($image_path !== $currentImage) {
                deleteCoverImage($currentImage); // old cover replaced or removed
            }
            header("Location: admin_books.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            deleteCoverImage($newImage); // update failed: discard the new upload
            if ($e->getCode() == 23000) {
                $isbnError = "This ISBN is already assigned to another book.";
            } else {
                $isbnError = "Database error: " . $e->getMessage();
            }
            $editBook = [
                'book_id'          => $book_id,
                'image_path'       => $currentImage,
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

// --- 3. CREATE CATEGORY ---
$categoryError = '';
$categoryName  = '';
$categoryDesc  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_category') {
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryDesc = trim($_POST['category_description'] ?? '');

    if ($categoryName === '') {
        $categoryError = "Please enter a category name.";
    } elseif (mb_strlen($categoryName) > 50) {
        $categoryError = "The category name must be 50 characters or fewer.";
    } else {
        // Case-insensitive duplicate check ("literature" = "Literature")
        $dup = $pdo->prepare("SELECT 1 FROM categories WHERE LOWER(name) = LOWER(:name)");
        $dup->execute([':name' => $categoryName]);

        if ($dup->fetchColumn()) {
            $categoryError = "A category named \"" . $categoryName . "\" already exists.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                $stmt->execute([
                    ':name'        => $categoryName,
                    ':description' => $categoryDesc !== '' ? $categoryDesc : null
                ]);
                header("Location: admin_books.php?msg=category_added");
                exit;
            } catch (PDOException $e) {
                $categoryError = ($e->getCode() == 23000)
                    ? "A category with this name already exists."
                    : "Database error: " . $e->getMessage();
            }
        }
    }
}

// --- DELETE AND FETCH LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $book_id = (int)$_GET['id'];

    $imgStmt = $pdo->prepare("SELECT image_path FROM books WHERE book_id = :book_id");
    $imgStmt->execute([':book_id' => $book_id]);
    $oldImage = $imgStmt->fetchColumn() ?: null;

    $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = :book_id");
    $stmt->execute([':book_id' => $book_id]);
    deleteCoverImage($oldImage);
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
$categoryStats = $pdo->query("
    SELECT categories.name, COUNT(books.book_id) AS book_count
    FROM categories
    LEFT JOIN books ON books.category_id = categories.category_id
    GROUP BY categories.category_id
    ORDER BY categories.name ASC
")->fetchAll();
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Books - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="enduser_dashboard.php"><span class="brand-mark">📚</span> Campus Library</a>
            <nav class="nav-links">
                <a href="enduser_dashboard.php">Dashboard</a>
                <?php if ($_SESSION['role'] === 'librarian'): ?>
                    <a href="admin_books.php" class="active">Manage Books</a>
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

    <?php $isEditing = isset($editBook['book_id']); ?>

    <main class="container">
        <div class="page-title">
            <h1>Manage books</h1>
            <p>Add new titles, update copy counts, or remove books from the catalog.</p>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'added'): ?>
                <div class="alert alert-success">New book added.</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success">Book details updated.</div>
            <?php elseif ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success">Book removed.</div>
            <?php elseif ($_GET['msg'] === 'category_added'): ?>
                <div class="alert alert-success">New category added. You can now pick it when adding a book.</div>
            <?php endif; ?>
        <?php endif; ?>

        <h2 class="section-title"><?= $isEditing ? 'Edit book' : 'Add a new book' ?></h2>

        <div class="card card-gold">
            <form method="POST" action="admin_books.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
                <?php if ($isEditing): ?>
                    <input type="hidden" name="book_id" value="<?= $editBook['book_id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="field">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" value="<?= htmlspecialchars($editBook['title'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" value="<?= htmlspecialchars($editBook['author'] ?? '') ?>" required>
                    </div>

                    <div class="field">
                        <label for="isbn">ISBN</label>
                        <input type="text"
                               id="isbn"
                               name="isbn"
                               value="<?= htmlspecialchars($editBook['isbn'] ?? '') ?>"
                               pattern="^(?:\d{10}|\d{13}|[\d-]{13,17})$"
                               placeholder="e.g. 978-0-307-47427-8"
                               title="Please enter a valid 10 or 13 digit ISBN"
                               required>
                        <?php if ($isbnError): ?>
                            <span class="field-error"><?= htmlspecialchars($isbnError) ?></span>
                        <?php else: ?>
                            <span class="field-hint">10 or 13 digits. Hyphens are fine.</span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= (isset($editBook) && $editBook['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-hint">Not listed? <a href="#add-category">Add a new category</a>.</span>
                    </div>

                    <div class="field">
                        <label for="total_copies">Total copies</label>
                        <input type="number" id="total_copies" name="total_copies" value="<?= htmlspecialchars($editBook['total_copies'] ?? '1') ?>" min="1" required>
                    </div>

                    <?php if ($isEditing): ?>
                        <div class="field">
                            <label for="available_copies">Available copies</label>
                            <input type="number" id="available_copies" name="available_copies" value="<?= htmlspecialchars($editBook['available_copies']) ?>" min="0" required>
                            <?php if ($copiesError): ?>
                                <span class="field-error"><?= htmlspecialchars($copiesError) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="field field-full">
                        <label for="cover">Book cover image</label>
                        <?php if ($isEditing && !empty($editBook['image_path'])): ?>
                            <div class="cover-current">
                                <?= coverImage($editBook['image_path'], 'Current cover') ?>
                                <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp,image/gif">
                        <?php if ($imageError): ?>
                            <span class="field-error"><?= htmlspecialchars($imageError) ?></span>
                        <?php else: ?>
                            <span class="field-hint">Optional. JPG, PNG, WEBP or GIF, up to 2 MB.<?= $isEditing ? ' Choosing a new file replaces the current cover.' : '' ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn"><?= $isEditing ? 'Save changes' : 'Add book' ?></button>
                    <?php if (isset($editBook)): ?>
                        <a href="admin_books.php" class="btn btn-ghost">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h2 class="section-title" id="add-category">Categories</h2>

        <div class="card card-gold">
            <form method="POST" action="admin_books.php#add-category">
                <input type="hidden" name="action" value="create_category">

                <div class="form-grid">
                    <div class="field">
                        <label for="category_name">New category name</label>
                        <input type="text" id="category_name" name="category_name" maxlength="50" value="<?= htmlspecialchars($categoryName) ?>" placeholder="e.g. History" required>
                        <?php if ($categoryError): ?>
                            <span class="field-error"><?= htmlspecialchars($categoryError) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="category_description">Description (optional)</label>
                        <input type="text" id="category_description" name="category_description" value="<?= htmlspecialchars($categoryDesc) ?>" placeholder="What kind of books belong here?">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Add category</button>
                </div>
            </form>

            <?php if (!empty($categoryStats)): ?>
                <p class="chips-label">Existing categories</p>
                <ul class="chips">
                    <?php foreach ($categoryStats as $c): ?>
                        <li class="chip"><?= htmlspecialchars($c['name']) ?> <span class="chip-count"><?= (int)$c['book_count'] ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Book catalog (<?= count($books) ?>)</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Total</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($books)): ?>
                        <tr><td colspan="9" class="empty">The catalog is empty. Add your first book above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <tr>
                                <td><?= $b['book_id'] ?></td>
                                <td><?= coverImage($b['image_path'] ?? null, $b['title']) ?></td>
                                <td class="cell-title"><?= htmlspecialchars($b['title']) ?></td>
                                <td><?= htmlspecialchars($b['author']) ?></td>
                                <td><?= htmlspecialchars($b['isbn']) ?></td>
                                <td><?= htmlspecialchars($b['category_name'] ?? 'Uncategorized') ?></td>
                                <td><?= $b['total_copies'] ?></td>
                                <td><span class="stock <?= $b['available_copies'] > 0 ? '' : 'stock-out' ?>"><?= $b['available_copies'] ?></span></td>
                                <td>
                                    <div class="actions">
                                        <a href="admin_books.php?action=edit&id=<?= $b['book_id'] ?>" class="btn btn-sm btn-navy">Edit</a>
                                        <a href="admin_books.php?action=delete&id=<?= $b['book_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this book? This cannot be undone.');">Delete</a>
                                    </div>
                                </td>
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