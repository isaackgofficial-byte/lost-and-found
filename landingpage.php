<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Welcome | Lost and Found System</title>
  <link rel="icon" href="Img/FAVI_ICO.png" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Poppins", sans-serif;
    }

    body {
      background-color: #f8fbff;
      color: #003366;
      overflow-x: hidden;
    }

    /* HERO SECTION */
    .hero {
      background: linear-gradient(135deg, rgba(0, 80, 158, 0.5), rgba(0, 153, 255, 0.3)),
        url("Img/UI_LOGIN.jpeg") no-repeat center center/cover;
      height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      color: #fff;
      padding: 0 20px;
      position: relative;
    }

    .glass-box {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 40px 25px;
      max-width: 750px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
      animation: fadeIn 1.2s ease-out;
    }

    .hero h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .hero h1 span {
      color: #ffd166;
    }

    .hero p {
      font-size: 1.1rem;
      margin-bottom: 40px;
      color: #e6f2ff;
    }

    .hero-buttons {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .hero a {
      text-decoration: none;
      color: white;
      background: linear-gradient(90deg, #0077ff, #00aaff);
      padding: 14px 35px;
      border-radius: 12px;
      font-weight: 600;
      transition: 0.3s;
      box-shadow: 0 4px 12px rgba(0, 119, 255, 0.3);
    }

    .hero a:hover {
      background: linear-gradient(90deg, #0059b3, #0077e6);
      transform: translateY(-3px);
    }

    /* FEATURES SECTION */
    .features {
      padding: 80px 20px;
      text-align: center;
      background: #f0f6ff;
    }

    .features h2 {
      font-size: 2.3rem;
      margin-bottom: 15px;
      color: #003366;
    }

    .features p {
      font-size: 1rem;
      color: #555;
      margin-bottom: 40px;
    }

    .feature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 30px;
      max-width: 1100px;
      margin: 0 auto;
    }

    .feature-card {
      background: white;
      border-radius: 16px;
      padding: 30px 20px;
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
      transition: 0.3s;
    }

    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 119, 255, 0.15);
    }

    .feature-card h3 {
      color: #0077ff;
      margin-bottom: 10px;
    }

    /* HOW IT WORKS */
    .how-it-works {
      background: white;
      padding: 80px 20px;
      text-align: center;
    }

    .how-it-works h2 {
      font-size: 2.2rem;
      color: #003366;
    }

    .steps {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 40px;
      margin-top: 40px;
    }

    .step {
      background: #f8fbff;
      border: 1px solid #dce9ff;
      border-radius: 16px;
      width: 250px;
      padding: 25px 20px;
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s;
    }

    .step:hover {
      transform: translateY(-5px);
    }

    .step span {
      display: inline-block;
      background: #0077ff;
      color: white;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      line-height: 40px;
      font-weight: bold;
      font-size: 1.2rem;
      margin-bottom: 10px;
    }

    /* FOOTER */
    footer {
      background-color: #003366;
      color: white;
      text-align: center;
      padding: 20px 10px;
      margin-top: 50px;
    }

    footer a {
      color: #ffd166;
      text-decoration: none;
      font-weight: 600;
    }

    footer a:hover {
      text-decoration: underline;
    }

    /* ANIMATIONS */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      .hero h1 {
        font-size: 2.2rem;
      }
      .hero p {
        font-size: 1rem;
      }
    }
  </style>
</head>

<body>
  <!-- HERO SECTION -->
  <section class="hero">
    <div class="glass-box">
      <h1>Welcome to the Lost and <span>Found</span> System</h1>
      <p>Smart, fast, and reliable way to help you locate lost belongings or report found items within your community.</p>
      <div class="hero-buttons">
        <a href="login.php">Get Started</a>
        <a href="signup.php">Sign Up</a>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="features">
    <h2>Why Use Our System?</h2>
    <p>We make it simple and secure to reconnect people with their lost belongings.</p>
    <div class="feature-grid">
      <div class="feature-card">
        <h3>🔍 Smart Search</h3>
        <p>Find reported items instantly using filters and AI keyword matching.</p>
      </div>
      <div class="feature-card">
        <h3>📸 Image Upload</h3>
        <p>Upload clear pictures of lost or found items for easy identification.</p>
      </div>
      <div class="feature-card">
        <h3>🧑‍💼 User Profiles</h3>
        <p>Manage your listings, comments, and item updates from your dashboard.</p>
      </div>
      <div class="feature-card">
        <h3>🔔 Notifications</h3>
        <p>Get alerts when an item matching your description is reported.</p>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="how-it-works">
    <h2>How It Works</h2>
    <div class="steps">
      <div class="step">
        <span>1</span>
        <h4>Report an Item</h4>
        <p>Post details of a lost or found item, including photos and date.</p>
      </div>
      <div class="step">
        <span>2</span>
        <h4>Browse Listings</h4>
        <p>Search for items that match your description or location.</p>
      </div>
      <div class="step">
        <span>3</span>
        <h4>Connect & Recover</h4>
        <p>Contact the finder or owner securely through comments or messages.</p>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <p>© 2025 Lost and Found System | Designed by <a href="#">Isaack Gitau</a></p>
  </footer>
</body>
</html>
