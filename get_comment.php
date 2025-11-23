<?php
session_start();
include('db_connect.php');

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
if ($item_id <= 0) {
    echo json_encode(['error' => 'Invalid item ID']);
    exit;
}

$sql = "SELECT admin_comment FROM items WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['comment' => $row['admin_comment'] ?: 'No comment from Admin.']);
} else {
    echo json_encode(['comment' => 'Item not found.']);
}

$stmt->close();
$conn->close();
?>
