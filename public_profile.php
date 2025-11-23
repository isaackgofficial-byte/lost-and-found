<?php 
session_start();
include("db_connect.php");

if (!isset($_GET['user_id'])) {
    die("No user specified.");
}

$targetUserId = intval($_GET['user_id']);
$loggedInUser = $_SESSION['user_id'] ?? null;

// ==================== LOAD USER DATA ====================
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, phone, location, bio,
           profile_public, email_public, phone_public, location_public, activity_public,
           profile_image, date_created
    FROM users 
    WHERE id = ?
");
if(!$stmt){
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// ==================== PRIVACY CHECK ====================
if ($user['profile_public'] == 0 && $loggedInUser != $targetUserId) {
    die("<h3>This profile is private.</h3>");
}

// ==================== LOAD USER ACTIVITY ====================
$activity = [];
if ($user['activity_public'] == 1 || $loggedInUser == $targetUserId) {
    $a = $conn->prepare("
        SELECT id, title, type, status, created_at
        FROM activity_log 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    if($a){
        $a->bind_param("i", $targetUserId);
        $a->execute();
        $activity = $a->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?> - Public Profile</title>
    <style>
        body { font-family: Arial; background:#111; color:white; padding:20px; }
        .container { max-width:700px; margin:auto; background:#222; padding:20px; border-radius:10px; }
        .avatar { 
            width:120px; height:120px; border-radius:50%; margin:auto;
            background:#555; display:flex; align-items:center; justify-content:center;
            font-size:40px; overflow:hidden; transition: transform 0.2s;
        }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .avatar:hover { transform: scale(1.1); cursor:pointer; }

        h2,h3 { text-align:center; margin:10px 0; }
        .tabs { display:flex; justify-content:center; gap:10px; margin-top:20px; }
        .tab-btn { padding:8px 16px; background:#333; border:none; border-radius:6px; cursor:pointer; transition:0.2s; color:white; }
        .tab-btn.active { background:#ff9800; color:black; font-weight:bold; }
        .tab-content { display:none; padding:15px; margin-top:15px; background:#333; border-radius:6px; }
        .tab-content.active { display:block; }

        .activity-item { padding:8px 0; border-bottom:1px solid #444; }
        .activity-item:last-child { border-bottom:none; }
        .contact-item { padding:6px 0; border-bottom:1px solid #444; }
        .contact-item:last-child { border-bottom:none; }
        #showMoreActivity { margin-top:10px; background:#ff9800; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; }
    </style>
</head>
<body>
<div class="container">

    <!-- Back Button -->
    <div style="margin-bottom:15px;">
        <button onclick="goBack()" 
            style="background:#ff9800; border:none; padding:8px 16px; 
            border-radius:6px; cursor:pointer; font-weight:bold;">
            ← Back
        </button>
    </div>

    <div class="avatar">
        <?php if ($user['profile_image']): ?>
            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Avatar">
        <?php else: ?>
            <?= strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1)) ?>
        <?php endif; ?>
    </div>

    <h2><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></h2>
    <p style="text-align:center;">Member since <?= date("Y", strtotime($user['date_created'])) ?></p>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="aboutTab">About</button>
        <button class="tab-btn" data-tab="contactTab">Contact Info</button>
        <button class="tab-btn" data-tab="activityTab">Activity</button>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content active" id="aboutTab">
        <p><?= htmlspecialchars($user['bio'] ?: "No bio available.") ?></p>
    </div>

    <div class="tab-content" id="contactTab">
        <?php if($user['email_public'] || $loggedInUser==$targetUserId): ?>
            <div class="contact-item">Email: <?= htmlspecialchars($user['email']) ?></div>
        <?php endif; ?>
        <?php if($user['phone_public'] || $loggedInUser==$targetUserId): ?>
            <div class="contact-item">Phone: <?= htmlspecialchars($user['phone']) ?></div>
        <?php endif; ?>
        <?php if($user['location_public'] || $loggedInUser==$targetUserId): ?>
            <div class="contact-item">Location: <?= htmlspecialchars($user['location']) ?></div>
        <?php endif; ?>
    </div>

    <div class="tab-content" id="activityTab">
        <div id="activityList">
            <?php foreach(array_slice($activity,0,5) as $act): ?>
                <div class="activity-item">
                    <strong><?= htmlspecialchars($act['title']) ?></strong> — <?= htmlspecialchars($act['type'].'/'.$act['status']) ?>
                </div>
            <?php endforeach; ?>
            <?php if(count($activity) > 5): ?>
                <button id="showMoreActivity">Show More</button>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
// Tabs switching
document.querySelectorAll('.tab-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
    });
});

// Expand activity list
const showMoreBtn = document.getElementById('showMoreActivity');
if(showMoreBtn){
    showMoreBtn.addEventListener('click', ()=>{
        const list = document.getElementById('activityList');
        list.innerHTML = '';
        <?php foreach($activity as $act): ?>
            list.innerHTML += `<div class="activity-item"><strong><?= htmlspecialchars($act['title']) ?></strong> — <?= htmlspecialchars($act['type'].'/'.$act['status']) ?></div>`;
        <?php endforeach; ?>
        showMoreBtn.style.display='none';
    });
}

// Back button function
function goBack() {
    if (document.referrer) {
        window.history.back();
    } else {
        window.location.href = 'ui.php';
    }
}
</script>

</body>
</html>
