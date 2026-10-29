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
    <title>Login - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h2>Library Login</h2>
    <?php if ($error): ?><p style="color: red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST" action="login.php">
        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>
        
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</body>
</html>