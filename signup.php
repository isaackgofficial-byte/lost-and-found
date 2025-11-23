<?php
include("db_connect.php");

$firstName = $lastName = $email = $password = $confirmPassword = "";
$firstNameErr = $lastNameErr = $emailErr = $passwordErr = $confirmPasswordErr = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize Inputs
    $firstName = htmlspecialchars(trim($_POST["first_name"]));
    $lastName = htmlspecialchars(trim($_POST["last_name"]));
    $email = strtolower(trim($_POST["email"])); // force lowercase
    $password = htmlspecialchars(trim($_POST["password"]));
    $confirmPassword = htmlspecialchars(trim($_POST["confirm_password"]));

    // =========================
    // VALIDATION
    // =========================

    // First Name (letters only)
    if (empty($firstName)) {
        $firstNameErr = "First name is required";
    } elseif (!preg_match("/^[A-Za-z]+$/", $firstName)) {
        $firstNameErr = "First name must contain letters only";
    }

    // Last Name (letters only)
    if (empty($lastName)) {
        $lastNameErr = "Last name is required";
    } elseif (!preg_match("/^[A-Za-z]+$/", $lastName)) {
        $lastNameErr = "Last name must contain letters only";
    }

    // Email validation
    if (empty($email)) {
        $emailErr = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailErr = "Invalid email format";
    } else {
        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $emailErr = "Email is already registered";
        }
        $checkEmail->close();
    }

    // Password
    if (empty($password)) {
        $passwordErr = "Password is required";
    } elseif (strlen($password) < 6) {
        $passwordErr = "Password must be at least 6 characters long";
    }

    // Confirm Password
    if (empty($confirmPassword)) {
        $confirmPasswordErr = "Please confirm your password";
    } elseif ($password !== $confirmPassword) {
        $confirmPasswordErr = "Passwords do not match";
    }

    // =========================
    // INSERT USER IF NO ERRORS
    // =========================
    if (
        empty($firstNameErr) &&
        empty($lastNameErr) &&
        empty($emailErr) &&
        empty($passwordErr) &&
        empty($confirmPasswordErr)
    ) {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password, date_created)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);

        if ($stmt->execute()) {
            $successMessage = "✅ Account created successfully! Redirecting...";
            echo "<script>
                    setTimeout(function(){
                        window.location.href = 'login.php';
                    }, 2000);
                  </script>";
        } else {
            $successMessage = "❌ Something went wrong. Please try again.";
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign Up | Lost and Found System</title>
  <link rel="icon" href="Img/FAVI_ICO.png" />
  <style>
    /* ====== GLOBAL STYLES ====== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background: linear-gradient(135deg, #003366, #00509E);
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* ====== SIGNUP CONTAINER ====== */
    .signup-container {
        background-color: #fff;
        width: 900px;
        max-width: 95%;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        display: flex;
        animation: fadeIn 0.8s ease-in-out;
    }

    /* ====== IMAGE SECTION ====== */
    .signup-image {
        width: 50%;
        background: #003366;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .signup-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* ====== FORM SECTION ====== */
    .signup-form {
        width: 50%;
        padding: 40px 35px;
    }

    header h1 {
        color: #003366;
        font-size: 26px;
        margin-bottom: 8px;
        text-align: center;
    }

    header p {
        text-align: center;
        color: #666;
        font-size: 15px;
        margin-bottom: 25px;
    }

    form div {
        margin-bottom: 18px;
    }

    label {
        display: block;
        color: #003366;
        font-weight: bold;
        margin-bottom: 5px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
    }

    input:focus {
        border-color: #00509E;
        box-shadow: 0 0 4px rgba(0,80,158,0.3);
    }

    button {
        width: 100%;
        padding: 12px;
        background-color: #00509E;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s ease;
        margin-top: 10px;
    }

    button:hover {
        background-color: #003366;
    }

    /* Google Button */
    .google-btn {
        margin-top: 15px;
        width: 100%;
        text-align: center;
        background: #4285F4;
        color: white;
        padding: 12px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        gap: 10px;
        transition: 0.3s ease;
    }
    .google-btn:hover {
        background: #3367D6;
    }
    .google-btn img {
        width: 22px;
        height: 22px;
    }

    /* ====== FEEDBACK MESSAGES ====== */
    .error {
        color: red;
        font-size: 13px;
        margin-top: 4px;
        display: block;
    }

    .success {
        color: green;
        font-size: 15px;
        font-weight: bold;
        margin-bottom: 10px;
        text-align: center;
    }

    /* ====== RESPONSIVE ====== */
    @media (max-width: 768px) {
        .signup-container {
            flex-direction: column;
        }
        .signup-image {
            display: none;
        }
        .signup-form {
            width: 100%;
            padding: 30px;
        }
    }

    /* ====== ANIMATION ====== */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <div class="signup-image">
      <img src="Img/img1.jpg" alt="Registration Interface" />
    </div>

    <div class="signup-form">
      <header>
        <h1>Create Your Account</h1>
        <p>Fill out the form below to register.</p>
      </header>

      <?php if (!empty($successMessage)): ?>
        <div class="success"><?php echo $successMessage; ?></div>
      <?php endif; ?>

      <form action="" method="POST" autocomplete="off">
        <div>
          <label for="first_name">First Name</label>
          <input 
            type="text" 
            id="first_name" 
            name="first_name" 
            value="<?php echo $firstName; ?>" 
            placeholder="Enter your first name" 
          />
          <span class="error"><?php echo $firstNameErr; ?></span>
        </div>

        <div>
          <label for="last_name">Last Name</label>
          <input 
            type="text" 
            id="last_name" 
            name="last_name" 
            value="<?php echo $lastName; ?>" 
            placeholder="Enter your last name" 
          />
          <span class="error"><?php echo $lastNameErr; ?></span>
        </div>

        <div>
          <label for="email">Email Address</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            value="<?php echo $email; ?>" 
            placeholder="Enter your email address"
            style="text-transform: lowercase;"
            oninput="this.value = this.value.toLowerCase();"
          />
          <span class="error"><?php echo $emailErr; ?></span>
        </div>

        <div>
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter password" />
          <span class="error"><?php echo $passwordErr; ?></span>
        </div>

        <div>
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" />
          <span class="error"><?php echo $confirmPasswordErr; ?></span>
        </div>

        <button type="submit">Register Account</button>

        <!-- Google Signup Button -->
        <!-- <a href="google_auth.php" class="google-btn">
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo">
            Sign up with Google
        </a> -->

      </form>

      <footer>
        <p>Already have an account? <a href="login.php">Log in</a></p>
      </footer>
    </div>
  </div>
</body>
</html>
