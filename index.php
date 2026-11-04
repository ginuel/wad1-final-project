<?php
session_start();
$loggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Campus Library - Browse, reserve and manage school books</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="index.php"><span class="brand-mark">📚</span> Campus Library</a>
            <nav class="nav-links">
                <a href="index.php" class="active">Home</a>
                <a href="items.php">Browse Books</a>
                <?php if ($loggedIn): ?>
                    <a href="enduser_dashboard.php">Dashboard</a>
                    <?php if ($_SESSION['role'] === 'librarian'): ?>
                        <a href="admin_books.php">Manage Books</a>
                        <a href="users.php">Users</a>
                    <?php endif; ?>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
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

    <section class="hero">
        <div class="container">
            <h1>Find it. Reserve it. Read it.</h1>
            <p>Campus Library lets students and teachers browse the school's books and reserve them online, while the librarian approves requests and keeps the catalog up to date.</p>
            <div class="hero-actions">
                <?php if ($loggedIn): ?>
                    <a class="btn" href="enduser_dashboard.php">Go to my dashboard</a>
                    <a class="btn btn-light" href="items.php">Browse books</a>
                <?php else: ?>
                    <a class="btn" href="login.php">Log in</a>
                    <a class="btn btn-light" href="register.php">Create an account</a>
                    <a class="btn btn-light" href="items.php">Browse books</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <main class="container">

        <h2 class="section-title">Who uses it</h2>
        <div class="feature-grid">
            <div class="card card-gold feature">
                <h3>Visitors</h3>
                <ul>
                    <li>Browse the full catalog without an account.</li>
                    <li>See each book's cover, author, category and how many copies are available.</li>
                    <li>Create an account as a student or a teacher.</li>
                </ul>
            </div>
            <div class="card card-gold feature">
                <h3>Students and teachers</h3>
                <ul>
                    <li>Log in to a personal dashboard with reservation stats.</li>
                    <li>Reserve any book that has copies available.</li>
                    <li>Track every reservation with its dates and status: Pending, Approved, Rejected or Returned.</li>
                    <li>Renew a loan up to 2 times, 7 more days each, as long as it is not overdue.</li>
                    <li>See overdue loans flagged in red.</li>
                </ul>
            </div>
            <div class="card card-gold feature">
                <h3>Librarians</h3>
                <ul>
                    <li>Approve or reject reservation requests from one list.</li>
                    <li>Mark returned books so the copy goes back on the shelf, and spot overdue loans.</li>
                    <li>Add, edit and delete books, including cover images.</li>
                    <li>Add new categories and see how many books each holds.</li>
                    <li>View all users and make someone a librarian.</li>
                </ul>
            </div>
        </div>

        <h2 class="section-title">How a reservation works</h2>
        <div class="card">
            <ol class="steps">
                <li><strong>Find a book.</strong> Use the catalog on your dashboard. Books with no copies left show as out of stock.</li>
                <li><strong>Reserve it.</strong> Select Reserve. A Pending request is created and one copy is held for you right away.</li>
                <li><strong>The librarian reviews it.</strong> Requests appear on the librarian's dashboard to Approve or Reject. Approving starts a 7-day loan. Rejecting puts the copy back on the shelf.</li>
                <li><strong>Borrow the book.</strong> Once approved, your dashboard shows the due date. A loan is flagged Overdue when its due date passes.</li>
                <li><strong>Renew if you need more time.</strong> Select Renew on an approved loan to add 7 days. Each loan can be renewed up to 2 times, and overdue loans can't be renewed.</li>
                <li><strong>Return it.</strong> Bring the book back and the librarian selects Mark returned. The status changes to Returned and the copy is available to others again.</li>
            </ol>
        </div>


        
        <h2 class="section-title">Good to know</h2>
        <div class="card">
            <ul class="checklist">
                <li>A loan lasts 7 days from approval. Late fees are not part of the system.</li>
                <li>Deleting a book is permanent and also removes that book's reservation records.</li>
                <li>Librarian accounts are built in or promoted by an existing librarian.</li>
            </ul>
        </div>
    </main>

    <footer class="site-footer">Campus Library &copy; <?= date('Y') ?></footer>
</body>
</html>