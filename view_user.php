<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}
$user_id = intval($_GET['id']);

// Fetch user info
$stmt = $conn->prepare("SELECT id, username, first_name, last_name, email, phone, profile_image, status, date_created FROM users WHERE id=?");
if (!$stmt) die("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) die("User not found.");

// Pagination and search for posts
$items_per_page = 5;
$item_page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$item_offset = ($item_page-1)*$items_per_page;
$item_search = isset($_GET['search']) ? $_GET['search'] : '';
$item_type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Count items
$item_count_query = "SELECT COUNT(*) as total FROM items WHERE user_id=?";
$params = [$user_id]; $types = "i";
if ($item_search) { $item_count_query .= " AND title LIKE ?"; $types.="s"; $params[]="%$item_search%"; }
if ($item_type_filter) { $item_count_query .= " AND type=?"; $types.="s"; $params[]=$item_type_filter; }
$stmt = $conn->prepare($item_count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Fetch items
$item_query = "SELECT id, title, type, date, image FROM items WHERE user_id=?";
$params = [$user_id]; $types="i";
if ($item_search) { $item_query .= " AND title LIKE ?"; $types.="s"; $params[]="%$item_search%"; }
if ($item_type_filter) { $item_query .= " AND type=?"; $types.="s"; $params[]=$item_type_filter; }
$item_query .= " ORDER BY date DESC LIMIT ? OFFSET ?";
$types.="ii"; $params[]=$items_per_page; $params[]=$item_offset;
$stmt = $conn->prepare($item_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$user_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pagination and search for activity
$activity_per_page = 5;
$activity_page = isset($_GET['activity_page']) ? max(1,intval($_GET['activity_page'])) : 1;
$activity_offset = ($activity_page-1)*$activity_per_page;
$activity_search = isset($_GET['activity_search']) ? $_GET['activity_search'] : '';

// Count activity
$activity_count_query = "SELECT COUNT(*) as total FROM user_activity WHERE user_id=?";
$params_act = [$user_id]; $types_act="i";
if ($activity_search) { $activity_count_query .= " AND action LIKE ?"; $types_act.="s"; $params_act[]="%$activity_search%"; }
$stmt = $conn->prepare($activity_count_query);
$stmt->bind_param($types_act, ...$params_act);
$stmt->execute();
$total_activity = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Fetch activity
$activity_query = "SELECT action, details, created_at FROM user_activity WHERE user_id=?";
$params_act = [$user_id]; $types_act="i";
if ($activity_search) { $activity_query .= " AND action LIKE ?"; $types_act.="s"; $params_act[]="%$activity_search%"; }
$activity_query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types_act.="ii"; $params_act[]=$activity_per_page; $params_act[]=$activity_offset;
$stmt = $conn->prepare($activity_query);
$stmt->bind_param($types_act, ...$params_act);
$stmt->execute();
$user_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Profile - Admin</title>
<link rel="stylesheet" href="ui.css">
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
.container { max-width:1200px; margin:2rem auto; display:flex; gap:2rem; flex-wrap:wrap; }
.left-col { flex:1 1 300px; background:white; padding:2rem; border-radius:12px; box-shadow:0 6px 15px rgba(0,0,0,0.1); text-align:center; }
.right-col { flex:2 1 600px; display:flex; flex-direction:column; gap:2rem; }
.back-btn { margin-bottom:1rem; padding:8px 14px; border:none; border-radius:6px; background:#3399ff; color:white; cursor:pointer; }
.profile img { width:150px; height:150px; border-radius:50%; border:4px solid #3399ff; object-fit:cover; margin-bottom:1rem; cursor:pointer; }
.profile h2 { margin:0; color:#003366; font-size:28px; }
.profile p { margin:4px 0; color:#333; }
.badge { padding:6px 14px; border-radius:20px; color:white; font-weight:bold; font-size:14px; }
.active { background:#28a745; }
.inactive { background:#dc3545; }

.section { background:#fff; padding:1rem; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.05); }
.section h3 { color:#003366; border-bottom:2px solid #3399ff; padding-bottom:5px; margin-bottom:1rem; }
.card { background:#eaf3ff; padding:1rem; border-radius:10px; margin-bottom:10px; display:flex; gap:15px; align-items:center; }
.card img { width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid #ccc; cursor:pointer; }
.card a { text-decoration:none; color:#003366; font-weight:bold; }
.card p { margin:0; color:#555; font-size:14px; }
.search-bar { margin-bottom:10px; display:flex; gap:10px; flex-wrap:wrap; }
.search-bar input, .search-bar select { padding:6px 10px; border-radius:6px; border:1px solid #ccc; flex:1; min-width:150px; }
.search-bar button { padding:6px 12px; border-radius:6px; border:none; background:#3399ff; color:white; cursor:pointer; }
.pagination a { padding:5px 10px; background:#3399ff; color:white; text-decoration:none; border-radius:5px; margin-right:5px; }
.pagination a.active { background:#28a745; }

/* Modal */
#imageModal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:1000; }
#imageModal img { max-width:90%; max-height:90%; border-radius:12px; box-shadow:0 0 20px rgba(0,0,0,0.5); }

@media(max-width:900px){ .container { flex-direction:column; } .left-col, .right-col { flex:1 1 100%; } }
</style>
</head>
<body>
<div class="container">
    <!-- Left Column -->
    <div class="left-col">
        <button class="back-btn" onclick="window.history.back()">← Back</button>
        <div class="profile">
            <img id="profileImage" src="<?= $user['profile_image'] ? 'uploads/'.$user['profile_image'] : 'default_user.png' ?>" alt="Profile Image">
            <h2><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h2>
            <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?: 'Not Provided') ?></p>
            <p><strong>Joined:</strong> <?= $user['date_created'] ?></p>
            <span class="badge <?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span>
        </div>
    </div>

    <!-- Right Column -->
    <div class="right-col">
        <!-- Activity Logs -->
        <div class="section">
            <h3>User Activity Logs</h3>
            <form method="GET" class="search-bar">
                <input type="hidden" name="id" value="<?= $user_id ?>">
                <input type="text" name="activity_search" placeholder="Search activity..." value="<?= htmlspecialchars($activity_search) ?>">
                <button type="submit">Search</button>
            </form>
            <?php if($user_activity): foreach($user_activity as $act): ?>
                <div class="card">
                    <div>
                        <p><strong><?= htmlspecialchars($act['action']) ?></strong></p>
                        <p><?= htmlspecialchars($act['details']) ?></p>
                        <p style="font-size:12px;color:#777;"><?= $act['created_at'] ?></p>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="card"><p>No activity yet.</p></div>
            <?php endif; ?>
            <div class="pagination">
                <?php for($i=1;$i<=ceil($total_activity/$activity_per_page);$i++): ?>
                    <a href="?id=<?= $user_id ?>&activity_page=<?= $i ?>&activity_search=<?= htmlspecialchars($activity_search) ?>" class="<?= $i==$activity_page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <!-- User Posts -->
        <div class="section">
            <h3>User Posts</h3>
            <form method="GET" class="search-bar">
                <input type="hidden" name="id" value="<?= $user_id ?>">
                <input type="text" name="search" placeholder="Search posts..." value="<?= htmlspecialchars($item_search) ?>">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="lost" <?= $item_type_filter=='lost'?'selected':'' ?>>Lost</option>
                    <option value="found" <?= $item_type_filter=='found'?'selected':'' ?>>Found</option>
                </select>
                <button type="submit">Search</button>
            </form>

            <?php if($user_items): foreach($user_items as $item): ?>
                <div class="card">
                    <?php if($item['image']): ?>
                        <img class="post-img" src="<?= htmlspecialchars($item['image']) ?>" alt="Item Image">
                    <?php endif; ?>
                    <div>
                        <a href="view_item.php?id=<?= $item['id'] ?>"><?= htmlspecialchars($item['title']) ?></a>
                        <p><?= ucfirst($item['type']) ?> - <?= $item['date'] ?></p>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="card"><p>No posts yet.</p></div>
            <?php endif; ?>

            <div class="pagination">
                <?php for($i=1;$i<=ceil($total_items/$items_per_page);$i++): ?>
                    <a href="?id=<?= $user_id ?>&page=<?= $i ?>&search=<?= htmlspecialchars($item_search) ?>&type=<?= htmlspecialchars($item_type_filter) ?>" class="<?= $i==$item_page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal">
    <img id="modalImg" src="">
</div>

<script>
const profileImage = document.getElementById('profileImage');
const postImages = document.querySelectorAll('.post-img');
const imageModal = document.getElementById('imageModal');
const modalImg = document.getElementById('modalImg');

// Profile image click
profileImage.addEventListener('click', () => {
    modalImg.src = profileImage.src;
    imageModal.style.display = 'flex';
});

// Post image click
postImages.forEach(img=>{
    img.addEventListener('click', ()=>{
        modalImg.src = img.src;
        imageModal.style.display = 'flex';
    });
});

// Close modal
imageModal.addEventListener('click', ()=>{ imageModal.style.display='none'; });
</script>
</body>
</html>
