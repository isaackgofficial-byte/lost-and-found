<?php
session_start();
include('db_connect.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Block temporary admins
if (isset($_SESSION['role']) && $_SESSION['role'] === 'temporary') {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Temporary admins cannot access system settings.</h2>";
    echo "<p style='text-align:center;'><a href='Admin_dashboard.php'>Back to Dashboard</a></p>";
    exit();
}

/* ----------------------- FETCH SETTINGS ----------------------- */
$settings_res = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $settings_res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

/* ----------------------- UPDATE SYSTEM SETTINGS ----------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $changes = [];
    $allowed_keys = ['site_name', 'admin_email', 'notifications_enabled'];
    foreach ($_POST as $key => $value) {
        if ($key === "update_settings" || !in_array($key, $allowed_keys)) continue;
        $old_value = $settings[$key] ?? '';
        if ($old_value != $value) {
            $changes[] = "Changed '$key' from '$old_value' to '$value'";
            $stmt = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
            $stmt->close();
            $settings[$key] = $value;
        }
    }
    if (!empty($changes)) {
        $message = "Settings updated successfully!";
        if (($settings['notifications_enabled'] ?? '0') === '1') {
            $notif_msg = "Admin updated system settings: " . implode(", ", $changes);
            $users_res = $conn->query("SELECT id FROM users");
            while ($u = $users_res->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO notifications(user_id, message) VALUES (?, ?)");
                $stmt->bind_param("is", $u['id'], $notif_msg);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $message = "No changes detected.";
    }
}

/* ----------------------- ADD TEMPORARY ADMIN ----------------------- */
if (isset($_POST['add_temp_admin'])) {
    $user_id = intval($_POST['user_id']);
    $days = intval($_POST['duration_days']);

    if ($user_id > 0 && $days > 0) {
        $stmt = $conn->prepare("SELECT first_name, last_name, username, email, password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($first_name, $last_name, $username, $email, $password_hash);
            $stmt->fetch();
            $stmt->close();

            $expiry_date = date('Y-m-d', strtotime("+$days days"));
            $role = 'temporary';

            $insert = $conn->prepare("INSERT INTO admins (first_name, last_name, username, email, password, role, date_created, expiry_date) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
            $insert->bind_param("sssssss", $first_name, $last_name, $username, $email, $password_hash, $role, $expiry_date);
            if ($insert->execute()) {
                $message = "Temporary admin added successfully!";
                if (($settings['notifications_enabled'] ?? '0') === '1') {
                    $notif_msg = "You have been granted temporary admin access until $expiry_date.";
                    $stmt2 = $conn->prepare("INSERT INTO notifications(user_id, message) VALUES (?, ?)");
                    $stmt2->bind_param("is", $user_id, $notif_msg);
                    $stmt2->execute();
                    $stmt2->close();
                }
            } else {
                $error = "Failed to add temporary admin. Maybe user is already an admin.";
            }
            $insert->close();
        } else {
            $error = "Selected user not found.";
        }
    } else {
        $error = "Select a valid user and duration.";
    }
}

/* ----------------------- REMOVE TEMPORARY ADMIN ----------------------- */
if (isset($_GET['remove_admin'])) {
    $id = intval($_GET['remove_admin']);
    if ($id !== 1) {
        $res = $conn->query("SELECT id FROM users WHERE email=(SELECT email FROM admins WHERE id=$id)");
        $u = $res->fetch_assoc();
        $user_id = $u['id'] ?? null;

        $stmt = $conn->prepare("DELETE FROM admins WHERE id=? AND role='temporary'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $message = "Temporary admin removed.";

        if ($user_id && ($settings['notifications_enabled'] ?? '0') === '1') {
            $notif_msg = "Your temporary admin access has been removed by the system admin.";
            $stmt2 = $conn->prepare("INSERT INTO notifications(user_id, message) VALUES (?, ?)");
            $stmt2->bind_param("is", $user_id, $notif_msg);
            $stmt2->execute();
            $stmt2->close();
        }
    } else {
        $error = "The main admin cannot be removed.";
    }
}

/* ----------------------- SEND EMAILS (PHPMailer) ----------------------- */
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['send_email'])) {
    $email_subject = trim($_POST['email_subject']);
    $email_body = trim($_POST['email_body']);
    $target_user = $_POST['target_user'];

    if ($email_subject === "" || $email_body === "") {
        $error = "Email subject and message cannot be empty.";
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'isaackvicious523@gmail.com'; // your Gmail
            $mail->Password   = 'YOUR_APP_PASSWORD';          // Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('isaackvicious523@gmail.com', 'Lost and Found System');
            $mail->isHTML(true);
            $mail->Subject = $email_subject;
            $mail->Body    = $email_body;

            if ($target_user !== "all") {
                $user_id = intval($target_user);
                $stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->bind_result($user_email);
                $stmt->fetch();
                $stmt->close();

                if ($user_email) {
                    $mail->addAddress($user_email);
                    $mail->send();
                    $message = "Email sent successfully to selected user.";
                } else {
                    $error = "User email not found.";
                }
            } else {
                $res_users = $conn->query("SELECT email FROM users");
                $count_sent = 0;
                while ($row = $res_users->fetch_assoc()) {
                    $mail->clearAddresses();
                    $mail->addAddress($row['email']);
                    $mail->send();
                    $count_sent++;
                }
                $message = "Email sent to all users ($count_sent recipients).";
            }
        } catch (Exception $e) {
            $error = "Failed to send email: " . $mail->ErrorInfo;
        }
    }
}

/* ----------------------- FETCH USERS ----------------------- */
$users_res = $conn->query("
    SELECT id, first_name, last_name, username, email 
    FROM users 
    WHERE id NOT IN (SELECT id FROM admins)
    ORDER BY username ASC
");
$users = [];
while ($row = $users_res->fetch_assoc()) {
    $users[] = $row;
}

/* ----------------------- FETCH TEMPORARY ADMINS ----------------------- */
$temp_admins_res = $conn->query("
    SELECT id, first_name, last_name, username, email, role, date_created, expiry_date
    FROM admins
    WHERE role='temporary'
    ORDER BY expiry_date ASC
");
$temp_admins = [];
while ($row = $temp_admins_res->fetch_assoc()) {
    $temp_admins[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings</title>
<style>
body { margin:0; background:#121212; color:#fff; font-family:Arial; }
nav { background:#1e1e1e; padding:1rem; display:flex; gap:1rem; flex-wrap:wrap; }
nav a { color:#fff; text-decoration:none; font-weight:bold; }
nav a.active { color:#4cc9f0; }
.container { padding:2rem; }
.table { width:100%; border-collapse:collapse; margin-top:1rem; }
.table th, .table td { padding:10px; border:1px solid #333; text-align:left; }
input, textarea, select { width:100%; padding:7px; margin-top:5px; border-radius:5px; border:none; background:#1e1e1e; color:white; }
button { padding:10px 15px; background:#4cc9f0; border:none; border-radius:5px; cursor:pointer; font-weight:bold; margin-top:10px; }
button.remove { background:#b22222; }
.message { background:#4caf50; padding:10px; border-radius:5px; display:inline-block; margin-bottom:10px; }
.error { background:#b22222; padding:10px; border-radius:5px; display:inline-block; margin-bottom:10px; }
textarea { resize: vertical; }
@media(max-width:768px){ .container { padding:1rem; } }
</style>
</head>
<body>

<nav>
    <a href="Admin_dashboard.php">Dashboard</a>
    <a href="users.php">User Management</a>
    <a href="content.php">Post Moderation</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php" class="active">System Settings</a>
</nav>

<div class="container">

<h1>System Settings</h1>
<?php if(isset($message)) echo "<div class='message'>$message</div>"; ?>
<?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>

<!-- Update Settings -->
<form method="post">
    <table class="table">
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>Site Name</td><td><input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>"></td></tr>
        <tr><td>Admin Email</td><td><input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>"></td></tr>
        <tr><td>Enable Notifications</td>
            <td>
                <select name="notifications_enabled">
                    <option value="1" <?= (isset($settings['notifications_enabled']) && $settings['notifications_enabled']=='1')?'selected':'' ?>>Enabled</option>
                    <option value="0" <?= (isset($settings['notifications_enabled']) && $settings['notifications_enabled']=='0')?'selected':'' ?>>Disabled</option>
                </select>
            </td>
        </tr>
    </table>
    <button name="update_settings">Save Settings</button>
</form>

<hr style="margin:40px 0; border-color:#333;">

<!-- Manage Temporary Admins -->
<h1>Manage Temporary Admins</h1>
<form method="post" style="margin-bottom:20px;">
    <label>Select User:</label>
    <select name="user_id" required>
        <option value="">--Select User--</option>
        <?php foreach($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username'].' ('.$u['first_name'].' '.$u['last_name'].')') ?></option>
        <?php endforeach; ?>
    </select>
    <label>Duration (days):</label>
    <input type="number" name="duration_days" min="1" placeholder="Enter number of days" required>
    <button name="add_temp_admin">Add Temporary Admin</button>
</form>

<table class="table">
    <tr><th>Username</th><th>Full Name</th><th>Email</th><th>Start Date</th><th>Expiry Date</th><th>Action</th></tr>
    <?php foreach($temp_admins as $ta): ?>
        <tr>
            <td><?= htmlspecialchars($ta['username']) ?></td>
            <td><?= htmlspecialchars($ta['first_name'].' '.$ta['last_name']) ?></td>
            <td><?= htmlspecialchars($ta['email']) ?></td>
            <td><?= $ta['date_created'] ?></td>
            <td><?= $ta['expiry_date'] ?></td>
            <td><a class="remove" href="settings.php?remove_admin=<?= $ta['id'] ?>" onclick="return confirm('Remove this admin?');">Remove</a></td>
        </tr>
    <?php endforeach; ?>
</table>

<hr style="margin:40px 0; border-color:#333;">

<!-- Send Email Section -->
<h1>Send Email to Users</h1>
<form method="post" style="margin-bottom:20px;">
    <label>Send To:</label>
    <select name="target_user" required>
        <option value="all">All Users</option>
        <?php foreach($users as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username'].' ('.$u['email'].')') ?></option>
        <?php endforeach; ?>
    </select>

    <label>Subject:</label>
    <input type="text" name="email_subject" placeholder="Enter email subject" required>

    <label>Message:</label>
    <textarea name="email_body" rows="5" placeholder="Write your email message..." required></textarea>

    <button name="send_email">Send Email</button>
</form>

</div>
</body>
</html>
