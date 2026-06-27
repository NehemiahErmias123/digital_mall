<?php
session_start();
 
$error   = "";
$success = "";
 
$role = "customer";
if (isset($_GET['role']))  { $role = $_GET['role']; }
if (isset($_POST['role'])) { $role = $_POST['role']; }
if (!in_array($role, ['customer','manager'])) { $role = 'customer'; }
 
$page_title   = $role == "manager" ? "Shop Owner Sign Up"  : "Customer Sign Up";
$page_heading = $role == "manager" ? "Register your shop 🏪" : "Create account ✨";
 
if (isset($_POST['submit'])) {
 
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];
 
    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "Please fill in all fields.";
    } elseif ($password != $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
 
        require 'config.php';
        if (!$conn) {
            $error = "Database connection failed.";
        } else {
            $email_safe = mysqli_real_escape_string($conn, $email);
            $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email_safe'");
            if (mysqli_num_rows($check) > 0) {
                $error = "An account with this email already exists.";
            } else {
 
                if ($role == "customer") {
                    $address = trim($_POST['address']);
                    if (empty($address)) {
                        $error = "Please enter your delivery address.";
                    } else {
                        $name_s    = mysqli_real_escape_string($conn, $name);
                        $pass_s    = password_hash($password, PASSWORD_DEFAULT);
                        $address_s = mysqli_real_escape_string($conn, $address);
                        mysqli_query($conn, "INSERT INTO users (name, email, password, role, address)
                                            VALUES ('$name_s', '$email_safe', '$pass_s', 'Customer', '$address_s')");
                        $success = "Account created! Redirecting to login...";
                        header("Refresh: 2; url=login.php");
                    }
 
                } elseif ($role == "manager") {
                    $shop_name  = trim($_POST['shop_name']);
                    $shop_type  = $_POST['shop_type'];
                    $location   = trim($_POST['location']);
                    $shop_image = trim($_POST['shop_image'] ?? '');

                    if (empty($shop_image)) {
                        $shop_image = 'https://via.placeholder.com/400x300?text=No+Image';
                    }

                    if (empty($shop_name) || empty($shop_type) || empty($location)) {
                        $error = "Please fill in all shop details.";
                    } else {
                        $name_s       = mysqli_real_escape_string($conn, $name);
                        $pass_s       = password_hash($password, PASSWORD_DEFAULT);
                        $shop_name_s  = mysqli_real_escape_string($conn, $shop_name);
                        $shop_type_s  = mysqli_real_escape_string($conn, $shop_type);
                        $location_s   = mysqli_real_escape_string($conn, $location);
                        $shop_image_s = mysqli_real_escape_string($conn, $shop_image);

                        // 1. Create the manager's user account first
                        $result = mysqli_query(
                            $conn,
                            "INSERT INTO users (name, email, password, role)
                             VALUES ('$name_s', '$email_safe', '$pass_s', 'Manager')"
                        );

                        if (!$result) {
                            die("Database Error: " . mysqli_error($conn));
                        }

                        $new_manager_id = mysqli_insert_id($conn);

                        // 2. Create their new shop, linked to that manager
                        $result2 = mysqli_query(
                            $conn,
                            "INSERT INTO shops (name, type, location, manager_id, status, total_sales, sub_start, sub_end, image)
                             VALUES ('$shop_name_s', '$shop_type_s', '$location_s', $new_manager_id, 'active', 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), '$shop_image_s')"
                        );

                        if (!$result2) {
                            die("Database Error: " . mysqli_error($conn));
                        }

                        $success = "Shop owner account created! Redirecting to login...";
                        header("Refresh: 2; url=login.php");
                    }
                }
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
    <title><?php echo $page_title; ?> — Digital Mall</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page-wrapper">
 
    <div class="left-panel">
        <div class="grid-overlay"></div>
        <a href="login.php" class="logo">
            <div class="logo-box">DM</div>
            <div class="logo-text">
                <span class="logo-name">DigitalMall</span>
                <span class="logo-tag">Smart Commerce</span>
            </div>
        </a>
        <div class="hero-content">
            <h1 class="hero-title">Join the<br><span>future of</span><br>shopping</h1>
            <p class="hero-desc">Create your free account and get access to hundreds of shops and exclusive deals.</p>
            <div class="features">
                <div class="feat">🛍️ Shop Anywhere</div>
                <div class="feat">📦 Track Orders</div>
                <div class="feat">⭐ Rate & Review</div>
                <div class="feat">🔐 Secure Checkout</div>
            </div>
        </div>
    </div>
 
    <div class="right-panel">
        <div class="form-card">
            <div class="form-header">
                <h2><?php echo $page_heading; ?></h2>
                <p>Already have an account? <a href="login.php">Sign in</a></p>
                <p style="margin-top:6px;">
                    Wrong page?
                    <?php if ($role != "customer") { ?><a href="signup.php?role=customer">Sign up as Customer</a> · <?php } ?>
                    <?php if ($role != "manager")  { ?><a href="signup.php?role=manager">Shop Owner</a><?php } ?>
                </p>
            </div>
 
            <?php if ($error   != "") { ?><div class="alert alert-error">⚠️ <?php echo $error; ?></div><?php } ?>
            <?php if ($success != "") { ?><div class="alert alert-success">🎉 <?php echo $success; ?></div><?php } ?>
 
            <form method="POST" action="signup.php">
                <input type="hidden" name="role" value="<?php echo $role; ?>">
 
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="Jane Doe"
                               value="<?php if (isset($_POST['name'])) echo htmlspecialchars($_POST['name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="text" name="email" placeholder="you@example.com"
                               value="<?php if (isset($_POST['email'])) echo htmlspecialchars($_POST['email']); ?>">
                    </div>
                </div>
 
                <?php if ($role == "customer") { ?>
                <div class="form-group">
                    <label>Delivery Address</label>
                    <input type="text" name="address" placeholder="123 Main St, City, Country"
                           value="<?php if (isset($_POST['address'])) echo htmlspecialchars($_POST['address']); ?>">
                </div>
                <?php } ?>
 
                <?php if ($role == "manager") { ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="shop_name" placeholder="e.g. Brew & Co."
                               value="<?php if (isset($_POST['shop_name'])) echo htmlspecialchars($_POST['shop_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="e.g. Floor 2, Block A"
                               value="<?php if (isset($_POST['location'])) echo htmlspecialchars($_POST['location']); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Shop Type</label>
                    <select name="shop_type">
                        <option value="">-- Select a type --</option>
                        <option value="Coffeeshop">☕ Coffee Shop</option>
                        <option value="Restaurant">🍽️ Restaurant</option>
                        <option value="Clothing">👗 Clothing</option>
                        <option value="Electronics">📱 Electronics</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Shop Image URL</label>
                    <input type="text" name="shop_image" placeholder="https://example.com/your-shop-photo.jpg"
                           value="<?php if (isset($_POST['shop_image'])) echo htmlspecialchars($_POST['shop_image']); ?>">
                </div>
                <?php } ?>
 
                <div class="form-row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min. 6 characters">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm" placeholder="Repeat password">
                    </div>
                </div>
 
                <button type="submit" name="submit" class="btn-submit">Create My Account →</button>
            </form>
        </div>
    </div>
 
</div>
</body>
</html>