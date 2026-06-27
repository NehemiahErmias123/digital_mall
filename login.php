<?php
session_start();

$error   = "";
$success = "";

if (isset($_POST['submit'])) {

    $email    = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        require 'config.php';
        if (!$conn) {
            $error = "Database connection failed.";
        } else {
            $email_safe = mysqli_real_escape_string($conn, $email);
            $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email_safe'");
            $user   = mysqli_fetch_assoc($result);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['shop_id']   = $user['shop_id'] ?? null;

                if ($user['role'] == 'Admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'Manager') {
                    header("Location: manager_dashboard.php");
                } else {
                    header("Location: customer.php");
                }
                exit;
            } else {
                $error = "Wrong email or password. Please try again.";
            }
            mysqli_close($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Digital Mall</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrapper">

    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="grid-overlay"></div>

        <a href="#" class="logo">
            <div class="logo-box">DM</div>
            <div class="logo-text">
                <span class="logo-name">DigitalMall</span>
                <span class="logo-tag">Smart Commerce</span>
            </div>
        </a>

        <div class="hero-content">
            <h1 class="hero-title">Your city's mall —<br><span>fully digital</span></h1>
            <p class="hero-desc">Browse coffee shops, restaurants, fashion, and electronics all in one place.</p>

            <div class="features">
                <div class="feat">☕ Coffee Shops</div>
                <div class="feat">🍽️ Restaurants</div>
                <div class="feat">👗 Clothing</div>
                <div class="feat">📱 Electronics</div>
            </div>

            <div class="stats-row">
                <div class="stat"><div class="stat-num">120+</div><div class="stat-label">Shops</div></div>
                <div class="stat"><div class="stat-num">8K</div><div class="stat-label">Products</div></div>
                <div class="stat"><div class="stat-num">24/7</div><div class="stat-label">Open</div></div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="form-card">

            <div class="form-header">
                <h2>Welcome back 👋</h2>
                <p>Enter your credentials to sign in.</p>
            </div>

            <?php if ($error != "") { ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php } ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="text" id="email" name="email" placeholder="you@example.com"
                           value="<?php if (isset($_POST['email'])) echo htmlspecialchars($_POST['email']); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password">
                </div>
                <div class="form-extras">
                    <label class="remember-wrap">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                <button type="submit" name="submit" class="btn-submit">Sign In →</button>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 16px; margin-bottom: 20px;">
                    <a href="signup.php?role=customer" style="color:#FF5E1A; text-decoration:underline; font-size:15px;">Sign up</a>
                    <a href="signup.php?role=manager"  style="color:#FF5E1A; text-decoration:underline; font-size:15px;">Sign up as a Shop Owner</a>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>