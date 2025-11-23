<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$item_id = intval($data['item_id'] ?? 0);
$comment_text = trim($data['comment'] ?? '');

if ($item_id <= 0 || $comment_text === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Insert comment
$stmt = $conn->prepare("INSERT INTO comments (item_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $item_id, $user_id, $comment_text);

if ($stmt->execute()) {
    // Notify item owner (if commenter is not owner)
    $ownerStmt = $conn->prepare("SELECT user_id, title FROM items WHERE id = ?");
    $ownerStmt->bind_param("i", $item_id);
    $ownerStmt->execute();
    $ownerStmt->bind_result($owner_id, $item_title);
    $ownerStmt->fetch();
    $ownerStmt->close();

    if ($owner_id != $user_id) {
        $message = "New comment on your item: '{$item_title}'";
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, item_id, message, is_read) 
            VALUES (?, ?, ?, 0)
        ");
        $notifStmt->bind_param("iis", $owner_id, $item_id, $message);
        $notifStmt->execute();
        $notifStmt->close();
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
?>
