<?php
session_start();
require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Verify user exists and check hashed password
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Redirect Librarians to admin management, everyone else to end-user dashboard
            if ($user['role'] === 'librarian') {
                header("Location: admin_books.php");
                exit;
            } else {
                header("Location: enduser_dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
    <main class="auth-wrap">
        <div class="auth-brand">
            <span class="brand-mark">📚</span>
            <h1>Campus Library</h1>
            <p>Sign in to browse and reserve books.</p>
        </div>

        <div class="card card-gold auth-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-block">Log in</button>
            </form>
        </div>

        <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
        <p class="auth-switch"><a href="items.php">Browse Books</a> without logging in</p>
    </main>
    <footer class="site-footer">Campus Library &copy; <?= date('Y') ?></footer>

</body>
</html>