<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'student';

    // Only student or teacher can be chosen here. The librarian account is
    // hardcoded in db.php and promoted by a librarian from the Users page.
    if (!in_array($role, ['student', 'teacher'], true)) {
        $role = 'student';
    }

    if (!empty($username) && !empty($email) && !empty($password)) {
        // Secure password hashing
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
        
        try {
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':password' => $hashedPassword,
                ':role'     => $role
            ]);
            $success = "Registration successful! You can now <a href='login.php'>Login</a>.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Unique constraint violation
                $error = "Username or Email is already registered.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
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
    <title>Register - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
    <main class="auth-wrap">
        <div class="auth-brand">
            <span class="brand-mark">📚</span>
            <h1>Create your account</h1>
            <p>Join the Campus Library to reserve books online.</p>
        </div>

        <div class="card card-gold auth-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="role">I am a</label>
                    <select id="role" name="role">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-block">Create account</button>
            </form>
        </div>

        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </main>
    <footer class="site-footer">Campus Library &copy; <?= date('Y') ?></footer>

</body>
</html>