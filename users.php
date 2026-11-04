<?php
session_start();
require_once 'db.php';

// Auth & Role check: Redirect if not logged in or if user is not a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: enduser_dashboard.php");
    exit;
}

$roleError = '';

// --- CHANGE ROLE (librarian only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_role') {
    $targetId = (int)$_POST['user_id'];
    $newRole  = ($_POST['role'] ?? '') === 'librarian' ? 'librarian' : 'student';

    if ($targetId === (int)$_SESSION['user_id']) {
        // Prevents the last librarian from locking everyone out
        $roleError = "You can't change your own role.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE user_id = :user_id");
        $stmt->execute([':role' => $newRole, ':user_id' => $targetId]);
        header("Location: users.php?msg=" . ($newRole === 'librarian' ? 'promoted' : 'demoted'));
        exit;
    }
}

// Fetch all registered users from SQLite (excluding plain text passwords)
$stmt = $pdo->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registered Users - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="enduser_dashboard.php"><span class="brand-mark">📚</span> Campus Library</a>
            <nav class="nav-links">
                <a href="enduser_dashboard.php">Dashboard</a>
                <?php if ($_SESSION['role'] === 'librarian'): ?>
                    <a href="admin_books.php">Manage Books</a>
                    <a href="users.php" class="active">Users</a>
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

    <main class="container">
        <div class="page-title">
            <h1>Registered users</h1>
            <p><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?> in the system.</p>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'promoted'): ?>
            <div class="alert alert-success">The user is now a librarian.</div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'demoted'): ?>
            <div class="alert alert-success">The librarian was changed back to a student.</div>
        <?php endif; ?>
        <?php if ($roleError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($roleError) ?></div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="empty">No accounts yet. New registrations will show up here.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['user_id'] ?></td>
                                <td class="cell-title"><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><span class="badge <?= $user['role'] === 'librarian' ? 'badge-librarian' : 'badge-role' ?>"><?= ucfirst(htmlspecialchars($user['role'])) ?></span></td>
                                <td><?= htmlspecialchars(substr((string)$user['created_at'], 0, 10)) ?></td>
                                <td>
                                    <?php if ((int)$user['user_id'] === (int)$_SESSION['user_id']): ?>
                                        <span class="stock">This is you</span>
                                    <?php else: ?>
                                        <form method="POST" action="users.php" class="actions">
                                            <input type="hidden" name="action" value="set_role">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <?php if ($user['role'] === 'librarian'): ?>
                                                <input type="hidden" name="role" value="student">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove librarian access from <?= htmlspecialchars($user['username'], ENT_QUOTES) ?>?');">Remove librarian</button>
                                            <?php else: ?>
                                                <input type="hidden" name="role" value="librarian">
                                                <button type="submit" class="btn btn-sm btn-navy" onclick="return confirm('Make <?= htmlspecialchars($user['username'], ENT_QUOTES) ?> a librarian? They will be able to manage books and users.');">Make librarian</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
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