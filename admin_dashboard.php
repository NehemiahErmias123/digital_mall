<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Admin') {
    header("Location: login.php"); exit;
}

require 'config.php';
if (!$conn) { echo "Connection failed."; exit; }

$page = "home";
if (isset($_GET['page'])) { $page = $_GET['page']; }

// ── DELETE USER ──
if (isset($_GET['delete_user'])) {
    $del_id = (int)$_GET['delete_user'];
    mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
    header("Location: admin_dashboard.php?page=users&msg=deleted"); exit;
}

// ── UPDATE USER ROLE ──
if (isset($_POST['update_user'])) {
    $upd_id   = (int)$_POST['upd_user_id'];
    $upd_role = mysqli_real_escape_string($conn, $_POST['upd_role']);
    $upd_name = mysqli_real_escape_string($conn, $_POST['upd_name']);
    mysqli_query($conn, "UPDATE users SET name='$upd_name', role='$upd_role' WHERE id=$upd_id");
    header("Location: admin_dashboard.php?page=users&msg=updated"); exit;
}

// ── ADD MANAGER ──
if (isset($_POST['add_manager'])) {
    $mgr_name  = mysqli_real_escape_string($conn, $_POST['mgr_name']);
    $mgr_email = mysqli_real_escape_string($conn, $_POST['mgr_email']);
    $mgr_pass = password_hash($_POST['mgr_pass'], PASSWORD_DEFAULT);
    $mgr_shop  = (int)$_POST['mgr_shop'];
    $err = "";
    if (empty($mgr_name) || empty($mgr_email) || empty($mgr_pass) || empty($mgr_shop)) {
        $err = "Please fill in all fields.";
    } else {
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE email='$mgr_email'");
        if (mysqli_num_rows($chk) > 0) {
            $err = "Email already in use.";
        } else {
            $chk2 = mysqli_query($conn, "SELECT id FROM users WHERE shop_id=$mgr_shop AND role='Manager'");
            if (mysqli_num_rows($chk2) > 0) {
                $err = "This shop already has a manager.";
            } else {
                mysqli_query($conn, "INSERT INTO users (name, email, password, role, shop_id)
                                    VALUES ('$mgr_name','$mgr_email','$mgr_pass','Manager',$mgr_shop)");
                header("Location: admin_dashboard.php?page=users&msg=added"); exit;
            }
        }
    }
}

// ── UPDATE SHOP STATUS ──
if (isset($_POST['update_shop'])) {
    $sh_id     = (int)$_POST['sh_id'];
    $sh_status = mysqli_real_escape_string($conn, $_POST['sh_status']);
    mysqli_query($conn, "UPDATE shops SET status='$sh_status' WHERE id=$sh_id");
    header("Location: admin_dashboard.php?page=shops&msg=updated"); exit;
}

// ── DELETE SHOP ──
if (isset($_GET['delete_shop'])) {
    $del_sh = (int)$_GET['delete_shop'];
    mysqli_query($conn, "DELETE FROM shops WHERE id=$del_sh");
    header("Location: admin_dashboard.php?page=shops&msg=deleted"); exit;
}

// ── STATS ──
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"));
$total_users = $row["total"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM shops WHERE status='active'"));
$active_shops = $row["total"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM shops"));
$total_shops = $row["total"];

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_sales) AS total FROM shops"));
$total_revenue = $row["total"] ?? 0;

$count_customer = 0; $count_manager = 0; $count_admin = 0;
$q = mysqli_query($conn, "SELECT role, COUNT(*) AS total FROM users GROUP BY role");
while ($r = mysqli_fetch_assoc($q)) {
    if ($r["role"] == "Customer") $count_customer = $r["total"];
    if ($r["role"] == "Manager")  $count_manager  = $r["total"];
    if ($r["role"] == "Admin")    $count_admin    = $r["total"];
}

$count_active = 0; $count_maintenance = 0; $count_disabled = 0; $count_closed = 0;
$q = mysqli_query($conn, "SELECT status, COUNT(*) AS total FROM shops GROUP BY status");
while ($r = mysqli_fetch_assoc($q)) {
    if ($r["status"] == "active")      $count_active      = $r["total"];
    if ($r["status"] == "maintenance") $count_maintenance = $r["total"];
    if ($r["status"] == "disabled")    $count_disabled    = $r["total"];
    if ($r["status"] == "closed")      $count_closed      = $r["total"];
}

$shop_names = ""; $shop_sales = ""; $first = true;
$q = mysqli_query($conn, "SELECT name, total_sales FROM shops ORDER BY id");
while ($r = mysqli_fetch_assoc($q)) {
    if ($first) { $shop_names = "'".$r["name"]."'"; $shop_sales = $r["total_sales"]; $first = false; }
    else        { $shop_names .= ", '".$r["name"]."'"; $shop_sales .= ", ".$r["total_sales"]; }
}

$users_result = mysqli_query($conn, "SELECT id, name, email, role FROM users");
$shops_result = mysqli_query($conn, "SELECT id, name, total_sales, sub_start, sub_end, status FROM shops");

// Shops without a manager (for Add Manager dropdown)
$free_shops = mysqli_query($conn, "SELECT s.id, s.name FROM shops s
                                   LEFT JOIN users u ON u.shop_id = s.id AND u.role = 'Manager'
                                   WHERE u.id IS NULL ORDER BY s.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Mall - Admin</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-body">

<nav class="admin-nav">
  <div class="nav-logo">
    <div class="nav-brand">Digital<em>Mall</em></div>
    <div class="nav-badge">Admin Panel</div>
  </div>
  <div class="nav-links">
    <a href="?page=home"  <?php if ($page=="home")  echo 'class="active"'; ?>>&#9741; &nbsp; Home</a>
    <a href="?page=users" <?php if ($page=="users") echo 'class="active"'; ?>>&#128100; &nbsp; Manage Users</a>
    <a href="?page=shops" <?php if ($page=="shops") echo 'class="active"'; ?>>&#127978; &nbsp; Manage Shops</a>
  </div>
  <div class="nav-bottom">
    <a href="logout.php">&#x2BA8; &nbsp; Logout</a>
  </div>
</nav>

<main class="admin-main">

<?php if ($page == "home") { ?>

  <div class="page-header">
    <h1>Subscription Analytics</h1>
    <p>Overview of users, shops, and platform revenue</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card"><div class="label">Total Revenue</div><div class="value">$<?php echo $total_revenue; ?></div><div class="sub">Sum of all shop sales</div></div>
    <div class="stat-card"><div class="label">Active Shops</div><div class="value"><?php echo $active_shops; ?></div><div class="sub">Currently subscribed</div></div>
    <div class="stat-card"><div class="label">Total Users</div><div class="value"><?php echo $total_users; ?></div><div class="sub">Across all roles</div></div>
    <div class="stat-card"><div class="label">Total Shops</div><div class="value"><?php echo $total_shops; ?></div><div class="sub">All registered shops</div></div>
  </div>

  <div class="section-label">Users Analytics</div>
  <div class="charts-row">
    <div class="chart-card"><h3>Total Sales per Shop</h3><canvas id="salesChart" height="110"></canvas></div>
    <div class="chart-card"><h3>Users by Role</h3><canvas id="rolesChart" height="160"></canvas></div>
  </div>

  <div class="section-label">Shops Analytics</div>
  <div class="charts-row">
    <div class="chart-card"><h3>Shop Status Breakdown</h3><canvas id="statusChart" height="110"></canvas></div>
    <div class="chart-card"><h3>Active vs Inactive Shops</h3><canvas id="activeChart" height="160"></canvas></div>
  </div>

  <script>
    new Chart(document.getElementById('salesChart').getContext('2d'), {
      type:'bar', data:{ labels:[<?php echo $shop_names; ?>], datasets:[{ label:'Total Sales ($)', data:[<?php echo $shop_sales; ?>], backgroundColor:'rgba(255,94,26,0.2)', borderColor:'#ff5e1a', borderWidth:2, borderRadius:6 }] },
      options:{ plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'}}, y:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'}} } }
    });
    new Chart(document.getElementById('rolesChart').getContext('2d'), {
      type:'doughnut', data:{ labels:['Customer','Manager','Admin'], datasets:[{ data:[<?php echo "$count_customer,$count_manager,$count_admin"; ?>], backgroundColor:['rgba(59,130,246,0.2)','rgba(168,85,247,0.2)','rgba(255,94,26,0.2)'], borderColor:['#60a5fa','#c084fc','#ff5e1a'], borderWidth:2 }] },
      options:{ cutout:'70%', plugins:{legend:{position:'bottom',labels:{color:'#7a7a9a',padding:14,font:{size:12}}}} }
    });
    new Chart(document.getElementById('statusChart').getContext('2d'), {
      type:'bar', data:{ labels:['Active','Maintenance','Disabled','Closed'], datasets:[{ label:'Number of Shops', data:[<?php echo "$count_active,$count_maintenance,$count_disabled,$count_closed"; ?>], backgroundColor:['rgba(34,197,94,0.2)','rgba(245,158,11,0.2)','rgba(239,68,68,0.2)','rgba(107,114,128,0.2)'], borderColor:['#22c55e','#f59e0b','#ef4444','#6b7280'], borderWidth:2, borderRadius:6 }] },
      options:{ plugins:{legend:{display:false}}, scales:{ x:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'}}, y:{grid:{color:'#2a2a38'},ticks:{color:'#7a7a9a'},beginAtZero:true} } }
    });
    new Chart(document.getElementById('activeChart').getContext('2d'), {
      type:'doughnut', data:{ labels:['Active','Inactive'], datasets:[{ data:[<?php echo "$count_active,".($count_maintenance+$count_disabled+$count_closed); ?>], backgroundColor:['rgba(34,197,94,0.2)','rgba(239,68,68,0.2)'], borderColor:['#22c55e','#ef4444'], borderWidth:2 }] },
      options:{ cutout:'70%', plugins:{legend:{position:'bottom',labels:{color:'#7a7a9a',padding:14,font:{size:12}}}} }
    });
  </script>

<?php } else if ($page == "users") { ?>

  <div class="page-header">
    <h1>Manage Users</h1>
    <p>View, update, or remove user accounts</p>
  </div>

  <?php if (isset($_GET['msg'])) {
    $msgs = ['added'=>'Manager added successfully.','updated'=>'User updated.','deleted'=>'User deleted.'];
    echo '<div class="flash-msg">'.$msgs[$_GET['msg']].'</div>';
  } ?>
  <?php if (isset($err) && $err != "") { echo '<div class="flash-msg flash-err">'.$err.'</div>'; } ?>

  <div class="table-card">
    <div class="table-top">
      <h2>All Users</h2>
      <button class="btn-add" onclick="openModal('addMgrModal')">+ Add Manager</button>
    </div>
    <table>
      <thead>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php while ($user = mysqli_fetch_assoc($users_result)) { ?>
        <tr>
          <td class="muted">#<?php echo $user["id"]; ?></td>
          <td><?php echo htmlspecialchars($user["name"]); ?></td>
          <td class="muted"><?php echo htmlspecialchars($user["email"]); ?></td>
          <td>
            <?php
            $badge = ['Customer'=>'role-customer','Manager'=>'role-manager','Admin'=>'role-admin'];
            $cls   = $badge[$user["role"]] ?? 'role-customer';
            echo '<span class="role-badge '.$cls.'">'.$user["role"].'</span>';
            ?>
          </td>
          <td>
            <div class="action-btns">
              <button class="btn-edit" onclick="openEditUser(<?php echo $user['id']; ?>,'<?php echo addslashes($user['name']); ?>','<?php echo $user['role']; ?>')">Update</button>
              <a href="?page=users&delete_user=<?php echo $user['id']; ?>" onclick="return confirm('Delete this user?')" class="btn-del" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">Delete</a>
            </div>
          </td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

  <!-- Add Manager Modal -->
  <div class="modal-overlay" id="addMgrModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('addMgrModal')">&#x2715;</button>
      <h3>Add Manager</h3>
      <form method="POST" action="?page=users">
        <div class="form-group"><label>Full Name</label><input type="text" name="mgr_name" placeholder="Jane Doe"></div>
        <div class="form-group"><label>Email</label><input type="text" name="mgr_email" placeholder="manager@example.com"></div>
        <div class="form-group"><label>Password</label><input type="password" name="mgr_pass" placeholder="Min. 6 characters"></div>
        <div class="form-group">
          <label>Assign to Shop</label>
          <select name="mgr_shop">
            <option value="">-- Select shop --</option>
            <?php while ($fs = mysqli_fetch_assoc($free_shops)) { ?>
              <option value="<?php echo $fs['id']; ?>"><?php echo htmlspecialchars($fs['name']); ?></option>
            <?php } ?>
          </select>
        </div>
        <button type="submit" name="add_manager" class="btn-submit" style="margin-top:8px;">Create Manager</button>
      </form>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div class="modal-overlay" id="editUserModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('editUserModal')">&#x2715;</button>
      <h3>Update User</h3>
      <form method="POST" action="?page=users">
        <input type="hidden" name="upd_user_id" id="upd_user_id">
        <div class="form-group"><label>Full Name</label><input type="text" name="upd_name" id="upd_user_name"></div>
        <div class="form-group">
          <label>Role</label>
          <select name="upd_role" id="upd_user_role">
            <option value="Customer">Customer</option>
            <option value="Manager">Manager</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
        <button type="submit" name="update_user" class="btn-submit" style="margin-top:8px;">Save Changes</button>
      </form>
    </div>
  </div>

<?php } else if ($page == "shops") { ?>

  <div class="page-header">
    <h1>Manage Shops</h1>
    <p>Monitor subscriptions, sales, and shop statuses</p>
  </div>

  <?php if (isset($_GET['msg'])) {
    $msgs = ['updated'=>'Shop updated.','deleted'=>'Shop deleted.'];
    echo '<div class="flash-msg">'.$msgs[$_GET['msg']].'</div>';
  } ?>

  <div class="table-card">
    <div class="table-top"><h2>All Shops</h2></div>
    <table>
      <thead>
        <tr><th>ID</th><th>Shop Name</th><th>Total Sales</th><th>Sub Start</th><th>Sub End</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php while ($shop = mysqli_fetch_assoc($shops_result)) { ?>
        <tr>
          <td class="muted">#<?php echo $shop["id"]; ?></td>
          <td><?php echo htmlspecialchars($shop["name"]); ?></td>
          <td>$<?php echo $shop["total_sales"]; ?></td>
          <td class="muted"><?php echo $shop["sub_start"]; ?></td>
          <td class="muted"><?php echo $shop["sub_end"]; ?></td>
          <td>
            <?php
            $sc = ['active'=>'status-active','maintenance'=>'status-maintenance','disabled'=>'status-disabled','closed'=>'status-closed'];
            $cls = $sc[$shop["status"]] ?? 'status-closed';
            echo '<span class="status-badge '.$cls.'">'.ucfirst($shop["status"]).'</span>';
            ?>
          </td>
          <td>
            <div class="action-btns">
              <button class="btn-edit" onclick="openEditShop(<?php echo $shop['id']; ?>,'<?php echo $shop['status']; ?>')">Update</button>
              <a href="?page=shops&delete_shop=<?php echo $shop['id']; ?>" onclick="return confirm('Delete this shop?')" class="btn-del" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">Delete</a>
            </div>
          </td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

  <!-- Edit Shop Modal -->
  <div class="modal-overlay" id="editShopModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('editShopModal')">&#x2715;</button>
      <h3>Update Shop Status</h3>
      <form method="POST" action="?page=shops">
        <input type="hidden" name="sh_id" id="sh_id">
        <div class="form-group">
          <label>Status</label>
          <select name="sh_status" id="sh_status">
            <option value="active">Active</option>
            <option value="maintenance">Maintenance</option>
            <option value="disabled">Disabled</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <button type="submit" name="update_shop" class="btn-submit" style="margin-top:8px;">Save Changes</button>
      </form>
    </div>
  </div>

<?php } ?>

</main>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openEditUser(id, name, role) {
    document.getElementById('upd_user_id').value   = id;
    document.getElementById('upd_user_name').value = name;
    document.getElementById('upd_user_role').value = role;
    openModal('editUserModal');
}
function openEditShop(id, status) {
    document.getElementById('sh_id').value     = id;
    document.getElementById('sh_status').value = status;
    openModal('editShopModal');
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});
</script>

</body>
</html>