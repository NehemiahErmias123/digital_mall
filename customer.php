<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Customer') {
    header("Location: login.php"); exit;
}

require 'config.php';
$user_id = $_SESSION['user_id'];

$page    = $_GET['page']    ?? 'home';
$shop_id = $_GET['shop_id'] ?? null;

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// ADD TO CART
if (isset($_POST['add_to_cart'])) {
    $id = (int)$_POST['product_id'];
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
}

// UPDATE QTY
if (isset($_POST['update_qty'])) {
    $id  = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    if ($qty < 1) $qty = 1;
    $_SESSION['cart'][$id] = $qty;
}

// REMOVE FROM CART
if (isset($_POST['remove'])) {
    unset($_SESSION['cart'][(int)$_POST['product_id']]);
}

// CONFIRM ORDER — insert one order row per cart item, checking + decrementing stock
$cart_error = "";
if (isset($_POST['confirm_order']) && !empty($_SESSION['cart'])) {

    // Pass 1: make sure nothing in the cart exceeds available stock
    $insufficient = [];
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $pid  = (int)$pid;
        $prow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, stock FROM products WHERE id=$pid"));
        if (!$prow) continue;
        if ($qty > $prow['stock']) {
            $insufficient[] = $prow['name'] . " (only " . $prow['stock'] . " left)";
        }
    }

    if (!empty($insufficient)) {
        $cart_error = "Not enough stock for: " . implode(", ", $insufficient) . ". Please update the quantity.";
    } else {
        // Pass 2: everything has enough stock, place the order and decrement stock
        $user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT address FROM users WHERE id=$user_id"));
        $address  = mysqli_real_escape_string($conn, $user_row['address'] ?? '');

        foreach ($_SESSION['cart'] as $pid => $qty) {
            $pid = (int)$pid;
            $prow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT price, shop_id FROM products WHERE id=$pid"));
            if (!$prow) continue;
            $total = $prow['price'] * $qty;
            $sid   = $prow['shop_id'];
            mysqli_query($conn, "INSERT INTO orders (user_id, product_id, shop_id, quantity, total_price, address, status, created_at)
                                 VALUES ($user_id, $pid, $sid, $qty, $total, '$address', 'pending', NOW())");

            // Decrease stock now that the order has actually been placed
            mysqli_query($conn, "UPDATE products SET stock = stock - $qty WHERE id=$pid");
        }
        $_SESSION['cart'] = [];
        header("Location: customer.php?page=orders&placed=1"); exit;
    }
}

// CANCEL ORDER — restore stock since the order never actually went through
if (isset($_GET['cancel_order'])) {
    $cid = (int)$_GET['cancel_order'];

    $cancel_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id, quantity, status FROM orders WHERE id=$cid AND user_id=$user_id"));

    if ($cancel_row && in_array($cancel_row['status'], ['pending','accepted'])) {
        mysqli_query($conn, "UPDATE orders SET status='cancelled' WHERE id=$cid AND user_id=$user_id AND status IN ('pending','accepted')");

        $restore_qty = (int)$cancel_row['quantity'];
        $restore_pid = (int)$cancel_row['product_id'];
        mysqli_query($conn, "UPDATE products SET stock = stock + $restore_qty WHERE id=$restore_pid");
    }

    header("Location: customer.php?page=orders"); exit;
}

// UPDATE ADDRESS
if (isset($_POST['update_address'])) {
    $new_addr = mysqli_real_escape_string($conn, $_POST['new_address']);
    mysqli_query($conn, "UPDATE users SET address='$new_addr' WHERE id=$user_id");
    header("Location: customer.php?page=account&msg=updated"); exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard — Digital Mall</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="admin-body">

<div class="admin-nav">
    <div class="nav-logo">
        <div class="nav-brand">Digital<em>Mall</em></div>
        <span class="nav-badge">Customer</span>
    </div>
    <div class="nav-links">
        <a href="?page=home"   class="<?= $page=='home'   ? 'active':'' ?>"><i class="fas fa-store"></i> Home</a>
        <a href="?page=cart"   class="<?= $page=='cart'   ? 'active':'' ?>"><i class="fas fa-shopping-cart"></i> Cart <?php if (!empty($_SESSION['cart'])) echo '<span style="color:#ff5e1a;">('.array_sum($_SESSION['cart']).')</span>'; ?></a>
        <a href="?page=orders" class="<?= $page=='orders' ? 'active':'' ?>"><i class="fas fa-box"></i> Orders</a>
        <a href="?page=account" class="<?= $page=='account'? 'active':'' ?>"><i class="fas fa-user"></i> Account</a>
    </div>
    <div class="nav-bottom">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="admin-main">

<!-- HOME -->
<?php if ($page == 'home'): ?>
    <div class="page-header"><h1>Stores</h1><p>Browse available shops</p></div>
    <div class="products-grid">
    <?php
    $shops = mysqli_query($conn, "SELECT * FROM shops WHERE status='active'");
    while ($shop = mysqli_fetch_assoc($shops)):
    ?>
        <div class="product-card">
            <div class="prod-img-wrap"><img src="<?= htmlspecialchars($shop['image']) ?>" class="prod-img" alt="<?= htmlspecialchars($shop['name']) ?>"></div>
            <div class="prod-body">
                <div class="cat-tag">Store</div>
                <div class="prod-name"><?= htmlspecialchars($shop['name']) ?></div>
                <div class="prod-desc">Total Sales: $<?= number_format($shop['total_sales'],2) ?></div>
                <div class="prod-footer">
                    <a href="?page=store&shop_id=<?= $shop['id'] ?>" class="btn-add" style="text-decoration:none;">View Store</a>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

<!-- STORE -->
<?php elseif ($page == 'store' && $shop_id):
    $shop_id  = (int)$shop_id;
    $shop_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shops WHERE id=$shop_id"));
?>
    <div class="page-header">
        <h1><?= htmlspecialchars($shop_info['name']) ?></h1>
        <p><a href="?page=home" style="color:#ff5e1a;text-decoration:none;">&#8592; Back to Stores</a></p>
    </div>
    <div class="products-grid">
    <?php
    $products = mysqli_query($conn, "SELECT p.*, c.name AS cat_name FROM products p
                                     LEFT JOIN categories c ON p.category_id = c.id
                                     WHERE p.shop_id=$shop_id");
    while ($p = mysqli_fetch_assoc($products)):
    ?>
        <div class="product-card">
            <div class="prod-img-wrap"><img src="<?= htmlspecialchars($p['image']) ?>" class="prod-img" alt="<?= htmlspecialchars($p['name']) ?>"></div>
            <div class="prod-body">
                <div class="cat-tag"><?= $p['cat_name'] ? htmlspecialchars($p['cat_name']) : 'Product' ?></div>
                <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="prod-desc"><?= htmlspecialchars($p['description']) ?></div>
                <div class="prod-footer">
                    <div class="prod-price">$<?= number_format($p['price'],2) ?></div>
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <button name="add_to_cart" class="btn-add">+ Add</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
    </div>

<!-- CART -->
<?php elseif ($page == 'cart'): ?>
    <div class="page-header"><h1>Your Cart</h1><p>Review your items before placing an order</p></div>
    <?php if ($cart_error != "") { ?>
        <div class="flash-msg flash-err" style="margin-bottom:16px;"><?= htmlspecialchars($cart_error) ?></div>
    <?php } ?>
    <?php if (empty($_SESSION['cart'])): ?>
        <div style="text-align:center;padding:60px 20px;color:#7a7a9a;">
            <div style="font-size:48px;margin-bottom:12px;">&#128722;</div>
            <p>Your cart is empty. <a href="?page=home" style="color:#ff5e1a;">Browse stores</a></p>
        </div>
    <?php else: ?>
    <div class="table-card">
        <table>
            <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>Remove</th></tr></thead>
            <tbody>
            <?php
            $total = 0;
            foreach ($_SESSION['cart'] as $id => $qty):
                $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id=".(int)$id));
                if (!$p) continue;
                $sub = $p['price'] * $qty; $total += $sub;
            ?>
            <tr>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td class="muted">$<?= number_format($p['price'],2) ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:5px;align-items:center;">
                        <input type="hidden" name="product_id" value="<?= $id ?>">
                        <input type="number" name="qty" value="<?= $qty ?>" min="1" style="width:60px;background:#1a1a24;border:1px solid #2a2a38;border-radius:6px;padding:6px;color:#f0eef8;">
                        <button name="update_qty" class="btn-edit">&#10004;</button>
                    </form>
                </td>
                <td><strong style="color:#ff5e1a;">$<?= number_format($sub,2) ?></strong></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="product_id" value="<?= $id ?>">
                        <button name="remove" class="btn-del">&#10005;</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="padding:24px;border-top:1px solid #2a2a38;">
            <div style="display:flex;flex-direction:column;gap:8px;max-width:300px;margin-left:auto;">
                <div style="display:flex;justify-content:space-between;font-size:14px;color:#7a7a9a;"><span>Subtotal</span><span>$<?= number_format($total,2) ?></span></div>
                <div style="display:flex;justify-content:space-between;font-size:14px;color:#7a7a9a;"><span>Tax (8%)</span><span>$<?= number_format($total*0.08,2) ?></span></div>
                <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;color:#f0eef8;padding-top:10px;border-top:1px solid #2a2a38;margin-top:4px;"><span>Total</span><span style="color:#ff5e1a;">$<?= number_format($total*1.08,2) ?></span></div>
                <form method="POST">
                    <button name="confirm_order" class="btn-submit" style="width:100%;margin-top:16px;">Confirm Order</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- ORDERS -->
<?php elseif ($page == 'orders'): ?>
    <div class="page-header"><h1>My Orders</h1><p>Track and manage your placed orders</p></div>
    <?php if (isset($_GET['placed'])): ?>
        <div class="flash-msg">Your order has been placed successfully!</div>
    <?php endif; ?>
    <div class="table-card">
        <table>
            <thead><tr><th>ID</th><th>Product</th><th>Qty</th><th>Total</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php
            $ords = mysqli_query($conn, "SELECT o.id, o.quantity, o.total_price, o.status, o.created_at, p.name AS product_name
                                         FROM orders o
                                         JOIN products p ON o.product_id = p.id
                                         WHERE o.user_id = $user_id
                                         ORDER BY o.id DESC");
            $has = false;
            while ($ord = mysqli_fetch_assoc($ords)):
                $has = true;
                $st  = $ord['status'];
                $can = ($st == 'pending' || $st == 'accepted');
            ?>
            <tr>
                <td class="muted">#<?= $ord['id'] ?></td>
                <td><?= htmlspecialchars($ord['product_name']) ?></td>
                <td><?= $ord['quantity'] ?></td>
                <td><strong style="color:#ff5e1a;">$<?= number_format($ord['total_price'],2) ?></strong></td>
                <td class="muted"><?= $ord['created_at'] ?></td>
                <td><span class="status-badge status-<?= $st ?>"><?= ucfirst($st) ?></span></td>
                <td>
                    <?php if ($can): ?>
                        <a href="?page=orders&cancel_order=<?= $ord['id'] ?>" onclick="return confirm('Cancel this order?')" class="btn-reject">Cancel</a>
                    <?php else: ?>
                        <button class="btn-del" disabled style="opacity:0.3;cursor:not-allowed;">Cancel</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if (!$has): ?>
            <tr><td colspan="7" style="text-align:center;color:#7a7a9a;padding:40px;">You have no orders yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ACCOUNT -->
<?php elseif ($page == 'account'):
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id"));
?>
    <div class="page-header"><h1>My Account</h1><p>Your personal details</p></div>
    <?php if (isset($_GET['msg'])): ?><div class="flash-msg">Address updated successfully.</div><?php endif; ?>
    <div class="form-card">
        <div class="form-group"><label>Full Name</label><input type="text" value="<?= htmlspecialchars($user['name']) ?>" readonly></div>
        <div class="form-group"><label>Email Address</label><input type="text" value="<?= htmlspecialchars($user['email']) ?>" readonly></div>
        <div class="form-group"><label>Role</label><input type="text" value="<?= $user['role'] ?>" readonly></div>
        <form method="POST" style="margin-top:16px;">
            <div class="form-group">
                <label>Delivery Address</label>
                <input type="text" name="new_address" value="<?= htmlspecialchars($user['address'] ?? '') ?>" placeholder="Enter your address">
            </div>
            <button name="update_address" class="btn-submit" style="max-width:200px;">Update Address</button>
        </form>
    </div>

<?php endif; ?>
</div>
</body>
</html>