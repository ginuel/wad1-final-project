<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>

    <form action="login-process.php" method="POST">
        <h2>Login</h2>

        <label for="username">Username or Email</label><br>
        <input type="text" id="username" name="username" required><br><br>

        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit" name="login">Log In</button>
    </form>
</body>
</html>
