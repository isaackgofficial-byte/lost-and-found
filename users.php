<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Handle Activate / Deactivate / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        if ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE users SET status='active', deactivated_at=NULL, deactivation_days=0 WHERE id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action === 'deactivate') {
            $days = isset($_POST['deactivation_days']) ? intval($_POST['deactivation_days']) : 7;
            $stmt = $conn->prepare("UPDATE users SET status='inactive', deactivated_at=NOW(), deactivation_days=? WHERE id=?");
            $stmt->bind_param("ii", $days, $user_id);
            $stmt->execute();
            $stmt->close();

        } elseif ($action === 'delete') {
            // Permanent delete — requires items.user_id FK with ON DELETE CASCADE
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: users.php");
    exit;
}

// Search + filter + pagination
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : '';
$conditions = ["1=1"]; // base condition
if (!empty($search)) $conditions[] = "(first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%')";
if (!empty($filter)) $conditions[] = "status='$filter'";
$whereSQL = !empty($conditions) ? "WHERE ".implode(" AND ", $conditions) : "";

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Total users count
$countResult = $conn->query("SELECT COUNT(*) AS total FROM users $whereSQL");
$total = $countResult->fetch_assoc()['total'];
$pages = ceil($total / $limit);

// Fetch users with pagination
$result = $conn->query("SELECT id, first_name, last_name, email, date_created, IFNULL(status,'inactive') AS status, profile_image 
                        FROM users $whereSQL ORDER BY id DESC LIMIT $limit OFFSET $offset");
$users = [];
while ($row = $result->fetch_assoc()) $users[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
<style>
body { margin:0; font-family:"Segoe UI",Arial; background:#e9f2ff; color:#003366; }
header{background:#0066cc;color:white;padding:1.2rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 3px 6px rgba(0,0,0,0.15);} 
header button{background:#ff4d4d;border:none;padding:0.6rem 1.2rem;border-radius:8px;color:white;font-weight:bold;cursor:pointer;} 
nav{background:#004c99;display:flex;justify-content:center;flex-wrap:wrap;} nav a{color:white;text-decoration:none;padding:1rem 2rem;font-weight:bold;} nav a.active,nav a:hover{background:#0066cc;} 

.main-content{padding:2rem;max-width:1200px;margin:auto;} 

.search-filter-box{background:white;padding:15px;border-radius:12px;box-shadow:0 4px 8px rgba(0,0,0,0.1);margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;} 
.search-filter-box input, .search-filter-box select{padding:10px;border:1px solid #3399ff;border-radius:8px;font-size:15px;} 
.search-btn{background:#0066cc;color:white;padding:10px 18px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;} 

.table-container{background:white;border-radius:16px;padding:20px;box-shadow:0 6px 12px rgba(0,0,0,0.12);overflow-x:auto;} 

table{width:100%;border-collapse:collapse;} th,td{padding:14px;border-bottom:1px solid #e5e5e5;text-align:left;} th{background:#0066cc;color:white;} 
.status-active{background:#28a745;color:white;padding:4px 10px;border-radius:6px;} .status-inactive{background:#cc0000;color:white;padding:4px 10px;border-radius:6px;} 
.action-btn{padding:6px 12px;border:none;border-radius:6px;font-weight:bold;cursor:pointer;} 
.activate{background:#28a745;color:white;} .deactivate{background:#ff9900;color:white;} .delete{background:#ff4d4d;color:white;} 

.pagination{margin-top:20px;display:flex;justify-content:center;gap:10px;} 
.pagination a{padding:8px 14px;background:#0066cc;color:white;border-radius:6px;text-decoration:none;font-weight:bold;} 
.pagination a.active{background:#003d80;} 

.profile-img {width:40px; height:40px; border-radius:50%; object-fit:cover; cursor:pointer;}

.modal {display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;overflow:auto;background:rgba(0,0,0,0.5);}
.modal-content {background:white;margin:15% auto;padding:20px;border-radius:12px;width:320px;box-shadow:0 4px 8px rgba(0,0,0,0.2);}
.modal-content h3{margin-top:0;text-align:center;}
.modal-content input[type=number]{width:100%;padding:8px;margin:10px 0;border-radius:6px;border:1px solid #ccc;}
.modal-content button{width:48%;padding:8px;margin:5px;border:none;border-radius:6px;font-weight:bold;cursor:pointer;}
.modal-content .confirm{background:#28a745;color:white;} .modal-content .cancel{background:#ff4d4d;color:white;}
</style>
</head>
<body>
<header>
    <h1>User Management</h1>
    <button onclick="window.location.href='logout.php'">Logout</button>
</header>
<nav>
    <a href="Admin_dashboard.php">Dashboard</a>
    <a class="active" href="users.php">User Management</a>
    <a href="content.php">Post Moderation</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">System Settings</a>
</nav>

<div class="main-content">
    <h2>All Registered Users</h2>
    <form method="GET" class="search-filter-box">
        <input type="text" name="search" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
        <select name="filter">
            <option value="">All Status</option>
            <option value="active" <?= $filter==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <button class="search-btn">Apply</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Profile</th>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Date Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): foreach ($users as $user): ?>
                <tr>
                    <td><img class="profile-img" src="<?= htmlspecialchars($user['profile_image'] ?: 'default.png') ?>" alt="Profile"></td>
                    <td><?= $user['id'] ?></td>
                    <td><a href="view_user.php?id=<?= $user['id'] ?>" style="color:#004c99;font-weight:bold;"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></a></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span></td>
                    <td><?= $user['date_created'] ?></td>
                    <td>
                        <form method="POST" class="action-form" data-userid="<?= $user['id'] ?>">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <?php if($user['status']==='active'): ?>
                                <button class="action-btn deactivate" type="button" onclick="openDeactivateModal(<?= $user['id'] ?>)">Deactivate</button>
                            <?php else: ?>
                                <button class="action-btn activate" name="action" value="activate">Activate</button>
                            <?php endif; ?>
                            <button class="action-btn delete" type="button" onclick="openDeleteModal(<?= $user['id'] ?>)">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" style="text-align:center;">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for($i=1;$i<=$pages;$i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<!-- DEACTIVATION MODAL -->
<div id="deactivateModal" class="modal">
  <div class="modal-content">
    <h3>Deactivate User</h3>
    <p>Enter number of days:</p>
    <input type="number" id="deactivationDays" value="7" min="1">
    <div style="text-align:center;">
        <button class="confirm" onclick="confirmDeactivate()">Confirm</button>
        <button class="cancel" onclick="closeModal('deactivateModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <h3>Delete User</h3>
    <p>Are you sure you want to permanently remove this user?</p>
    <div style="text-align:center;">
        <button class="confirm" onclick="confirmDelete()">Yes</button>
        <button class="cancel" onclick="closeModal('deleteModal')">Cancel</button>
    </div>
  </div>
</div>

<script>
let currentUserId = 0;
function openDeactivateModal(userId){
    currentUserId = userId;
    document.getElementById('deactivateModal').style.display = 'block';
}
function openDeleteModal(userId){
    currentUserId = userId;
    document.getElementById('deleteModal').style.display = 'block';
}
function closeModal(modalId){
    document.getElementById(modalId).style.display = 'none';
}

function confirmDeactivate(){
    const days = document.getElementById('deactivationDays').value;
    const form = document.querySelector(`form[data-userid='${currentUserId}']`);
    const inputAction = document.createElement('input');
    inputAction.type='hidden'; inputAction.name='action'; inputAction.value='deactivate';
    const inputDays = document.createElement('input');
    inputDays.type='hidden'; inputDays.name='deactivation_days'; inputDays.value=days;
    form.appendChild(inputAction); form.appendChild(inputDays);
    form.submit();
}

function confirmDelete(){
    const form = document.querySelector(`form[data-userid='${currentUserId}']`);
    const inputAction = document.createElement('input');
    inputAction.type='hidden'; inputAction.name='action'; inputAction.value='delete';
    form.appendChild(inputAction);
    form.submit();
}

// Close modals on outside click
window.onclick = function(event){
    ['deactivateModal','deleteModal'].forEach(id=>{
        const modal=document.getElementById(id);
        if(event.target===modal) modal.style.display='none';
    });
}
</script>
</body>
</html>
