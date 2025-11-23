<?php
session_start();
include('db_connect.php');

$sql = "
    SELECT i.id, i.title, i.description, i.category, i.type, i.image AS image_path,
           i.date_posted AS created_at, u.id AS user_id, CONCAT(u.first_name,' ',u.last_name) AS full_name
    FROM items i
    JOIN users u ON i.user_id = u.id
    WHERE i.status = 'approved'
    ORDER BY i.date_posted DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare failed: " . $conn->error);

$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['image_path'])) $row['image_path'] = 'uploads/default_image.png';
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode($items);
?>
