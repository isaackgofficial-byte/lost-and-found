<?php
include("session_check.php");
include("db_connect.php");

$userId = $_SESSION['user_id'];

// Fetch user items
$stmt = $conn->prepare("SELECT id, title, description, category, type, status, image, date_posted, admin_comment 
                        FROM items 
                        WHERE user_id = ? 
                        ORDER BY date_posted DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Items - Lost & Found</title>
<style>
/* ===== Base ===== */
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #121212;
    color: #f1f1f1;
    transition: all 0.3s;
}
body.light-mode {
    background-color: #f4f4f4;
    color: #121212;
}

/* ===== Navbar ===== */
.navbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:1rem 2rem;
    background-color: var(--navbar-bg);
    box-shadow:0 2px 6px rgba(0,0,0,0.4);
    transition: background 0.3s;
}
:root {
    --navbar-bg: #1e1e1e;
    --card-bg: #1e1e1e;
    --text-color: #f1f1f1;
    --accent-color: #4cc9f0;
    --btn-bg: #4cc9f0;
    --no-items: #888;
}
body.light-mode {
    --navbar-bg: #ffffff;
    --card-bg: #ffffff;
    --text-color: #121212;
    --accent-color: #1976d2;
    --btn-bg: #1976d2;
    --no-items: #555;
}
.navbar .logo { font-size:1.3rem; color: var(--accent-color); font-weight:bold; }
.nav-links a { margin-right:1.2rem; text-decoration:none; color: var(--text-color); transition:0.3s; }
.nav-links a:hover, .nav-links a.active { color: var(--accent-color); }
.nav-actions { display:flex; align-items:center; }
.nav-actions .btn { background:none; border:1px solid var(--btn-bg); padding:0.5rem 1rem; color: var(--btn-bg); border-radius:6px; text-decoration:none; }
.theme-toggle { background:none; border:none; color: var(--text-color); font-size:1.2rem; margin-right:1rem; cursor:pointer; }

/* ===== Section ===== */
.form-section { padding:2rem; max-width:1000px; margin:auto; }
.items-container { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.5rem; margin-top:1.5rem; }

/* ===== Item Card ===== */
.item-card {
    background-color: var(--card-bg);
    border-radius:10px;
    overflow:hidden;
    box-shadow:0 4px 10px rgba(0,0,0,0.3);
    position:relative;
    transition: all 0.3s;
}
.item-card:hover { transform:translateY(-4px); }
.item-image { width:100%; height:200px; object-fit:cover; cursor:pointer; }
.item-details { padding:1rem; }
.item-details h3 { color: var(--accent-color); margin-bottom:0.5rem; }
.item-details p { font-size:0.9rem; margin-bottom:0.4rem; }
.status-btn { padding:0.4rem 0.8rem; border-radius:5px; font-size:0.85rem; font-weight:500; border:none; cursor:pointer; margin-top:0.5rem; }
.status-pending { background:#ffca28; color:#000; }
.status-approved { background:#2196f3; color:#fff; }
.status-rejected { background:#f44336; color:#fff; }
.delete-btn { position:absolute; top:10px; right:10px; background:#f44336; border:none; color:#fff; padding:0.3rem 0.6rem; border-radius:5px; cursor:pointer; font-size:0.8rem; z-index:5; }

/* ===== No items ===== */
.no-items { text-align:center; color: var(--no-items); padding:2rem; grid-column:1 / -1; }

/* ===== Popup ===== */
.popup-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:9999; }
.popup-content { background-color: var(--card-bg); color: var(--text-color); padding:1.5rem; border-radius:8px; max-width:400px; width:90%; text-align:center; position:relative; }
.popup-content img { max-width:100%; border-radius:8px; margin-bottom:1rem; }
.close-popup { position:absolute; top:5px; right:10px; cursor:pointer; font-size:1.2rem; border:none; background:none; color: var(--text-color); }

/* ===== Footer ===== */
footer { text-align:center; padding:1rem; margin-top:2rem; font-size:0.9rem; color: var(--no-items); }

/* ===== Responsive ===== */
@media(max-width:768px){
    .items-container { grid-template-columns:1fr; }
    .navbar { flex-direction:column; align-items:flex-start; gap:0.5rem; }
    .nav-actions { margin-top:0.5rem; }
}
</style>
</head>
<body>
<nav class="navbar">
    <div class="logo">🔒 Lost & Found</div>
    <div class="nav-links">
        <a href="ui.php">Dashboard</a>
        <a href="post.php">Post Item</a>
        <a href="items.php" class="active">My Items</a>
        <a href="profile.php">Profile</a>
    </div>
    <div class="nav-actions">
        <button class="theme-toggle" id="themeToggle">🌙</button>
        <a href="logout.php" class="btn">Logout</a>
    </div>
</nav>

<section class="form-section">
    <h2>My Posted Items</h2>
    <p>Below are all the items you have posted. Check if they’ve been approved by Admin.</p>

    <div class="items-container" id="itemsContainer">
        <?php if (count($items) === 0): ?>
            <p class="no-items">You haven’t posted any items yet.</p>
        <?php else: ?>
            <?php foreach ($items as $item): 
                $statusClass = $item['status']==='pending'?'status-pending':($item['status']==='approved'?'status-approved':'status-rejected');
                $imagePath = !empty($item['image']) ? htmlspecialchars($item['image']) : 'images/default.png';
            ?>
                <div class="item-card" id="item-<?= $item['id'] ?>">
                    <button class="delete-btn" onclick="deleteItem(<?= $item['id'] ?>)">Delete</button>
                    <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image" onclick="enlargeImage('<?= $imagePath ?>')">
                    <div class="item-details">
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <p><strong>Category:</strong> <?= htmlspecialchars($item['category']) ?></p>
                        <p><strong>Type:</strong> <?= htmlspecialchars($item['type']) ?></p>
                        <p><?= htmlspecialchars($item['description']) ?></p>
                        <p><strong>Posted on:</strong> <?= htmlspecialchars($item['date_posted']) ?></p>
                        <button class="status-btn <?= $statusClass ?>" onclick="showAdminComment(<?= $item['id'] ?>)">
                            <?= ucfirst($item['status']) ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<div class="popup-overlay" id="popupOverlay">
    <div class="popup-content" id="popupContent">
        <button class="close-popup" onclick="closePopup()">×</button>
        <div id="popupBody"></div>
    </div>
</div>

<footer>© 2025 Lost & Found System | All Rights Reserved</footer>

<script>
// ===== Unified Theme Toggle =====
const themeToggle = document.getElementById("themeToggle");
if(localStorage.getItem('theme')==='light'){ document.body.classList.add('light-mode'); themeToggle.textContent='☀️'; }
else{ document.body.classList.remove('light-mode'); themeToggle.textContent='🌙'; }

themeToggle.addEventListener('click', () => {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('theme', isLight?'light':'dark');
    themeToggle.textContent = isLight?'☀️':'🌙';
});

// ===== Delete Item =====
function deleteItem(id){
    if(!confirm("Are you sure you want to delete this item?")) return;
    fetch(`delete_item.php?id=${id}`, {method:'GET'})
        .then(res=>res.text())
        .then(data=>{ if(data.trim()==='success') document.getElementById(`item-${id}`).remove(); else alert(data||"Failed to delete item."); })
        .catch(()=>alert("Error deleting item."));
}

// ===== Admin Comment =====
function showAdminComment(itemId){
    fetch(`get_comment.php?item_id=${itemId}`)
        .then(res=>res.json())
        .then(data=>{ 
            document.getElementById("popupBody").innerHTML = `<p>${data.comment || "No comment from Admin."}</p>`;
            document.getElementById("popupOverlay").style.display = "flex";
        })
        .catch(()=>alert("Error fetching comment."));
}

function closePopup(){
    document.getElementById("popupOverlay").style.display="none";
    document.getElementById("popupBody").innerHTML="";
}

// ===== Enlarge Image =====
function enlargeImage(src){
    document.getElementById("popupBody").innerHTML = `<img src="${src}">`;
    document.getElementById("popupOverlay").style.display="flex";
}
</script>
</body>
</html>
