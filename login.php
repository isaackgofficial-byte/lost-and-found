<?php
session_start();
include("db_connect.php");

// Initialize variables
$email = $password = "";
$emailErr = $passwordErr = $loginErr = "";
$role = ""; // store role

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($email)) $emailErr = "The Email Field is required";
    if (empty($password)) $passwordErr = "The Password Field is required";

    if (empty($emailErr) && empty($passwordErr)) {
        // Check admins
        $stmt = $conn->prepare("SELECT id, first_name, last_name, username, password, role, expiry_date FROM admins WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $firstName, $lastName, $username, $hashedPassword, $role, $expiryDate);
            $stmt->fetch();
            $stmt->close();

            $today = date('Y-m-d');

            if ($role === 'temporary' && $expiryDate && $today > $expiryDate) {
                $loginErr = "This temporary admin account has expired.";
            } elseif (password_verify($password, $hashedPassword)) {
                $_SESSION["admin_logged_in"] = true;
                $_SESSION["admin_id"] = $id;
                $_SESSION["first_name"] = $firstName;
                $_SESSION["last_name"] = $lastName;
                $_SESSION["username"] = $username;
                $_SESSION["email"] = $email;
                $_SESSION["role"] = $role; // <-- Important

                header("Location: Admin_dashboard.php");
                exit();
            } else {
                $loginErr = "Incorrect password.";
            }
        } else {
            // Check regular users
            $stmt = $conn->prepare("SELECT id, first_name, last_name, password, status, deactivated_at, deactivation_days, is_deleted FROM users WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $firstName, $lastName, $hashedPassword, $status, $deactivatedAt, $deactivationDays, $isDeleted);
                $stmt->fetch();

                if ($isDeleted) {
                    $loginErr = "This account has been permanently deleted.";
                } elseif (password_verify($password, $hashedPassword)) {
                    if ($status === 'inactive') {
                        $deactivatedAtTime = strtotime($deactivatedAt);
                        $expiryTime = strtotime("+$deactivationDays days", $deactivatedAtTime);
                        $now = time();
                        if ($now < $expiryTime) {
                            $remaining = ceil(($expiryTime - $now)/86400);
                            $loginErr = "Your account is deactivated. Try again in $remaining day(s).";
                        } else {
                            $conn->query("UPDATE users SET status='active', deactivated_at=NULL, deactivation_days=0 WHERE id=$id");
                        }
                    }

                    if (empty($loginErr)) {
                        $_SESSION["user_id"] = $id;
                        $_SESSION["first_name"] = $firstName;
                        $_SESSION["last_name"] = $lastName;
                        $_SESSION["email"] = $email;

                        header("Location: ui.php");
                        exit();
                    }
                } else {
                    $loginErr = "Incorrect password.";
                }
            } else {
                $loginErr = "Email not registered.";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Lost and Found System</title>
<link rel="icon" href="Img/FAVI_ICO.png">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background: linear-gradient(135deg, #003366, #00509E); height: 100vh; display: flex; justify-content: center; align-items: center; }
.login-container { background: #ffffff; width: 380px; border-radius: 15px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); padding: 40px 30px; animation: fadeIn 0.8s ease-in-out; }
header h1 { color: #003366; text-align: center; font-size: 26px; margin-bottom: 10px; }
header p { text-align: center; color: #555; font-size: 15px; margin-bottom: 25px; }
.form-group { margin-bottom: 20px; }
label { font-weight: bold; color: #003366; }
input[type="text"], input[type="password"] { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 15px; margin-top: 8px; outline: none; transition: all 0.3s ease; }
input[type="text"]:focus, input[type="password"]:focus { border-color: #00509E; box-shadow: 0 0 4px rgba(0, 80, 158, 0.3); }
button { width: 100%; background-color: #00509E; color: white; border: none; border-radius: 8px; padding: 12px; font-size: 16px; cursor: pointer; transition: background-color 0.3s ease; margin-top: 10px; }
button:hover { background-color: #003366; }
footer { text-align: center; margin-top: 25px; }
footer a { color: #00509E; text-decoration: none; font-weight: bold; }
footer a:hover { text-decoration: underline; }
.error-message { color: red; font-size: 13px; margin-top: 5px; }
.forgotpassword_link { display: block; text-align: right; margin-top: -5px; margin-bottom: 10px; color: #003366; font-size: 14px; font-weight: bold; text-decoration: none; }
.forgotpassword_link:hover { text-decoration: underline; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body>
<div class="login-container">
    <div class="login-form">
        <header>
            <h1>Hi, welcome back</h1>
            <p>Please fill in your details to log in.</p>
        </header>
        <form action="" method="POST" novalidate>
            <div class="form-group">
                <label for="username">Email</label>
                <input type="text" id="username" name="username" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>">
                <?php if(!empty($emailErr)): ?><p class="error-message"><?php echo $emailErr; ?></p><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password">
                <?php if(!empty($passwordErr)): ?><p class="error-message"><?php echo $passwordErr; ?></p><?php endif; ?>
            </div>
            <?php if(!empty($loginErr)): ?><p class="error-message" style="font-weight:bold;"><?php echo $loginErr; ?></p><?php endif; ?>
            <!-- <a href="forgotpassword.php" class="forgotpassword_link">Forgot Password?</a> -->
            <button type="submit">Sign In</button>
        </form>
        <footer>
            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
        </footer>
    </div>
</div>
</body>
</html>
