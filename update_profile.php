<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "not_logged_in";
    exit();
}

$user_id = $_SESSION['user_id'];

$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$location = isset($_POST['location']) ? trim($_POST['location']) : null;
$bio = isset($_POST['bio']) ? trim($_POST['bio']) : null;

// ✅ Ensure upload folder exists
$uploadDir = "uploads/profile_images/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ✅ Handle profile image upload
$profile_image = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['profile_image']['tmp_name'];
    $imageName = time() . "_" . basename($_FILES['profile_image']['name']);
    $targetFile = $uploadDir . $imageName;

    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($imageFileType, $allowed)) {
        if (move_uploaded_file($tmpName, $targetFile)) {
            $profile_image = $imageName;
        }
    }
}

// ✅ If new columns don't exist, add them (safe to run multiple times)
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL");

// ✅ Update query (with or without profile image)
if ($profile_image) {
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, location=?, bio=?, profile_image=? WHERE id=?");
    $stmt->bind_param("ssssssi", $first_name, $last_name, $phone, $location, $bio, $profile_image, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, location=?, bio=? WHERE id=?");
    $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $location, $bio, $user_id);
}

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}
?>