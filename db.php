<?php
// db.php - Database Connection & Seeder Setup

$dbFile = __DIR__ . '/database.sqlite';

try {
    // 1. Connect or create SQLite database file
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 2. Enable Foreign Key Constraints
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // 3. Create Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'student',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
        );

        CREATE TABLE IF NOT EXISTS books (
            book_id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            author TEXT NOT NULL,
            isbn TEXT UNIQUE,
            category_id INTEGER,
            total_copies INTEGER NOT NULL DEFAULT 1,
            available_copies INTEGER NOT NULL DEFAULT 1,
            image_path TEXT,
            FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS borrow_records (
            record_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            book_id INTEGER NOT NULL,
            borrow_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            due_date DATETIME,
            return_date DATETIME,
            status TEXT NOT NULL DEFAULT 'Pending',
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE
        );
    ");

    // 4. Seed Data Function (Runs ONLY if tables are empty)
    seedDataIfEmpty($pdo);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

/**
 * Checks if tables have data and inserts initial sample records.
 */
function seedDataIfEmpty(PDO $pdo) {
    // Check if users table already has data
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    if ($userCount == 0) {
        // --- A. SEED USERS ---
        // Passwords hashed with BCRYPT (Satisfies security requirement)
        $librarianPass = password_hash('admin123', PASSWORD_BCRYPT);
        $studentPass   = password_hash('student123', PASSWORD_BCRYPT);

        $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
        
        $stmtUser->execute([':username' => 'librarian', ':email' => 'admin@campus.edu', ':password' => $librarianPass, ':role' => 'librarian']);
        $stmtUser->execute([':username' => 'garrett', ':email' => 'garrett@campus.edu', ':password' => $studentPass, ':role' => 'student']);

        // --- B. SEED CATEGORIES ---
        $pdo->exec("
            INSERT INTO categories (name, description) VALUES 
            ('Computer Science', 'Books on programming, web development, and algorithms.'),
            ('Literature', 'Classic and contemporary novels and stories.'),
            ('Engineering', 'Electrical, mechanical, and civil engineering guides.');
        ");

        // --- C. SEED BOOKS ---
        $pdo->exec("
            INSERT INTO books (title, author, isbn, category_id, total_copies, available_copies) VALUES 
            ('Web Application Development 101', 'John Doe', '978-0131103627', 1, 5, 4),
            ('Database Systems & SQLite', 'Jane Smith', '978-0201633610', 1, 3, 3),
            ('To Kill a Mockingbird', 'Harper Lee', '978-0061120084', 2, 2, 2);
        ");

        // --- D. SEED BORROW RECORDS ---
        $pdo->exec("
            INSERT INTO borrow_records (user_id, book_id, due_date, status) VALUES 
            (2, 1, '2026-11-20 00:00:00', 'Approved');
        ");
    }
}
?>