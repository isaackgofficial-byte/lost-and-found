<?php
include("session_check.php"); // session already started here
include("db_connect.php");

$successMsg = $errorMsg = "";

// ---------------- CSRF TOKEN ----------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------- POST ITEM ----------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMsg = "Invalid request (CSRF token mismatch).";
    } else {
        // Collect and sanitize inputs
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $type        = trim($_POST['type'] ?? '');
        $date        = $_POST['date'] ?? '';
        $imagePath   = "";
        $user_id     = intval($_SESSION['user_id']);

        // Basic validation
        if (!$user_id) {
            $errorMsg = "You must be logged in to post an item.";
        } elseif (!$title || !$description || !$location || !$date) {
            $errorMsg = "Please fill in all required fields.";
        }

        // Validate date
        if (empty($errorMsg)) {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!($d && $d->format('Y-m-d') === $date)) {
                $errorMsg = "Invalid date format.";
            }
        }

        // Handle image upload if provided
        if (empty($errorMsg) && !empty($_FILES['image']['name'])) {
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $targetDir = __DIR__ . "/uploads/";

            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0775, true);
                // Prevent execution in uploads
                @file_put_contents($targetDir . ".htaccess", "Options -Indexes\n<FilesMatch \"\.(php|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n  Deny from all\n</FilesMatch>\n");
            }

            $tmpName = $_FILES['image']['tmp_name'];
            $origName = $_FILES['image']['name'];
            $fileSize = $_FILES['image']['size'];
            $errorCode = $_FILES['image']['error'];

            if ($errorCode !== UPLOAD_ERR_OK) {
                $errorMsg = "Image upload error (code $errorCode).";
            } elseif ($fileSize > $maxFileSize) {
                $errorMsg = "Image too large. Maximum size is 5MB.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpName);
                $allowedMime = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/gif'=>'gif'];

                if (!array_key_exists($mime, $allowedMime)) {
                    $errorMsg = "Invalid image type. Only JPG, PNG, GIF allowed.";
                } else {
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $expectedExt = $allowedMime[$mime];
                    if ($ext !== $expectedExt && !($ext==='jpeg' && $expectedExt==='jpg')) {
                        $errorMsg = "File extension does not match file content.";
                    }
                }
            }

            if (empty($errorMsg)) {
                try {
                    $rand = bin2hex(random_bytes(8));
                } catch (Exception $e) {
                    $rand = time() . "_" . bin2hex(openssl_random_pseudo_bytes(8));
                }
                $fileName = $rand . "_" . time() . "." . ($allowedMime[$mime] ?? 'jpg');
                $targetFilePath = $targetDir . $fileName;

                if (!move_uploaded_file($tmpName, $targetFilePath)) {
                    $errorMsg = "Image upload failed (could not move file).";
                } else {
                    $imagePath = "uploads/" . $fileName;
                }
            }
        }

        // Insert item if no errors
        if (empty($errorMsg)) {
            $sql = "INSERT INTO items (user_id, title, description, location, category, type, date, image, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("isssssss", $user_id, $title, $description, $location, $category, $type, $date, $imagePath);
                if ($stmt->execute()) {
                    $newItemId = $stmt->insert_id;
                    $successMsg = "✅ Item posted successfully!";

                    // ---------------- MATCHING LOGIC ----------------
                    $matchType = ($type === "Lost") ? "Found" : "Lost";
                    $likeLocation = "%$location%";
                    $matchStmt = $conn->prepare("SELECT id, user_id, title FROM items WHERE type=? AND category=? AND location LIKE ? AND user_id<>?");
                    $matchStmt->bind_param("sssi", $matchType, $category, $likeLocation, $user_id);
                    $matchStmt->execute();
                    $matches = $matchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $matchStmt->close();

                    if (!empty($matches)) {
                        $successMsg .= " Found " . count($matches) . " possible match(es)!";
                        foreach ($matches as $match) {
                            $notifMsg = ($type === "Lost") 
                                ? "A matching lost item was found: {$title}" 
                                : "A matching found item was posted: {$title}";

                            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, item_id, message) VALUES (?, ?, ?)");
                            $notifStmt->bind_param("iis", $match['user_id'], $newItemId, $notifMsg);
                            $notifStmt->execute();
                            $notifStmt->close();
                        }
                    }

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // reset CSRF token
                } else {
                    $errorMsg = "Database error: failed to save item.";
                }
                $stmt->close();
            } else {
                $errorMsg = "Database error: could not prepare statement.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Post Item - Lost & Found</title>
<link rel="stylesheet" href="ui.css">
<style>
body { transition: background 0.3s,color 0.3s; }
.dark-mode { background:#121212;color:#e0e0e0; }
.navbar { display:flex;justify-content:space-between;align-items:center;background:#333;color:#fff;padding:15px 30px; }
.navbar .nav-links a { color:#fff;margin-left:20px;text-decoration:none;font-weight:500; }
.navbar .nav-links a.active { color:#ffcb05; }
.form-section { max-width:700px;background:#fff;padding:30px;margin:50px auto;border-radius:12px;box-shadow:0 0 10px rgba(0,0,0,0.1);transition:background 0.3s,color 0.3s; }
.dark-mode .form-section { background:#1e1e1e;color:#e0e0e0; }
label { font-weight:bold;color:inherit; }
input[type="text"], input[type="date"], textarea, select { width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:15px;background:#fff;color:#000;transition:background 0.3s,color 0.3s; }
.dark-mode input[type="text"], .dark-mode input[type="date"], .dark-mode textarea, .dark-mode select { background:#2b2b2b;color:#fff;border:1px solid #555; }
textarea { height:100px; resize:none; }
.upload-section { border:2px dashed #ccc;border-radius:10px;padding:20px;text-align:center;transition:border-color 0.3s; }
.dark-mode .upload-section { border-color:#555; }
.icon-btn { font-size:30px;cursor:pointer;margin:0 10px;transition:transform 0.2s; }
.icon-btn:hover { transform: scale(1.1); }
#imagePreview { display:none;margin-top:15px;max-width:100%;border-radius:8px; }
.submit-btn { width:100%;background-color:#333;color:#fff;border:none;padding:12px;font-size:16px;border-radius:8px;cursor:pointer;transition:background 0.3s; }
.submit-btn:hover { background-color:#ffcb05;color:#000; }
.success-message, .error-message { margin-top:15px;padding:10px;border-radius:6px;text-align:center; }
.success-message { background-color:#d4edda;color:#155724; }
.error-message { background-color:#f8d7da;color:#721c24; }
#cameraModal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:1000; }
#cameraModal video { width:80%; max-width:600px; border-radius:10px; }
#capturePhoto { margin-top:10px;padding:10px 20px;font-size:16px;cursor:pointer;border-radius:8px;border:none;background:#ffcb05; }
</style>
</head>
<body>
<nav class="navbar">
<div class="logo">🔒 Lost & Found</div>
<div class="nav-links">
<a href="ui.php">Dashboard</a>
<a href="post.php" class="active">Post Item</a>
<a href="items.php">My Items</a>
<a href="profile.php">Profile</a>
</div>
<button class="theme-toggle">🌙</button>
</nav>

<section class="form-section">
<h2>📋 Post a Lost or Found Item</h2>
<form id="itemForm" method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">

<div class="form-group">
<label for="title">Title:</label>
<input type="text" id="title" name="title" required placeholder="e.g., Black Wallet, iPhone 12" value="<?=htmlspecialchars($_POST['title'] ?? '')?>">
</div>

<div class="form-group">
<label for="description">Description:</label>
<textarea id="description" name="description" required placeholder="Provide detailed description..."><?=htmlspecialchars($_POST['description'] ?? '')?></textarea>
</div>

<div class="form-group">
<label for="location">Location:</label>
<input type="text" id="location" name="location" required placeholder="Where was it lost/found?" value="<?=htmlspecialchars($_POST['location'] ?? '')?>">
</div>

<div class="form-group">
<label for="category">Category:</label>
<select id="category" name="category">
<?php
$cats = ["Electronics","Clothing","Documents","Accessories","Other"];
foreach($cats as $c){
    $sel = (($_POST['category']??'') === $c) ? 'selected':'';
    echo "<option value=\"".htmlspecialchars($c)."\" $sel>".htmlspecialchars($c)."</option>";
}
?>
</select>
</div>

<div class="form-group">
<label for="type">Type:</label>
<select id="type" name="type">
<option value="Lost" <?= (($_POST['type']??'')==='Lost')?'selected':''?>>Lost</option>
<option value="Found" <?= (($_POST['type']??'')==='Found')?'selected':''?>>Found</option>
</select>
</div>

<div class="form-group">
<label for="date">Date:</label>
<input type="date" id="date" name="date" required value="<?=htmlspecialchars($_POST['date'] ?? '')?>">
</div>

<div class="form-group upload-section">
<label>Upload Image:</label><br>
<span class="icon-btn" id="cameraIcon">📷</span>
<span class="icon-btn" id="fileIcon">📁</span>
<input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" style="display:none;">
<img id="imagePreview" alt="Selected Image Preview">
<p><small>Optional - JPG, PNG, GIF up to 5MB</small></p>
</div>

<?php if($successMsg): ?>
<div class="success-message"><?=htmlspecialchars($successMsg)?></div>
<?php elseif($errorMsg): ?>
<div class="error-message"><?=htmlspecialchars($errorMsg)?></div>
<?php endif; ?>

<button type="submit" class="submit-btn">Submit Item</button>
</form>
</section>

<div id="cameraModal">
<div style="text-align:center;">
<video id="cameraPreview" autoplay playsinline></video><br>
<button id="capturePhoto">Capture</button>
</div>
</div>

<script>
const imageInput = document.getElementById('image');
const imagePreview = document.getElementById('imagePreview');
const cameraIcon = document.getElementById('cameraIcon');
const fileIcon = document.getElementById('fileIcon');
const themeToggle = document.querySelector('.theme-toggle');
const cameraModal = document.getElementById('cameraModal');
const cameraPreview = document.getElementById('cameraPreview');
const capturePhotoBtn = document.getElementById('capturePhoto');
let cameraStream = null;

fileIcon.addEventListener('click', ()=>imageInput.click());
cameraIcon.addEventListener('click', openCamera);

imageInput.addEventListener('change', ()=>{
    if(imageInput.files && imageInput.files[0]){
        if(imageInput.files[0].size>5*1024*1024){alert('Image too large'); imageInput.value=''; return;}
        const reader=new FileReader();
        reader.onload=e=>{imagePreview.src=e.target.result; imagePreview.style.display='block';};
        reader.readAsDataURL(imageInput.files[0]);
    }
});

themeToggle.addEventListener('click', ()=>{
    document.body.classList.toggle('dark-mode');
    themeToggle.textContent=document.body.classList.contains('dark-mode')?'☀️':'🌙';
    localStorage.setItem('theme', document.body.classList.contains('dark-mode')?'dark':'light');
});
if(localStorage.getItem('theme')==='dark'){document.body.classList.add('dark-mode');themeToggle.textContent='☀️';}

function openCamera(){
    cameraModal.style.display='flex';
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}})
    .then(stream=>{cameraStream=stream;cameraPreview.srcObject=stream;})
    .catch(()=>{alert('Cannot access camera'); cameraModal.style.display='none';});
}

capturePhotoBtn.addEventListener('click', ()=>{
    const canvas=document.createElement('canvas');
    canvas.width=cameraPreview.videoWidth || 1280;
    canvas.height=cameraPreview.videoHeight || 720;
    canvas.getContext('2d').drawImage(cameraPreview,0,0,canvas.width,canvas.height);
    canvas.toBlob(blob=>{
        const file=new File([blob],'camera.jpg',{type:'image/jpeg',lastModified:Date.now()});
        const dt=new DataTransfer();
        dt.items.add(file);
        imageInput.files=dt.files;
        imagePreview.src=URL.createObjectURL(blob);
        imagePreview.style.display='block';
        closeCamera();
    },'image/jpeg',0.9);
});

cameraModal.addEventListener('click', e=>{ if(e.target===cameraModal) closeCamera(); });
function closeCamera(){ cameraModal.style.display='none'; if(cameraStream){cameraStream.getTracks().forEach(t=>t.stop()); cameraStream=null;} }
</script>
</body>
</html>
