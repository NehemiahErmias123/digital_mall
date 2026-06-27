<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Manager') {
    header("Location: login.php"); exit;
}

require 'config.php';
$manager_id = (int)$_SESSION['user_id'];

$shop_result = mysqli_query(
    $conn,
    "SELECT id FROM shops WHERE manager_id = $manager_id"
);

$shop = mysqli_fetch_assoc($shop_result);

if (!$shop) {
    die("No shop assigned to this manager. Manager ID = " . $manager_id);
}

$shop_id = (int)$shop['id'];

$page = isset($_GET['page']) ? $_GET['page'] : "home";

// ── ORDER STATUS UPDATE (manager changes status manually) ──
if (isset($_POST['update_status'])) {
    $order_id   = (int)$_POST['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $allowed    = ['accepted','being_prepared','shipped','delivered','cancelled'];
    if (in_array($new_status, $allowed)) {
        $ord_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status, total_price FROM orders WHERE id=$order_id AND shop_id=$shop_id"));
        if ($ord_row) {
            mysqli_query($conn, "UPDATE orders SET status='$new_status' WHERE id=$order_id AND shop_id=$shop_id");

            // Only add to sales the moment it FIRST becomes delivered (avoids double-counting)
            if ($new_status == 'delivered' && $ord_row['status'] != 'delivered') {
                $amount = (float)$ord_row['total_price'];
                mysqli_query($conn, "UPDATE shops SET total_sales = total_sales + $amount WHERE id=$shop_id");
            }
        }
    }
    header("Location: manager_dashboard.php?page=orders"); exit;
}

// ── DELETE PRODUCT ──
if (isset($_GET['delete_product'])) {
    $del_id = (int)$_GET['delete_product'];
    mysqli_query($conn, "DELETE FROM products WHERE id=$del_id AND shop_id=$shop_id");
    header("Location: manager_dashboard.php?page=products&deleted=1"); exit;
}

// ── UPDATE PRODUCT (includes image) ──
if (isset($_POST['update_product'])) {
    $upd_id    = (int)$_POST['update_id'];
    $upd_name  = mysqli_real_escape_string($conn, $_POST['upd_name']);
    $upd_price = (float)$_POST['upd_price'];
    $upd_stock = (int)$_POST['upd_stock'];
    $upd_image = mysqli_real_escape_string($conn, trim($_POST['upd_image'] ?? ''));
    $image_sql = ($upd_image === '') ? "NULL" : "'$upd_image'";
    mysqli_query($conn, "UPDATE products SET name='$upd_name', price=$upd_price, stock=$upd_stock, image=$image_sql WHERE id=$upd_id AND shop_id=$shop_id");
    header("Location: manager_dashboard.php?page=products&updated=1"); exit;
}

// ── ADD CATEGORY ── (runs before the categories list is fetched below)
$cat_msg = "";
if (isset($_POST['add_category'])) {
    $cat_name = mysqli_real_escape_string($conn, $_POST['cat_name']);
    if (empty($cat_name)) { $cat_msg = "error:Category name is required."; }
    else { mysqli_query($conn, "INSERT INTO categories (name, shop_id) VALUES ('$cat_name',$shop_id)"); $cat_msg = "success:Category added!"; }
}

// ── ADD PRODUCT ── (runs before the products list is fetched below; category + image optional)
$prod_msg = "";
if (isset($_POST['add_product'])) {
    $prod_name    = mysqli_real_escape_string($conn, $_POST['prod_name']);
    $prod_price   = (float)$_POST['prod_price'];
    $prod_stock   = (int)$_POST['prod_stock'];
    $prod_cat_raw = $_POST['prod_category'];
    $prod_cat     = ($prod_cat_raw === '') ? null : (int)$prod_cat_raw;
    $prod_desc    = mysqli_real_escape_string($conn, $_POST['prod_desc']);
    $prod_image   = mysqli_real_escape_string($conn, trim($_POST['prod_image'] ?? ''));

    if (empty($prod_name) || $prod_price <= 0 || $prod_stock < 0) {
        $prod_msg = "error:Please fill in all required fields.";
    } else {
        $cat_sql   = ($prod_cat === null) ? "NULL" : $prod_cat;
        $image_sql = ($prod_image === '') ? "NULL" : "'$prod_image'";
        mysqli_query($conn, "INSERT INTO products (name, price, stock, category_id, description, image, shop_id)
                             VALUES ('$prod_name',$prod_price,$prod_stock,$cat_sql,'$prod_desc',$image_sql,$shop_id)");
        $prod_msg = "success:Product added!";
    }
}

// ── SHOP INFO & STATS ──
$shop_row = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM shops WHERE id=$shop_id")
);

if (!$shop_row) {
    die("Shop not found. shop_id = " . $shop_id);
}

$shop_name   = $shop_row["name"];
$total_sales = $shop_row["total_sales"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products WHERE shop_id=$shop_id"));
$total_products = $row["c"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status='pending'"));
$pending_orders = $row["c"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE shop_id=$shop_id AND status='delivered'"));
$total_delivered = $row["c"];

// ── ORDER STATUS COUNTS ──
$counts = ['pending'=>0,'accepted'=>0,'being_prepared'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0];
$sq = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM orders WHERE shop_id=$shop_id GROUP BY status");
while ($sr = mysqli_fetch_assoc($sq)) {
    if (isset($counts[$sr['status']])) $counts[$sr['status']] = $sr['c'];
}

// ── RECENT ORDERS (home) ──
$recent_result = mysqli_query($conn, "SELECT o.id, o.total_price, o.status, u.name AS customer
                                      FROM orders o JOIN users u ON o.user_id=u.id
                                      WHERE o.shop_id=$shop_id ORDER BY o.id DESC LIMIT 5");
$orders = [];
while ($r = mysqli_fetch_assoc($recent_result)) { $orders[] = $r; }

// ── CATEGORIES ──
$cat_result = mysqli_query($conn, "SELECT * FROM categories WHERE shop_id=$shop_id ORDER BY name");
$categories = [];
while ($c = mysqli_fetch_assoc($cat_result)) { $categories[] = $c; }

// ── PRODUCTS ──
$prod_result = mysqli_query($conn, "SELECT p.*, c.name AS cat_name FROM products p
                                    LEFT JOIN categories c ON p.category_id=c.id
                                    WHERE p.shop_id=$shop_id ORDER BY p.id DESC");
$products = [];
while ($p = mysqli_fetch_assoc($prod_result)) { $products[] = $p; }

// ── ALL ORDERS ──
$orders_result = mysqli_query($conn, "SELECT o.id, o.quantity, o.total_price, o.status, o.address, o.created_at,
                                             u.name AS customer, p.name AS product_name
                                      FROM orders o
                                      JOIN users u ON o.user_id=u.id
                                      JOIN products p ON o.product_id=p.id
                                      WHERE o.shop_id=$shop_id ORDER BY o.id DESC");
$all_orders = [];
while ($o = mysqli_fetch_assoc($orders_result)) { $all_orders[] = $o; }

// ── DELIVERIES (delivered orders) ──
$del_result = mysqli_query($conn, "SELECT o.id, o.quantity, o.total_price, o.status, o.address, o.created_at,
                                          u.name AS customer, p.name AS product_name
                                   FROM orders o
                                   JOIN users u ON o.user_id=u.id
                                   JOIN products p ON o.product_id=p.id
                                   WHERE o.shop_id=$shop_id AND o.status='delivered'
                                   ORDER BY o.id DESC");
$deliveries = [];
while ($d = mysqli_fetch_assoc($del_result)) { $deliveries[] = $d; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Mall — Manager</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<nav class="admin-nav">
  <div class="nav-logo">
    <div class="nav-brand">Digital<em>Mall</em></div>
    <div class="nav-badge manager-badge">Manager Panel</div>
  </div>
  <div class="nav-links">
    <a href="?page=home"       <?php if ($page=="home")       echo 'class="active"'; ?>>&#9741; &nbsp; Home</a>
    <a href="?page=products"   <?php if ($page=="products")   echo 'class="active"'; ?>>&#128722; &nbsp; Products</a>
    <a href="?page=orders"     <?php if ($page=="orders")     echo 'class="active"'; ?>>&#128203; &nbsp; Orders</a>
    <a href="?page=deliveries" <?php if ($page=="deliveries") echo 'class="active"'; ?>>&#128666; &nbsp; Deliveries</a>
  </div>
  <div class="nav-bottom">
    <a href="logout.php">&#x2BA8; &nbsp; Logout</a>
  </div>
</nav>

<main class="admin-main">

<!-- HOME -->
<?php if ($page == "home") { ?>

  <div class="page-header">
    <h1><?php echo htmlspecialchars($shop_name); ?> — Dashboard</h1>
    <p>Your shop overview and performance summary</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><div class="label">Total Revenue</div><div class="value">$<?php echo number_format($total_sales,2); ?></div><div class="sub">Lifetime shop sales</div></div>
    <div class="stat-card"><div class="label">Total Products</div><div class="value"><?php echo $total_products; ?></div><div class="sub">Listed in store</div></div>
    <div class="stat-card"><div class="label">Pending Orders</div><div class="value"><?php echo $pending_orders; ?></div><div class="sub">Awaiting your action</div></div>
    <div class="stat-card"><div class="label">Delivered</div><div class="value"><?php echo $total_delivered; ?></div><div class="sub">Completed orders</div></div>
  </div>

  <div class="section-label">Shop Analytics</div>
  <div class="charts-row">
    <div class="chart-card"><h3>Order Status Overview</h3><canvas id="ordersChart" height="110"></canvas></div>
    <div class="chart-card"><h3>Order Breakdown</h3><canvas id="ordersPieChart" height="160"></canvas></div>
  </div>

  <div class="section-label">Recent Orders</div>
  <div class="table-card">
    <div class="table-top"><h2>Last 5 Orders</h2></div>
    <table>
      <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (count($orders) == 0) { ?>
          <tr><td colspan="4" style="text-align:center;color:#7a7a9a;padding:28px;">No orders yet.</td></tr>
        <?php } ?>
        <?php for ($i = 0; $i < count($orders); $i++) { ?>
        <tr>
          <td class="muted">#<?php echo $orders[$i]["id"]; ?></td>
          <td><?php echo htmlspecialchars($orders[$i]["customer"]); ?></td>
          <td>$<?php echo number_format($orders[$i]["total_price"],2); ?></td>
          <td><span class="status-badge status-<?php echo $orders[$i]["status"]; ?>"><?php echo ucfirst(str_replace('_',' ',$orders[$i]["status"])); ?></span></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

  <script>
    var labels  = ['Pending','Accepted','Being Prepared','Shipped','Delivered','Cancelled'];
    var data    = [<?php echo implode(',',array_values($counts)); ?>];
    var bgColors= ['rgba(245,158,11,0.2)','rgba(34,197,94,0.2)','rgba(99,102,241,0.2)','rgba(59,130,246,0.2)','rgba(168,85,247,0.2)','rgba(107,114,128,0.2)'];
    var borders = ['#f59e0b','#22c55e','#818cf8','#60a5fa','#c084fc','#9ca3af'];
    new Chart(document.getElementById('ordersChart').getContext('2d'), {
      type:'bar', data:{ labels:labels, datasets:[{ data:data, backgroundColor:bgColors, borderColor:borders, borderWidth:2, borderRadius:6 }] },
      options:{ plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'}}, y:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'},beginAtZero:true} } }
    });
    new Chart(document.getElementById('ordersPieChart').getContext('2d'), {
      type:'doughnut', data:{ labels:labels, datasets:[{ data:data, backgroundColor:bgColors, borderColor:borders, borderWidth:2 }] },
      options:{ cutout:'70%', plugins:{legend:{position:'bottom',labels:{color:'#7a7a9a',padding:12,font:{size:11}}}} }
    });
  </script>

<!-- PRODUCTS -->
<?php } else if ($page == "products") { ?>

  <div class="products-toolbar">
    <div><h1>Products</h1><p class="toolbar-sub">Manage your shop's product catalogue</p></div>
    <div class="toolbar-btns">
      <button class="btn-secondary" onclick="openModal('catModal')">+ Add Category</button>
      <button class="btn-add"       onclick="openModal('prodModal')">+ Add Product</button>
    </div>
  </div>

  <?php if (isset($_GET['deleted'])) { echo '<div class="flash-msg">Product deleted.</div>'; } ?>
  <?php if (isset($_GET['updated'])) { echo '<div class="flash-msg">Product updated.</div>'; } ?>
  <?php
  foreach ([$cat_msg, $prod_msg] as $msg) {
      if ($msg != "") {
          $parts = explode(":", $msg, 2);
          $cls   = $parts[0] == "success" ? "flash-msg" : "flash-msg flash-err";
          echo '<div class="'.$cls.'">'.$parts[1].'</div>';
      }
  }
  ?>

  <div class="products-grid">
    <?php if (count($products) == 0) { ?>
      <p style="color:#7a7a9a;">No products yet. Click <strong>+ Add Product</strong> to get started.</p>
    <?php } ?>
    <?php for ($i = 0; $i < count($products); $i++) { ?>
    <div class="product-card">
      <div class="prod-img-wrap">
        <?php if ($products[$i]["image"]) { ?>
          <img src="<?php echo htmlspecialchars($products[$i]["image"]); ?>" alt="<?php echo htmlspecialchars($products[$i]["name"]); ?>" class="prod-img">
        <?php } else { ?>
          <div class="prod-img-placeholder">&#128722;</div>
        <?php } ?>
      </div>
      <div class="prod-body">
        <div class="cat-tag"><?php echo $products[$i]["cat_name"] ? htmlspecialchars($products[$i]["cat_name"]) : "Uncategorized"; ?></div>
        <div class="prod-name"><?php echo htmlspecialchars($products[$i]["name"]); ?></div>
        <div class="prod-desc"><?php echo htmlspecialchars($products[$i]["description"]); ?></div>
        <div class="prod-footer">
          <div class="prod-price">$<?php echo number_format($products[$i]["price"],2); ?></div>
          <div class="prod-stock">Stock: <span><?php echo $products[$i]["stock"]; ?></span></div>
        </div>
        <div class="action-btns" style="margin-top:10px;">
          <button class="btn-edit" onclick="openUpdateModal(<?php echo $products[$i]['id']; ?>,'<?php echo addslashes($products[$i]['name']); ?>',<?php echo $products[$i]['price']; ?>,<?php echo $products[$i]['stock']; ?>,'<?php echo addslashes($products[$i]['image'] ?? ''); ?>')">Update</button>
          <a href="?page=products&delete_product=<?php echo $products[$i]['id']; ?>" onclick="return confirm('Delete this product?')" class="btn-del" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">Delete</a>
        </div>
      </div>
    </div>
    <?php } ?>
  </div>

  <!-- Add Category Modal -->
  <div class="modal-overlay" id="catModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('catModal')">&#x2715;</button>
      <h3>Add Category</h3>
      <form method="POST" action="?page=products">
        <div class="form-group"><label>Category Name</label><input type="text" name="cat_name" placeholder="e.g. Beverages"></div>
        <button type="submit" name="add_category" class="btn-submit" style="margin-top:8px;">Create Category</button>
      </form>
    </div>
  </div>

  <!-- Add Product Modal -->
  <div class="modal-overlay" id="prodModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('prodModal')">&#x2715;</button>
      <h3>Add New Product</h3>
      <form method="POST" action="?page=products">
        <div class="form-group"><label>Product Name</label><input type="text" name="prod_name" placeholder="e.g. Iced Latte"></div>
        <div class="modal-form-row">
          <div class="form-group"><label>Price ($)</label><input type="number" step="0.01" name="prod_price" placeholder="9.99"></div>
          <div class="form-group"><label>Stock Qty</label><input type="number" name="prod_stock" placeholder="50"></div>
        </div>
        <div class="form-group">
          <label>Category (optional)</label>
          <select name="prod_category">
            <option value="">-- No category --</option>
            <?php for ($i=0; $i<count($categories); $i++) { ?>
              <option value="<?php echo $categories[$i]["id"]; ?>"><?php echo htmlspecialchars($categories[$i]["name"]); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="form-group"><label>Image URL (optional)</label><input type="text" name="prod_image" placeholder="https://example.com/product-photo.jpg"></div>
        <div class="form-group"><label>Description</label><textarea name="prod_desc" placeholder="Short product description..."></textarea></div>
        <button type="submit" name="add_product" class="btn-submit" style="margin-top:8px;">Add Product</button>
      </form>
    </div>
  </div>

  <!-- Update Product Modal -->
  <div class="modal-overlay" id="updateModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('updateModal')">&#x2715;</button>
      <h3>Update Product</h3>
      <form method="POST" action="?page=products">
        <input type="hidden" name="update_id" id="upd_id">
        <div class="form-group"><label>Product Name</label><input type="text" name="upd_name" id="upd_name"></div>
        <div class="modal-form-row">
          <div class="form-group"><label>Price ($)</label><input type="number" step="0.01" name="upd_price" id="upd_price"></div>
          <div class="form-group"><label>Stock Qty</label><input type="number" name="upd_stock" id="upd_stock"></div>
        </div>
        <div class="form-group"><label>Image URL</label><input type="text" name="upd_image" id="upd_image" placeholder="https://example.com/product-photo.jpg"></div>
        <button type="submit" name="update_product" class="btn-submit" style="margin-top:8px;">Save Changes</button>
      </form>
    </div>
  </div>

  <?php if ($cat_msg != "" && strpos($cat_msg,"error")===0)  { echo '<script>openModal("catModal");</script>';  } ?>
  <?php if ($prod_msg != "" && strpos($prod_msg,"error")===0) { echo '<script>openModal("prodModal");</script>'; } ?>

<!-- ORDERS -->
<?php } else if ($page == "orders") { ?>

  <div class="page-header"><h1>Orders</h1><p>Review and manage incoming customer orders</p></div>

  <div class="table-card">
    <div class="table-top"><h2>All Orders</h2></div>
    <table>
      <thead>
        <tr><th>ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Address</th><th>Date</th><th>Status</th><th>Change Status</th></tr>
      </thead>
      <tbody>
        <?php if (count($all_orders) == 0) { ?>
          <tr><td colspan="9" style="text-align:center;color:#7a7a9a;padding:40px;">No orders yet.</td></tr>
        <?php } ?>
        <?php for ($i=0; $i<count($all_orders); $i++) {
            $st = $all_orders[$i]["status"];
        ?>
        <tr>
          <td class="muted">#<?php echo $all_orders[$i]["id"]; ?></td>
          <td><?php echo htmlspecialchars($all_orders[$i]["customer"]); ?></td>
          <td class="muted" style="font-size:13px;"><?php echo htmlspecialchars($all_orders[$i]["product_name"]); ?></td>
          <td><?php echo $all_orders[$i]["quantity"]; ?></td>
          <td><strong style="color:#ff5e1a;">$<?php echo number_format($all_orders[$i]["total_price"],2); ?></strong></td>
          <td class="muted"><?php echo htmlspecialchars($all_orders[$i]["address"]); ?></td>
          <td class="muted"><?php echo $all_orders[$i]["created_at"]; ?></td>
          <td><span class="status-badge status-<?php echo $st; ?>"><?php echo ucfirst(str_replace('_',' ',$st)); ?></span></td>
          <td>
            <?php if ($st != 'delivered' && $st != 'cancelled') { ?>
            <form method="POST" style="display:flex;gap:4px;align-items:center;">
              <input type="hidden" name="order_id" value="<?php echo $all_orders[$i]["id"]; ?>">
              <select name="new_status" style="background:#1a1a24;border:1px solid #2a2a38;border-radius:6px;padding:6px;color:#f0eef8;font-size:12px;">
                <option value="accepted"      <?= $st=='accepted'      ?'selected':'' ?>>Accepted</option>
                <option value="being_prepared"<?= $st=='being_prepared'?'selected':'' ?>>Being Prepared</option>
                <option value="shipped"       <?= $st=='shipped'       ?'selected':'' ?>>Shipped</option>
                <option value="delivered"     <?= $st=='delivered'     ?'selected':'' ?>>Delivered</option>
                <option value="cancelled"                                              >Cancelled</option>
              </select>
              <button type="submit" name="update_status" class="btn-edit">&#10004;</button>
            </form>
            <?php } else { ?>
              <span style="color:#7a7a9a;font-size:12px;"><?php echo ucfirst(str_replace('_',' ',$st)); ?></span>
            <?php } ?>
          </td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

<!-- DELIVERIES -->
<?php } else if ($page == "deliveries") { ?>

  <div class="page-header"><h1>Deliveries</h1><p>All completed and delivered orders</p></div>

  <div class="table-card">
    <div class="table-top"><h2>Delivered Orders</h2></div>
    <table>
      <thead>
        <tr><th>ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Address</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php if (count($deliveries) == 0) { ?>
        <tr><td colspan="7" style="text-align:center;color:#7a7a9a;padding:40px;">No delivered orders yet.</td></tr>
        <?php } ?>
        <?php for ($i=0; $i<count($deliveries); $i++) { ?>
        <tr>
          <td class="muted">#<?php echo $deliveries[$i]["id"]; ?></td>
          <td><?php echo htmlspecialchars($deliveries[$i]["customer"]); ?></td>
          <td class="muted" style="font-size:13px;"><?php echo htmlspecialchars($deliveries[$i]["product_name"]); ?></td>
          <td><?php echo $deliveries[$i]["quantity"]; ?></td>
          <td><strong style="color:#ff5e1a;">$<?php echo number_format($deliveries[$i]["total_price"],2); ?></strong></td>
          <td class="muted"><?php echo htmlspecialchars($deliveries[$i]["address"]); ?></td>
          <td class="muted"><?php echo $deliveries[$i]["created_at"]; ?></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

<?php } ?>

</main>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openUpdateModal(id, name, price, stock, image) {
    document.getElementById('upd_id').value    = id;
    document.getElementById('upd_name').value  = name;
    document.getElementById('upd_price').value = price;
    document.getElementById('upd_stock').value = stock;
    document.getElementById('upd_image').value = image;
    openModal('updateModal');
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) { if (e.target===this) this.classList.remove('open'); });
});
</script>
</body>
</html>