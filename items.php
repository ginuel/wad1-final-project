<?php
require_once 'db.php';

// Example: Fetching books with their category name (READ operation)
$query = "
    SELECT 
        books.*, 
        categories.name AS category_name 
    FROM books 
    LEFT JOIN categories ON books.category_id = categories.category_id
";

$stmt = $pdo->query($query);
$books = $stmt->fetchAll();

// Display results
foreach ($books as $book) {
    echo $book['title'] . " by " . $book['author'] . " (" . $book['available_copies'] . " available)<br>";
}
?>