<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// ===== Handle API Calls =====
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'get_profile') {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, location, bio, profile_image,
            profile_public, email_public, phone_public, location_public, activity_public, date_created 
            FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $itemsCount = $conn->query("SELECT COUNT(*) as total FROM items WHERE user_id=$userId")->fetch_assoc()['total'];
        $foundCount = $conn->query("SELECT COUNT(*) as total FROM items WHERE user_id=$userId AND type='Found'")->fetch_assoc()['total'];

        echo json_encode(['user'=>$user,'items_count'=>$itemsCount,'found_count'=>$foundCount]);
        exit();
    }

    if ($action === 'update_profile') {
        $data = json_decode(file_get_contents('php://input'), true);
        $nameParts = explode(' ', $data['fullName'], 2);
        $first_name = $nameParts[0];
        $last_name = $nameParts[1] ?? '';
        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, location=?, bio=? WHERE id=?");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $data['email'], $data['phone'], $data['location'], $data['bio'], $userId);
        echo json_encode($stmt->execute() ? ['success'=>true,'message'=>'Profile updated'] : ['success'=>false,'error'=>'Update failed']);
        exit();
    }

    if ($action === 'change_password') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i",$userId);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password'];
        if (!password_verify($data['currentPassword'], $hash)) { echo json_encode(['success'=>false,'error'=>'Current password incorrect']); exit(); }
        $newHash = password_hash($data['newPassword'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si",$newHash,$userId);
        $stmt->execute();
        echo json_encode(['success'=>true,'message'=>'Password changed']);
        exit();
    }

    if ($action === 'update_privacy') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $conn->prepare("UPDATE users SET profile_public=?, email_public=?, phone_public=?, location_public=?, activity_public=? WHERE id=?");
        $stmt->bind_param("iiiiii",$data['profile'],$data['email'],$data['phone'],$data['location'],$data['activity'],$userId);
        $stmt->execute();
        echo json_encode(['success'=>true,'message'=>'Privacy settings updated']);
        exit();
    }

    if ($action === 'get_activity') {
        $stmt = $conn->prepare("SELECT title, type, status FROM items WHERE user_id=? ORDER BY date_posted DESC LIMIT 10");
        $stmt->bind_param("i",$userId);
        $stmt->execute();
        $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['activity'=>$activity]);
        exit();
    }

    if ($action === 'upload_avatar') {
        if (isset($_FILES['avatar'])) {
            $file = $_FILES['avatar'];
            $targetDir = 'uploads/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            $filePath = $targetDir . uniqid() . '_' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
                $stmt->bind_param("si",$filePath,$userId);
                $stmt->execute();
                echo json_encode(['success'=>true,'message'=>'Avatar updated','path'=>$filePath]);
            } else echo json_encode(['success'=>false,'error'=>'Upload failed']);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - Lost & Found</title>
<link rel="stylesheet" href="profile.css">
<script src="profile.js" defer></script>
</head>
<body>

<nav class="navbar">
    <div class="logo" style="color: blue">🔒 Lost & Found</div>
    <div class="nav-links">
        <a href="ui.php">Dashboard</a>
        <a href="post.php">Post Item</a>
        <a href="items.php">My Items</a>
        <a href="profile.php" class="active">Profile</a>
    </div>
    <div class="nav-actions">
        <button class="theme-toggle" id="themeToggle">🌙</button>
        <button class="btn btn-outline" onclick="window.location.href='ui.php'">Back</button>
    </div>
</nav>

<main class="profile-section">
    <div class="profile-card">
        <div class="profile-avatar" id="profileAvatar">
            <span id="avatarInitials"></span>
            <button class="change-avatar-btn" onclick="openAvatarModal()" title="Change avatar">📷</button>
        </div>
        <h2 id="profileName"></h2>
        <p id="profileEmail"></p>

        <div class="profile-stats">
            <div class="stat">Items Posted: <span id="itemsCount"></span></div>
            <div class="stat">Items Found: <span id="foundCount"></span></div>
            <div class="stat">Member Since: <span id="memberSince"></span></div>
        </div>

        <button class="btn btn-primary" onclick="switchTab('edit-profile')">Edit Profile</button>
    </div>

    <div class="profile-details">
        <div class="tabs">
            <button class="tab active" data-tab="edit-profile">Edit Profile</button>
            <button class="tab" data-tab="change-password">Change Password</button>
            <button class="tab" data-tab="privacy">Privacy Settings</button>
            <button class="tab" data-tab="activity">Recent Activity</button>
        </div>

        <div id="edit-profile" class="tab-content active">
            <form id="profileForm">
                <div class="form-group"><input type="text" id="fullName" placeholder="Full Name" required></div>
                <div class="form-group"><input type="email" id="email" placeholder="Email" required></div>
                <div class="form-group"><input type="tel" id="phone" placeholder="Phone"></div>
                <div class="form-group"><input type="text" id="location" placeholder="Location"></div>
                <div class="form-group"><textarea id="bio" placeholder="Bio"></textarea></div>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>

        <div id="change-password" class="tab-content">
            <form id="passwordForm">
                <div class="form-group"><input type="password" id="currentPassword" placeholder="Current Password" required></div>
                <div class="form-group"><input type="password" id="newPassword" placeholder="New Password" required></div>
                <div class="form-group"><input type="password" id="confirmPassword" placeholder="Confirm New Password" required></div>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Update Password</button></div>
            </form>
        </div>

        <div id="privacy" class="tab-content">
            <form id="privacyForm">
                <label><input type="checkbox" id="profileVisibility"> Profile Public</label>
                <label><input type="checkbox" id="emailVisibility"> Email Public</label>
                <label><input type="checkbox" id="phoneVisibility"> Phone Public</label>
                <label><input type="checkbox" id="locationVisibility"> Location Public</label>
                <label><input type="checkbox" id="activityVisibility"> Activity Public</label>
                <div class="form-actions"><button type="submit" class="btn btn-primary">Save Privacy Settings</button></div>
            </form>
        </div>

        <div id="activity" class="tab-content">
            <div id="activityList" class="activity-list">Loading activity...</div>
        </div>
    </div>
</main>

<!-- Avatar Modal -->
<div id="avatarModal" class="modal">
    <div class="modal-content">
        <h3>Upload Avatar</h3>
        <button onclick="uploadAvatarFromDevice()">Upload from Device</button>
        <button onclick="openCameraModal()">Take a Photo</button>
        <button onclick="closeAvatarModal()">Close</button>
        <input type="file" id="avatarUpload" accept="image/*" hidden>
    </div>
</div>

<!-- Camera Modal -->
<div id="cameraModal" class="modal">
    <div class="modal-content">
        <video id="cameraPreview" autoplay playsinline></video>
        <canvas id="cameraCanvas" hidden></canvas>
        <button onclick="capturePhoto()">Capture</button>
        <button onclick="closeCameraModal()">Close</button>
    </div>
</div>

</body>
</html>
