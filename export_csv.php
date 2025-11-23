<?php
session_start();
include('db_connect.php');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Access denied");
}

$type       = $_POST['type'] ?? 'Lost';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date   = $_POST['end_date'] ?? date('Y-m-t');
$category   = $_POST['category'] ?? '%';
if (empty($category)) $category = '%';

// Fetch items based on type, date, category
$stmt = $conn->prepare("
    SELECT i.title AS item_name, i.category, i.status, i.`date`,
           CONCAT(u.first_name,' ',u.last_name) AS reported_by
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.type=? AND i.status != 'rejected' AND i.`date` BETWEEN ? AND ? AND i.category LIKE ?
    ORDER BY i.`date` DESC
");
if (!$stmt) die("Prepare failed: ".$conn->error);
$stmt->bind_param("ssss", $type, $start_date, $end_date, $category);
$stmt->execute();
$result = $stmt->get_result();

// Output CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$type.'_items.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Item Name', 'Category', 'Reported By', 'Date', 'Status']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [$row['item_name'], $row['category'], $row['reported_by'], $row['date'], $row['status']]);
}

fclose($output);
$stmt->close();
exit();
