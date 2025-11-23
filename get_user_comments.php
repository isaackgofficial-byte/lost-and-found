<?php
session_start();
include('db_connect.php');

$item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
if ($item_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT c.id, c.comment, c.date_posted, u.id AS user_id, u.username, u.profile_image
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.item_id = ?
    ORDER BY c.date_posted ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['profile_image'])) $row['profile_image'] = 'uploads/avatar.png';
    $comments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($comments);
?>
