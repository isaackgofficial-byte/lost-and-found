<?php
session_start();
include('db_connect.php');

// Ensure item_id is provided
if(!isset($_GET['item_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit();
}

$item_id = intval($_GET['item_id']);

// Fetch comments with username
$stmt = $conn->prepare("
    SELECT c.comment, c.date_posted, CONCAT(u.first_name,' ',u.last_name) AS username
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.item_id = ?
    ORDER BY c.date_posted ASC
");

if(!$stmt) {
    http_response_code(500);
    echo json_encode([]);
    exit();
}

$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$comments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode($comments);
?>
