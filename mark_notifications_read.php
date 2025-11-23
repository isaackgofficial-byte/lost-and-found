<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
?>
