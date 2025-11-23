<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch notifications, most recent first
$stmt = $conn->prepare("
    SELECT id, item_id, message, is_read, date_created
    FROM notifications
    WHERE user_id = ?
    ORDER BY date_created DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'item_id' => $row['item_id'],
        'message' => $row['message'],
        'is_read' => (bool)$row['is_read'],
        'date_created' => $row['date_created']
    ];
}

$stmt->close();

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>
