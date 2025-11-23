<?php
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['item_id']) || !isset($data['status'])) {
    echo json_encode(["error" => "Invalid request."]);
    exit;
}

$item_id = intval($data['item_id']);
$status = $data['status'];
$comment = isset($data['comment']) ? trim($data['comment']) : '';

$stmt = $conn->prepare("UPDATE items SET status = ?, admin_comment = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("ssi", $status, $comment, $item_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Database update failed."]);
    }
    $stmt->close();
} else {
    echo json_encode(["error" => "SQL prepare failed."]);
}

$conn->close();
?>
