<?php
session_start();
include('db_connect.php');

// Ensure Admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Admin name for reports
$adminName = trim($_SESSION['admin_name'] ?? ($_SESSION['admin_username'] ?? 'Administrator'));

// -------------------- Helpers --------------------
function safe_filename($s) {
    return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $s);
}

function send_csv($rows, $columns, $filename="export.csv") {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $columns);
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = isset($r[$col]) ? $r[$col] : '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit();
}

function send_excel_as_tsv($rows, $columns, $filename="export.xls") {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    // use tab separator - works well for Excel
    fputcsv($out, $columns, "\t");
    foreach ($rows as $r) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = isset($r[$col]) ? $r[$col] : '';
        }
        fputcsv($out, $line, "\t");
    }
    fclose($out);
    exit();
}

// -------------------- EXPORT HANDLERS --------------------
// 1) CSV / Excel exports for Lost, Found, UserActivity (triggered by POST from dropdown)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'], $_POST['type']) && !isset($_POST['export_full_report'])) {
    $exportType = $_POST['export_type']; // csv or excel
    $type = $_POST['type']; // Lost | Found | UserActivity

    // date/category filters may be provided in hidden inputs (for items)
    $start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_POST['end_date'] ?? $_GET['end_date'] ?? date('Y-m-t');
    $category   = $_POST['category'] ?? $_GET['category'] ?? '%';
    if (empty($category)) $category = '%';

    if ($type === 'Lost' || $type === 'Found') {
        $sql = "
            SELECT i.id, i.title AS item_name, i.category, i.status, i.`date` AS date, CONCAT(u.first_name,' ',u.last_name) AS reported_by
            FROM items i
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.type=? AND i.status != 'rejected' AND i.`date` BETWEEN ? AND ? AND i.category LIKE ?
            ORDER BY i.`date` DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("ssss", $type, $start_date, $end_date, $category);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $columns = ['id','item_name','category','reported_by','date','status'];
        $filename = strtolower($type) . "_items_" . safe_filename($start_date . "_to_" . $end_date) . (($exportType==='excel')? ".xls":".csv");

        if ($exportType === 'csv') send_csv($rows, $columns, $filename);
        send_excel_as_tsv($rows, $columns, $filename);
    }

    if ($type === 'UserActivity') {
        $res = $conn->query("
            SELECT CONCAT(u.first_name,' ',u.last_name) AS username,
                   COUNT(CASE WHEN i.type='Lost' THEN 1 END) AS lost_items_uploaded,
                   COUNT(CASE WHEN i.type='Found' THEN 1 END) AS found_items_uploaded,
                   COUNT(c.id) AS comments_made
            FROM users u
            LEFT JOIN items i ON i.user_id = u.id
            LEFT JOIN comments c ON c.user_id = u.id
            GROUP BY u.id
            ORDER BY lost_items_uploaded DESC
        ");
        if (!$res) die("Query failed: " . $conn->error);
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $columns = ['username','lost_items_uploaded','found_items_uploaded','comments_made'];
        $filename = "user_activity_" . date('Ymd_His') . (($exportType==='excel')? ".xls":".csv");
        if ($exportType === 'csv') send_csv($rows, $columns, $filename);
        send_excel_as_tsv($rows, $columns, $filename);
    }

    // unknown type fallback
    header("Location: reports.php");
    exit();
}

// 2) Full Report export (triggered by modal form with export_full_report)
// Produces PDF via dompdf if available, otherwise HTML download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_full_report']) && $_POST['export_full_report'] == '1') {
    // sanitize simple text fields (we will htmlspecialchars when embedding)
    $admin_description = trim($_POST['admin_description'] ?? '');
    $admin_comment = trim($_POST['admin_comment'] ?? '');
    $reportDate = date("Y-m-d H:i:s");

    // Fetch datasets
    // Lost
    $stmt = $conn->prepare("
        SELECT i.id, i.title, i.category, i.status, i.`date`, CONCAT(u.first_name,' ',u.last_name) AS reported_by
        FROM items i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.type='Lost' AND i.status != 'rejected'
        ORDER BY i.`date` DESC
    ");
    $stmt->execute();
    $lost_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Found
    $stmt = $conn->prepare("
        SELECT i.id, i.title, i.category, i.status, i.`date`, CONCAT(u.first_name,' ',u.last_name) AS reported_by
        FROM items i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.type='Found' AND i.status != 'rejected'
        ORDER BY i.`date` DESC
    ");
    $stmt->execute();
    $found_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // User activity
    $res = $conn->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) AS username,
               COUNT(CASE WHEN i.type='Lost' THEN 1 END) AS lost_items_uploaded,
               COUNT(CASE WHEN i.type='Found' THEN 1 END) AS found_items_uploaded,
               COUNT(c.id) AS comments_made
        FROM users u
        LEFT JOIN items i ON i.user_id = u.id
        LEFT JOIN comments c ON c.user_id = u.id
        GROUP BY u.id
        ORDER BY lost_items_uploaded DESC
    ");
    $user_activity = $res->fetch_all(MYSQLI_ASSOC);

    // Reports
    $res = $conn->query("
        SELECT r.id, r.report_type, r.description, r.status, r.admin_comment, r.date_created,
               CONCAT(u.first_name,' ',u.last_name) AS username,
               i.title AS item_title, i.type AS item_type
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN items i ON r.item_id = i.id
        ORDER BY r.date_created DESC
    ");
    $reports = $res->fetch_all(MYSQLI_ASSOC);

    // Build HTML
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>Full Report</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;color:#222}
        h1,h2{color:#0b75b7}
        table{border-collapse:collapse;width:100%;margin-bottom:14px}
        th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top}
        th{background:#f4f4f4}
        .meta{margin-bottom:12px}
        .section { margin-bottom:18px; }
    </style>
    </head><body>';
    $html .= '<h1>Lost & Found System — Full Report</h1>';
    $html .= '<div class="meta"><strong>Reported By:</strong> '.htmlspecialchars($adminName).' &nbsp; | &nbsp; <strong>Date:</strong> '.htmlspecialchars($reportDate).'</div>';
    if ($admin_description !== '') $html .= '<div class="section"><h2>Description</h2><div>'.nl2br(htmlspecialchars($admin_description)).'</div></div>';
    if ($admin_comment !== '') $html .= '<div class="section"><h2>Admin Comment</h2><div>'.nl2br(htmlspecialchars($admin_comment)).'</div></div>';

    // Lost items table
    $html .= '<div class="section"><h2>Lost Items</h2>';
    if (count($lost_items) === 0) {
        $html .= '<p>No lost items to show.</p>';
    } else {
        $html .= '<table><thead><tr><th>#</th><th>Title</th><th>Category</th><th>Reported By</th><th>Date</th><th>Status</th></tr></thead><tbody>';
        foreach ($lost_items as $i => $it) {
            $html .= '<tr><td>'.($i+1).'</td><td>'.htmlspecialchars($it['title']).'</td><td>'.htmlspecialchars($it['category']).'</td><td>'.htmlspecialchars($it['reported_by'] ?? 'Unknown').'</td><td>'.htmlspecialchars($it['date']).'</td><td>'.htmlspecialchars($it['status']).'</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</div>';

    // Found items
    $html .= '<div class="section"><h2>Found Items</h2>';
    if (count($found_items) === 0) {
        $html .= '<p>No found items to show.</p>';
    } else {
        $html .= '<table><thead><tr><th>#</th><th>Title</th><th>Category</th><th>Reported By</th><th>Date</th><th>Status</th></tr></thead><tbody>';
        foreach ($found_items as $i => $it) {
            $html .= '<tr><td>'.($i+1).'</td><td>'.htmlspecialchars($it['title']).'</td><td>'.htmlspecialchars($it['category']).'</td><td>'.htmlspecialchars($it['reported_by'] ?? 'Unknown').'</td><td>'.htmlspecialchars($it['date']).'</td><td>'.htmlspecialchars($it['status']).'</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</div>';

    // User activity
    $html .= '<div class="section"><h2>User Activity</h2>';
    if (count($user_activity) === 0) {
        $html .= '<p>No user activity data.</p>';
    } else {
        $html .= '<table><thead><tr><th>User</th><th>Lost Uploaded</th><th>Found Uploaded</th><th>Comments</th></tr></thead><tbody>';
        foreach ($user_activity as $ua) {
            $html .= '<tr><td>'.htmlspecialchars($ua['username']).'</td><td>'.htmlspecialchars($ua['lost_items_uploaded']).'</td><td>'.htmlspecialchars($ua['found_items_uploaded']).'</td><td>'.htmlspecialchars($ua['comments_made']).'</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</div>';

    // Reports
    $html .= '<div class="section"><h2>Reports</h2>';
    if (count($reports) === 0) {
        $html .= '<p>No reports to show.</p>';
    } else {
        $html .= '<table><thead><tr><th>#</th><th>Type</th><th>Reported By</th><th>Item</th><th>Description</th><th>Status</th><th>Admin Comment</th><th>Date</th></tr></thead><tbody>';
        foreach ($reports as $i => $r) {
            $html .= '<tr><td>'.($i+1).'</td><td>'.htmlspecialchars($r['report_type']).'</td><td>'.htmlspecialchars($r['username'] ?? 'Unknown').'</td><td>'.htmlspecialchars($r['item_title'] ?? 'N/A').'</td><td>'.nl2br(htmlspecialchars($r['description'])).'</td><td>'.htmlspecialchars($r['status']).'</td><td>'.nl2br(htmlspecialchars($r['admin_comment'])).'</td><td>'.htmlspecialchars($r['date_created']).'</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</div>';

    $html .= '<hr><div style="font-size:0.85rem;color:#666">Generated by Lost & Found System</div>';
    $html .= '</body></html>';

    // Output PDF if dompdf exists
    $filename_base = "full_report_" . date('Ymd_His');
    $pdf_generated = false;
    if (file_exists(__DIR__ . '/dompdf/autoload.inc.php')) {
        try {
            require_once __DIR__ . '/dompdf/autoload.inc.php';
            // instantiate without "use"
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfString = $dompdf->output();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.safe_filename($filename_base).'.pdf"');
            echo $pdfString;
            $pdf_generated = true;
            exit();
        } catch (Exception $e) {
            // fallback to HTML
            $pdf_generated = false;
        }
    }

    if (!$pdf_generated) {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.safe_filename($filename_base).'.html"');
        echo $html;
        exit();
    }
}

// -------------------- Handle report actions (resolve/delete) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id']) && !isset($_POST['export_type']) && !isset($_POST['export_full_report'])) {
    $reportId = intval($_POST['report_id']);
    $action = $_POST['action'];

    if ($action === 'resolve') {
        $stmt = $conn->prepare("UPDATE reports SET status='resolved' WHERE id=?");
        if (!$stmt) die("Resolve Prepare failed: ".$conn->error);
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM reports WHERE id=?");
        if (!$stmt) die("Delete Prepare failed: ".$conn->error);
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: reports.php");
    exit();
}

// -------------------- Page data (summary, filters, queries) --------------------
// Summary
$summary_res = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM items WHERE type='Lost' AND status != 'rejected') AS total_lost,
        (SELECT COUNT(*) FROM items WHERE type='Found' AND status != 'rejected') AS total_found,
        (SELECT COUNT(*) FROM users WHERE status='active') AS total_active,
        (SELECT COUNT(*) FROM users WHERE status='inactive') AS total_inactive
");
if (!$summary_res) die("Summary Query failed: ".$conn->error);
$summary = $summary_res->fetch_assoc();

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-t');
$category   = $_GET['category'] ?? '%';
if (empty($category)) $category = '%';

// Fetch categories for dropdown
$category_result = $conn->query("SELECT DISTINCT category FROM items");
$categories = [];
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Fetch Lost Items
$stmt = $conn->prepare("
    SELECT i.id, i.title AS item_name, i.category, i.status, i.`date` AS date_lost,
           CONCAT(u.first_name,' ',u.last_name) AS reported_by
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.type='Lost' AND i.status != 'rejected' AND i.`date` BETWEEN ? AND ? AND i.category LIKE ?
    ORDER BY i.`date` DESC
");
if (!$stmt) die("Lost Items Prepare failed: ".$conn->error);
$stmt->bind_param("sss", $start_date, $end_date, $category);
$stmt->execute();
$lost_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Found Items
$stmt = $conn->prepare("
    SELECT i.id, i.title AS item_name, i.category, i.status, i.`date` AS date_found,
           CONCAT(u.first_name,' ',u.last_name) AS reported_by
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    WHERE i.type='Found' AND i.status != 'rejected' AND i.`date` BETWEEN ? AND ? AND i.category LIKE ?
    ORDER BY i.`date` DESC
");
if (!$stmt) die("Found Items Prepare failed: ".$conn->error);
$stmt->bind_param("sss", $start_date, $end_date, $category);
$stmt->execute();
$found_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch User Activity
$res = $conn->query("
    SELECT CONCAT(u.first_name,' ',u.last_name) AS username,
           COUNT(CASE WHEN i.type='Lost' THEN 1 END) AS lost_items_uploaded,
           COUNT(CASE WHEN i.type='Found' THEN 1 END) AS found_items_uploaded,
           COUNT(c.id) AS comments_made
    FROM users u
    LEFT JOIN items i ON i.user_id = u.id
    LEFT JOIN comments c ON c.user_id = u.id
    GROUP BY u.id
    ORDER BY lost_items_uploaded DESC
");
if (!$res) die("User Activity Query failed: ".$conn->error);
$user_activity = $res->fetch_all(MYSQLI_ASSOC);

// Fetch Reports
$res = $conn->query("
    SELECT r.id, r.report_type, r.description, r.status, r.admin_comment, r.date_created,
           CONCAT(u.first_name,' ',u.last_name) AS username,
           i.title AS item_title, i.type AS item_type
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN items i ON r.item_id = i.id
    ORDER BY r.date_created DESC
");
if (!$res) die("Reports Query failed: ".$conn->error);
$reports = $res->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reports Dashboard</title>
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background:#121212; color:#fff; }
nav { background:#1e1e1e; display:flex; padding:1rem; gap:1rem; }
nav a { color:#fff; text-decoration:none; font-weight:600; }
nav a.active { color:#4cc9f0; }
.container { padding:2rem; }
.cards { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2rem; }
.card-summary { flex:1; background:#1e1e1e; padding:1rem; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.5); text-align:center; }
.card-summary h2 { margin:0; color:#4cc9f0; font-size:2rem; }
.card-summary p { margin:0; font-size:0.9rem; color:#bbb; }
.tabs { display:flex; gap:1rem; margin-bottom:1rem; flex-wrap:wrap; }
.tab { cursor:pointer; padding:8px 12px; border-radius:6px; background:#1e1e1e; color:#fff; font-weight:700; }
.tab.active { background:#4cc9f0; color:#000; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.table { width:100%; border-collapse:collapse; margin-top:1rem; }
.table th, .table td { padding:8px; border:1px solid #333; text-align:left; font-size:0.9rem; }
.status { padding:4px 8px; border-radius:6px; font-weight:700; display:inline-block; }
.status-pending { background:#ffca28; color:#000; }
.status-resolved { background:#4caf50; color:#fff; }
button { padding:6px 10px; border:0; border-radius:6px; cursor:pointer; font-weight:700; }
button.resolve { background:#4caf50; color:#fff; }
button.delete { background:#ff4444; color:#fff; }
button.export { background:#4cc9f0; color:#000; }
select { padding:5px; border-radius:5px; border:none; font-weight:700; background:#1e1e1e; color:#fff; }
input[type="date"]{ padding:6px; border-radius:6px; border:none; background:#1b1b1b; color:#fff; }
@media(max-width:768px){ .cards { flex-direction:column; } form { flex-direction:column; gap:0.5rem; } }

/* Modal styles for full report */
#reportModal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:9999; }
#reportModal .modal-box { background:#1e1e1e; padding:20px; width:480px; border-radius:10px; box-shadow:0 6px 30px rgba(0,0,0,0.6); color:#fff; }
#reportModal label { display:block; margin-top:8px; font-weight:700; }
#reportModal textarea { width:100%; height:90px; padding:8px; border-radius:6px; border:none; background:#121212; color:#fff; resize:vertical; }
#reportModal .modal-actions { margin-top:12px; display:flex; gap:8px; justify-content:flex-end; }
</style>
</head>
<body>

<nav>
    <a href="Admin_dashboard.php">Dashboard</a>
    <a href="users.php">User Management</a>
    <a href="content.php">Post Moderation</a>
    <a href="reports.php" class="active">Reports</a>
    <a href="settings.php">System Settings</a>
</nav>

<div class="container">

    <!-- Summary Cards -->
    <div class="cards">
        <div class="card-summary"><h2><?= $summary['total_lost'] ?></h2><p>Total Lost Items</p></div>
        <div class="card-summary"><h2><?= $summary['total_found'] ?></h2><p>Total Found Items</p></div>
        <div class="card-summary"><h2><?= $summary['total_active'] ?></h2><p>Total Active Users</p></div>
        <div class="card-summary"><h2><?= $summary['total_inactive'] ?></h2><p>Total Inactive Users</p></div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" data-tab="lost">Lost Items</div>
        <div class="tab" data-tab="found">Found Items</div>
        <div class="tab" data-tab="users">User Activity</div>
        <div class="tab" data-tab="reports">Reports</div>
    </div>

    <!-- Filter Form -->
    <form method="get" style="margin-bottom:1.5rem; display:flex; gap:1rem; flex-wrap:wrap;">
        <label>Start Date: <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"></label>
        <label>End Date: <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"></label>
        <label>Category:
            <select name="category">
                <option value="%">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Filter</button>
    </form>

    <!-- Lost Items Table -->
    <div class="tab-content active" id="lost">
        <table class="table">
            <tr><th>Item</th><th>Category</th><th>Reported By</th><th>Date</th><th>Status</th></tr>
            <?php foreach($lost_items as $li): ?>
            <tr>
                <td><?= htmlspecialchars($li['item_name']) ?></td>
                <td><?= htmlspecialchars($li['category']) ?></td>
                <td><?= htmlspecialchars($li['reported_by']) ?></td>
                <td><?= htmlspecialchars($li['date_lost']) ?></td>
                <td><span class="status <?= strtolower($li['status'])==='pending'?'status-pending':'status-resolved' ?>"><?= htmlspecialchars($li['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Export Dropdown -->
        <form method="post" id="exportFormLost" style="margin-top:0.5rem;">
            <input type="hidden" name="type" value="Lost">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
            <select name="export_type" onchange="submitExportForm('Lost')">
                <option value="">Export Items</option>
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
            </select>
        </form>
    </div>

    <!-- Found Items Table -->
    <div class="tab-content" id="found">
        <table class="table">
            <tr><th>Item</th><th>Category</th><th>Reported By</th><th>Date</th><th>Status</th></tr>
            <?php foreach($found_items as $fi): ?>
            <tr>
                <td><?= htmlspecialchars($fi['item_name']) ?></td>
                <td><?= htmlspecialchars($fi['category']) ?></td>
                <td><?= htmlspecialchars($fi['reported_by']) ?></td>
                <td><?= htmlspecialchars($fi['date_found']) ?></td>
                <td><span class="status <?= strtolower($fi['status'])==='pending'?'status-pending':'status-resolved' ?>"><?= htmlspecialchars($fi['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Export Dropdown -->
        <form method="post" id="exportFormFound" style="margin-top:0.5rem;">
            <input type="hidden" name="type" value="Found">
            <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
            <select name="export_type" onchange="submitExportForm('Found')">
                <option value="">Export Items</option>
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
            </select>
        </form>
    </div>

    <!-- User Activity Table -->
    <div class="tab-content" id="users">
        <table class="table">
            <tr><th>Username</th><th>Lost Items Uploaded</th><th>Found Items Uploaded</th><th>Comments Made</th></tr>
            <?php foreach($user_activity as $ua): ?>
            <tr>
                <td><?= htmlspecialchars($ua['username']) ?></td>
                <td><?= $ua['lost_items_uploaded'] ?></td>
                <td><?= $ua['found_items_uploaded'] ?></td>
                <td><?= $ua['comments_made'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Export Dropdown for User Activity -->
        <form method="post" id="exportFormUserActivity" style="margin-top:0.5rem;">
            <input type="hidden" name="type" value="UserActivity">
            <select name="export_type" onchange="submitExportForm('UserActivity')">
                <option value="">Export User Activity</option>
                <option value="csv">CSV</option>
                <option value="excel">Excel</option>
            </select>
        </form>
    </div>

    <!-- Reports Table -->
    <div class="tab-content" id="reports">
        <table class="table">
            <tr><th>Report Type</th><th>Reported By</th><th>Item</th><th>Description</th><th>Status</th><th>Admin Comment</th><th>Date</th><th>Actions</th></tr>
            <?php foreach($reports as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['report_type']) ?></td>
                <td><?= htmlspecialchars($r['username'] ?: 'Unknown') ?></td>
                <td><?= htmlspecialchars($r['item_title'] ?: 'Unknown') ?></td>
                <td><?= nl2br(htmlspecialchars($r['description'])) ?></td>
                <td><span class="status <?= strtolower($r['status'])==='pending'?'status-pending':'status-resolved' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                <td><?= nl2br(htmlspecialchars($r['admin_comment'])) ?></td>
                <td><?= htmlspecialchars($r['date_created']) ?></td>
                <td>
                    <?php if(strtolower($r['status'])==='pending'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <button type="submit" name="action" value="resolve" class="resolve">Resolve</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete permanently?');">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <button type="submit" name="action" value="delete" class="delete">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div style="margin-top:12px;">
            <button class="export" onclick="openReportModal()" title="Export full report including admin description & comment">Export Full Report</button>
        </div>
    </div>

</div>

<!-- Full Report Modal -->
<div id="reportModal" role="dialog" aria-modal="true">
    <div class="modal-box">
        <h3>Create Full Report</h3>
        <form method="post" onsubmit="return submitFullReportForm(this);">
            <input type="hidden" name="export_full_report" value="1">
            <label for="admin_description">Description (appears at top of report)</label>
            <textarea name="admin_description" id="admin_description" placeholder="Optional description..."></textarea>
            <label for="admin_comment">Admin Comment (optional)</label>
            <textarea name="admin_comment" id="admin_comment" placeholder="Optional admin comment..."></textarea>
            <div class="modal-actions">
                <button type="button" onclick="closeReportModal()" style="background:#ff4444;color:#fff;padding:8px 12px;border-radius:6px;border:none;">Cancel</button>
                <button type="submit" style="background:#4cc9f0;color:#000;padding:8px 12px;border-radius:6px;border:none;">Download Full Report</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
const tabs = document.querySelectorAll('.tab');
const contents = document.querySelectorAll('.tab-content');
tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        tabs.forEach(t=>t.classList.remove('active'));
        contents.forEach(c=>c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
    });
});

// Export dropdown handler
function submitExportForm(tabType){
    let form, exportType;
    if(tabType==='Lost'){
        form = document.getElementById('exportFormLost');
        exportType = form.querySelector('select[name="export_type"]').value;
    } else if(tabType==='Found'){
        form = document.getElementById('exportFormFound');
        exportType = form.querySelector('select[name="export_type"]').value;
    } else if(tabType==='UserActivity'){
        form = document.getElementById('exportFormUserActivity');
        exportType = form.querySelector('select[name="export_type"]').value;
    } else {
        return;
    }
    if(!exportType) return;
    // set action to this same file; server handles download based on POST fields
    form.action = 'reports.php';
    form.submit();
}

// Modal functions
function openReportModal() {
    document.getElementById('reportModal').style.display = 'flex';
}
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}
function submitFullReportForm(form) {
    // nothing special needed client-side; server will generate file
    return true;
}
</script>

</body>
</html>
