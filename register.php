<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'student'; // Default to student

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
    <title>Register - Campus Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <h2>Register Account</h2>
    <?php if ($error): ?><p style="color: red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color: green;"><?= $success ?></p><?php endif; ?>

    <form method="POST" action="register.php">
        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>
        
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>
        
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Role:</label><br>
        <select name="role">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="librarian">Librarian</option>
        </select><br><br>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</body>
</html>