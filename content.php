<?php
session_start();
include('db_connect.php');

// Ensure Admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle actions: approve / reject / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['item_id'])) {
    $itemId = intval($_POST['item_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE items SET status='Approved', admin_comment=NULL WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (approve): " . $conn->error);
        }

    } elseif ($action === 'reject') {
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        $stmt = $conn->prepare("UPDATE items SET status='Rejected', admin_comment=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("si", $comment, $itemId);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (reject): " . $conn->error);
        }

    } elseif ($action === 'delete') {
        // Permanent deletion
        $stmt = $conn->prepare("DELETE FROM items WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $stmt->close();
        } else {
            die("Prepare failed (delete): " . $conn->error);
        }
    }

    // Redirect to avoid resubmission
    header("Location: content.php");
    exit();
}

// Fetch ALL items (Pending, Approved, Rejected, etc.)
$stmt = $conn->prepare("
    SELECT i.id, i.title, i.description, i.category, i.type, i.status, i.image, i.date_posted,
           i.admin_comment,
           CONCAT(u.first_name, ' ', u.last_name) AS username
    FROM items i
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY i.date_posted DESC
");
if (!$stmt) {
    die("Prepare failed (fetch items): " . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Post Moderation - Admin</title>
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background:#121212; color:#fff; }
nav { background:#1e1e1e; display:flex; padding:1rem; gap:1rem; }
nav a { color:#fff; text-decoration:none; font-weight:600; }
nav a.active { color:#4cc9f0; }

.container { padding:2rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.5rem; }
.card { background:#1e1e1e; border-radius:10px; padding:1rem; box-shadow:0 2px 10px rgba(0,0,0,0.5); display:flex; flex-direction:column; }
.card img { width:100%; height:200px; object-fit:cover; border-radius:6px; cursor:pointer; }
.card h3 { color:#4cc9f0; margin:0.5rem 0; }
.card p { margin:0.3rem 0; color:#ddd; font-size:0.95rem; }

.status { padding:6px 10px; border-radius:6px; font-weight:700; display:inline-block; margin-top:8px; font-size:0.85rem; }
.status-pending { background:#ffca28; color:#000; }
.status-approved { background:#4caf50; color:#fff; }
.status-rejected { background:#e63946; color:#fff; }

/* action area */
.actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
button, .action-form button { padding:8px 12px; border:0; border-radius:6px; cursor:pointer; font-weight:700; }
button.approve { background:#4caf50; color:#fff; }
button.reject { background:#f39c12; color:#fff; }
button.delete { background:#dc3545; color:#fff; }

/* modal */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); align-items:center; justify-content:center; z-index:999; }
.modal .box { background:#1e1e1e; padding:18px; border-radius:8px; width:320px; }
.modal textarea { width:100%; height:90px; margin-top:8px; padding:8px; border-radius:6px; border:1px solid #333; background:#0f0f10; color:#fff; }
.modal .row { display:flex; gap:8px; margin-top:10px; }

/* image modal */
#imageModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); justify-content:center; align-items:center; z-index:1000; }
#imageModal img { max-width:90%; max-height:90%; border-radius:8px; }
#imageModal span { position:absolute; top:20px; right:30px; font-size:30px; color:#fff; cursor:pointer; }

/* responsiveness */
@media (max-width:480px) {
  .container { grid-template-columns: 1fr; padding:1rem; }
  .card img { height:160px; }
}
</style>
</head>
<body>

<nav>
    <a href="Admin_dashboard.php">Dashboard</a>
    <a href="users.php">User Management</a>
    <a href="content.php" class="active">Post Moderation</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">System Settings</a>
</nav>

<div class="container">
    <?php if (empty($items)): ?>
        <p style="color:#bbb;">No items found.</p>
    <?php else: ?>
        <?php foreach ($items as $item): 
            $status = isset($item['status']) ? $item['status'] : 'Pending';
            $statusKey = strtolower($status);
        ?>
        <div class="card" id="item-<?= $item['id'] ?>">
            <img src="<?= htmlspecialchars($item['image'] ?: 'images/default.png') ?>" 
                 alt="<?= htmlspecialchars($item['title']) ?>" onclick="openImage(this.src)">
            <h3><?= htmlspecialchars($item['title']) ?></h3>

            <p><strong>User:</strong> <?= htmlspecialchars($item['username'] ?: 'Unknown') ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($item['type']) ?></p>
            <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
            <p style="margin-top:8px; color:#bbb;"><strong>Posted:</strong> <?= htmlspecialchars($item['date_posted']) ?></p>

            <?php if ($statusKey === 'pending'): ?>
                <span class="status status-pending"><?= htmlspecialchars($status) ?></span>
            <?php elseif ($statusKey === 'approved'): ?>
                <span class="status status-approved"><?= htmlspecialchars($status) ?></span>
            <?php elseif ($statusKey === 'rejected'): ?>
                <span class="status status-rejected"><?= htmlspecialchars($status) ?></span>
            <?php else: ?>
                <span class="status"><?= htmlspecialchars($status) ?></span>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="actions">
                <?php if ($statusKey === 'pending'): ?>
                    <!-- Approve -->
                    <form class="action-form" method="post" style="margin:0;">
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <button type="submit" name="action" value="approve" class="approve">Approve</button>
                    </form>

                    <!-- Reject -->
                    <button class="reject" type="button" onclick="openRejectModal(<?= $item['id'] ?>)">Reject</button>
                <?php endif; ?>

                <!-- Delete ALWAYS visible -->
                <form class="action-form" method="post" style="margin:0;" onsubmit="return confirm('Are you sure you want to DELETE this item permanently?');">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <button type="submit" name="action" value="delete" class="delete">Delete</button>
                </form>
            </div>

            <?php if (!empty($item['admin_comment'])): ?>
                <p style="margin-top:10px; color:#e0b4b4;"><strong>Admin comment:</strong> <?= nl2br(htmlspecialchars($item['admin_comment'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Reject modal -->
<div id="rejectModal" class="modal" aria-hidden="true">
    <div class="box">
        <h3 style="margin:0 0 10px 0; color:#fff;">Reject Item</h3>
        <form method="post" id="rejectForm">
            <input type="hidden" name="item_id" id="reject_item_id" value="">
            <textarea name="comment" id="reject_comment" placeholder="Enter reason for rejection..." required></textarea>

            <div class="row">
                <button type="submit" name="action" value="reject" class="reject" style="flex:1;">Submit Rejection</button>
                <button type="button" onclick="closeRejectModal()" style="flex:1; background:#444; color:#fff; border:none; border-radius:6px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Image modal -->
<div id="imageModal" onclick="closeImage()">
    <span>&times;</span>
    <img src="" alt="Preview">
</div>

<script>
function openRejectModal(id){
    document.getElementById('reject_item_id').value = id;
    document.getElementById('reject_comment').value = '';
    const modal = document.getElementById('rejectModal');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
}
function closeRejectModal(){
    const modal = document.getElementById('rejectModal');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
}

// Image modal
function openImage(src){
    document.querySelector('#imageModal img').src = src;
    document.getElementById('imageModal').style.display = 'flex';
}
function closeImage(){
    document.getElementById('imageModal').style.display = 'none';
}

// Close reject modal on outside click
document.getElementById('rejectModal').addEventListener('click', function(e){
    if (e.target === this) closeRejectModal();
});

// Close image modal on click
document.getElementById('imageModal').addEventListener('click', function(e){
    if (e.target === this || e.target.tagName === 'SPAN') closeImage();
});
</script>

</body>
</html>
