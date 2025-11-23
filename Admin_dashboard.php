<?php
session_start();
include 'db_connect.php'; // Connect to your MySQL database

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Function to safely get counts
function getCount($conn, $table, $condition = "1") {
    $allowedTables = ['users', 'posts', 'reports', 'items'];
    if (!in_array($table, $allowedTables)) return 0;

    $sql = "SELECT COUNT(*) AS total FROM `$table` WHERE $condition";
    $stmt = $conn->prepare($sql);
    if (!$stmt) die("SQL prepare error on table `$table`: " . $conn->error);

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) die("SQL get_result error on table `$table`: " . $stmt->error);

    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['total'] ?? 0;
}

// User counts
$totalUsers = getCount($conn, "users", "is_deleted=0");
$removedUsers = getCount($conn, "users", "is_deleted=1");
$deactivatedUsers = getCount($conn, "users", "status='inactive' AND is_deleted=0");

// Total Posts from items table (approved items only)
$totalPosts = getCount($conn, "items", "status='approved'");

// Other counts
$pendingReports = getCount($conn, "reports", "status='pending'");
$foundItems = getCount($conn, "items", "type='found' AND status='approved'");
$lostItems = getCount($conn, "items", "type='lost' AND status='approved'");
$pendingApproval = getCount($conn, "items", "status='pending'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Lost & Found</title>
<style>
    body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f6f8; }
    header { background-color: #4361ee; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
    header h1 { margin: 0; font-size: 1.5rem; }
    header button { background-color: #dc3545; color: white; border: none; padding: 0.5rem 1rem; cursor: pointer; border-radius: 5px; margin-top: 0.5rem; }
    nav { background-color: #2c3e50; display: flex; justify-content: center; flex-wrap: wrap; }
    nav a { color: white; text-decoration: none; padding: 1rem 1.5rem; display: block; transition: background 0.3s; }
    nav a:hover, nav a.active { background-color: #4361ee; }
    .main-content { padding: 2rem; }

    /* Grid layout for cards */
    .container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        justify-items: center;
    }

    .card {
        background-color: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        text-align: center;
        width: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }

    /* Color variants for special cards */
    .removed h2 { color: #dc3545; }
    .deactivated h2 { color: #f39c12; }
    .approved h2 { color: #27ae60; }
    .pending h2 { color: #e67e22; }

    .card h2 { font-size: 2rem; margin-bottom: 0.5rem; }
    .card p { margin: 0; font-size: 1rem; color: #555; }

    @media (max-width: 500px) {
        .container { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<header>
    <h1>Admin Dashboard</h1>
    <button onclick="window.location.href='logout.php'">Logout</button>
</header>

<nav>
    <a href="Admin_dashboard.php" class="active"> Dashboard</a>
    <a href="users.php"> User Management</a>
    <a href="content.php"> Post Moderation</a>
    <a href="reports.php"> Reports</a>
    <a href="settings.php"> System Settings</a>
</nav>

<div class="main-content">
    <div class="container">
        <!-- User-related cards -->
        <div class="card removed">
            <h2><?php echo $removedUsers; ?></h2>
            <p>Removed Users</p>
        </div>
        <div class="card deactivated">
            <h2><?php echo $deactivatedUsers; ?></h2>
            <p>Deactivated Users</p>
        </div>
        <div class="card approved">
            <h2><?php echo $totalUsers; ?></h2>
            <p>Total Users</p>
        </div>

        <!-- Total Posts card from items table -->
        <div class="card approved">
            <h2><?php echo $totalPosts; ?></h2>
            <p>Total Posts</p>
        </div>

        <!-- Other dashboard cards -->
        <div class="card pending">
            <h2><?php echo $pendingReports; ?></h2>
            <p>Pending Reports</p>
        </div>
        <div class="card approved">
            <h2><?php echo $foundItems; ?></h2>
            <p>Found Items</p>
        </div>
        <div class="card pending">
            <h2><?php echo $pendingApproval; ?></h2>
            <p>Pending Approval</p>
        </div>
        <div class="card approved">
            <h2><?php echo $lostItems; ?></h2>
            <p>Lost Items</p>
        </div>
    </div>
</div>

<script>
    console.log("Admin Dashboard Loaded");
</script>

</body>
</html>
