<?php
session_start();
include('db_connect.php');

// Ensure Admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$type       = $_POST['type'] ?? 'Lost';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date   = $_POST['end_date'] ?? date('Y-m-t');
$category   = $_POST['category'] ?? '%';

// Fetch items
$stmt = $conn->prepare("
    SELECT i.title AS Item, i.category AS Category, i.status AS Status, i.`date` AS Date,
           CONCAT(u.first_name,' ',u.last_name) AS ReportedBy
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.type=? AND i.`date` BETWEEN ? AND ? AND i.category LIKE ?
    ORDER BY i.`date` DESC
");
$stmt->bind_param("ssss", $type, $start_date, $end_date, $category);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$filename = $type.'_items_'.date('Ymd_His').'.xls';

// Set headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// Print column headers
if(!empty($items)){
    echo implode("\t", array_keys($items[0])) . "\n";
    foreach($items as $row){
        echo implode("\t", array_values($row)) . "\n";
    }
}
exit();
