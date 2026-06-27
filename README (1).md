# Digital Mall

A multi-role online mall management system built with vanilla PHP and MySQL — customers browse and order from multiple shops, shop owners manage their own storefront, and an admin oversees the whole platform.

Live demo: digitalmall.freedev.app

### Demo Credentials

| Role     | Email                | Password   |
|----------|-----------------------|------------|
| Admin    | nehemiah@gmail.com    | imadmin    |
| Manager  | ermias@gmail.com      | ermias123  |
| Customer | hana@gmail.com        | hana123    |

---

## Features

### Customer
- Browse all active shops and their products
- Add items to cart, adjust quantities, place orders
- Stock-aware checkout — can't order more than what's available
- Order tracking with status updates (pending, accepted, being prepared, shipped, delivered)
- Cancel pending/accepted orders (automatically restocks the item)
- Manage delivery address

### Manager (Shop Owner)
- Sign up and instantly create your own shop (name, type, location, image)
- Dashboard with revenue, product count, pending orders, and delivery stats
- Visual analytics with Chart.js (order status breakdown)
- Full product CRUD — add, update, delete, with optional category and image
- Create custom product categories
- Process incoming orders and update their status
- Track delivered orders separately

### Admin
- Manage all users (customers, managers)
- Manage all shops platform-wide
- Add new managers manually

---

## Tech Stack

- Backend: PHP (procedural, no framework)
- Database: MySQL (mysqli)
- Frontend: HTML, CSS, vanilla JavaScript
- Charts: Chart.js
- Hosting: InfinityFree (free PHP/MySQL hosting)

---

## Security

This project went through a full security review and hardening pass:

- Passwords — hashed with password_hash() and verified with password_verify() (no plaintext storage)
- SQL injection — all user input is escaped (mysqli_real_escape_string) or cast to expected types ((int), (float)) before hitting a query
- XSS — all user-supplied output is passed through htmlspecialchars() before being rendered
- Credentials — database connection details live in a single config.php, excluded from version control via .gitignore. A config.example.php template is committed instead

### Known limitation (by design, documented rather than fixed)

A few destructive actions (deleting a product, shop, or user) are triggered via GET requests rather than POST plus a CSRF token. For a public-facing production app this would need to change, since a crafted link or embedded request could trigger one of these actions while an admin/manager is logged in elsewhere. Flagging this here as an intentional scope decision for this project rather than an oversight.

---

## Local Setup

1. Clone the repo:
   git clone https://github.com/YOUR_USERNAME/digital-mall.git

2. Place the folder inside your local server's web root (e.g. C:\xampp\htdocs\)

3. Create a MySQL database and import the project's .sql file (export your local schema if you don't have one bundled — see note below)

4. Copy config.example.php to a new file named config.php and fill in your local database credentials:
   <?php
   $conn = mysqli_connect("localhost", "root", "", "digital_mall");

5. Start Apache and MySQL (e.g. via XAMPP) and visit localhost/PROJ1/login.php

Note: config.php is intentionally excluded from this repo for security. You must create your own using config.example.php as a template — the app will not connect to a database without it.

---

## Project Structure

login.php              - Login and session start
signup.php             - Customer / Manager signup (creates a shop on manager signup)
logout.php
customer.php           - Customer dashboard: browse, cart, orders, account
manager_dashboard.php  - Manager dashboard: products, categories, orders, deliveries
admin_dashboard.php    - Admin dashboard: users, shops
config.example.php     - Template for database credentials (copy to config.php)
style.css

---

## Future Improvements

- Convert GET-based delete actions to POST with CSRF tokens
- Image upload support (currently uses external image URLs)
- Order cancellation by managers, not just customers
- Pagination for large product/order lists

---

## Author

Built by Nehemiah as part of an Information Technology degree project, with a focus on real-world security practices alongside core CRUD functionality.
