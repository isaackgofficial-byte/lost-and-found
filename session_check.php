<?php
session_start();
include 'db_connect.php'; // Ensure this file initializes $conn properly

/* -----------------------------------------------------------
   Detect if request expects JSON (API mode)
------------------------------------------------------------*/
$isApi = (
    isset($_SERVER['HTTP_ACCEPT']) &&
    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || 
(defined('API_MODE') && API_MODE === true);

/* -----------------------------------------------------------
   If no active session
------------------------------------------------------------*/
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(["logged_in" => false]);
        exit;
    } 

    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);

/* -----------------------------------------------------------
   Fetch user status & deactivation details
------------------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT status, deactivated_at, deactivation_days 
    FROM users 
    WHERE id = ? 
    LIMIT 1
");

if (!$stmt) { 
    die("SQL Prepare Failed: " . $conn->error); 
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

/* -----------------------------------------------------------
   Handle missing user (deleted account)
------------------------------------------------------------*/
if (!$user) {
    session_destroy();

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(["logged_in" => false]);
        exit;
    }

    header("Location: login.php");
    exit;
}

/* -----------------------------------------------------------
   Handle INACTIVE users
------------------------------------------------------------*/
if ($user['status'] === 'inactive') {

    $deactivated_at = strtotime($user['deactivated_at'] ?? '');
    $days = intval($user['deactivation_days'] ?? 0);

    // Calculate when account becomes active again
    $expires_at = strtotime("+$days days", $deactivated_at);

    if (time() < $expires_at) {

        // Still inside the inactive period
        $remaining_days = ceil(($expires_at - time()) / 86400);

        session_destroy();

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                "logged_in" => false,
                "deactivated" => true,
                "remaining_days" => $remaining_days,
                "message" => "Your account is deactivated for $remaining_days more day(s)."
            ]);
            exit;
        }

        // Web version message
        die("
            <h2>🚫 Account Deactivated</h2>
            <p>Your account is deactivated for <strong>$remaining_days</strong> more day(s).</p>
        ");
    }

    /* -----------------------------------------------------------
       Auto-reactivate user when the deactivation period ends
    ------------------------------------------------------------*/
    $stmt = $conn->prepare("
        UPDATE users 
        SET status = 'active', deactivated_at = NULL, deactivation_days = NULL 
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

/* -----------------------------------------------------------
   If logged in & active, send API response if needed
------------------------------------------------------------*/
if ($isApi) {
    header('Content-Type: application/json');
    echo json_encode([
        "logged_in" => true,
        "user_id"   => $_SESSION['user_id'],
        "username"  => $_SESSION['username'] ?? null
    ]);
    exit;
}
?>
