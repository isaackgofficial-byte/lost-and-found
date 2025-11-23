<?php
include("session_check.php");
include("db_connect.php");

header('Content-Type: text/plain'); // AJAX response as plain text

if (!isset($_GET['id'])) {
    echo "Item ID not provided";
    exit;
}

$itemId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

// Ensure the user owns the item
$stmt = $conn->prepare("SELECT id FROM items WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $itemId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo "Item not found or you don't have permission";
    exit;
}
$stmt->close();

// Delete the item
$stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
$stmt->bind_param("i", $itemId);

if ($stmt->execute()) {
    $stmt->close();
    echo "success"; // AJAX reads this to remove item from page
} else {
    $stmt->close();
    echo "Failed to delete item. Try again.";
}
?>
