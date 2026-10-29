<?php
session_start();
require_once 'db.php';

// Auth & Role check: Redirect if not logged in or if user is not a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'librarian') {
    header("Location: enduser_dashboard.php");
    exit;
}

// Fetch all registered users from SQLite (excluding plain text passwords)
$stmt = $pdo->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Accounts - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header>
        <h1>Registered Users Directory</h1>
        <p>Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> (Librarian)</p>
        <nav>
            <a href="enduser_dashboard.php">Dashboard</a> | 
            <a href="admin_books.php">Manage Books (CRUD)</a> | 
            <a href="users.php">View Users</a> | 
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <hr>

    <h2>All Registered Accounts</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="5">No registered accounts found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['user_id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><strong><?= ucfirst(htmlspecialchars($user['role'])) ?></strong></td>
                        <td><?= $user['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>