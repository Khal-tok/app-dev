<?php
session_start();
include 'db.php'; // Include your database connection

// === 1. Authorization Check ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// === 2. Fetch Metrics ===
$result_products_count = mysqli_query($conn, "SELECT COUNT(id) AS total_products FROM products");
$products_count = mysqli_fetch_assoc($result_products_count)['total_products'];

$result_users_count = mysqli_query($conn, "SELECT COUNT(id) AS total_users FROM users");
$users_count = mysqli_fetch_assoc($result_users_count)['total_users'];

$result_pos_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_pos_sales FROM transactions");
$pos_sales = mysqli_fetch_assoc($result_pos_sales)['total_pos_sales'];

$result_online_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_online_sales FROM online_orders WHERE status != 'Canceled'");
$online_sales = mysqli_fetch_assoc($result_online_sales)['total_online_sales'];


// === 3. Handle Product Search and Fetch Products ===
$search_term = '';
$stmt_products = null;
$result_products = null;

if (isset($_GET['product_search']) && !empty(trim($_GET['product_search']))) {
    $search_term = trim($_GET['product_search']);
    
    $sql_products = "SELECT * FROM products WHERE name LIKE ? OR id = ? ORDER BY id DESC";
    $stmt_products = mysqli_prepare($conn, $sql_products); 

    $like_term = '%' . $search_term . '%';
    mysqli_stmt_bind_param($stmt_products, "si", $like_term, $search_term); 
    
    mysqli_stmt_execute($stmt_products);
    $result_products = mysqli_stmt_get_result($stmt_products);
} else {

    $sql_products = "SELECT * FROM products ORDER BY id DESC";
    $result_products = mysqli_query($conn, $sql_products);
}

// === 4. Fetch Users ===
$sql_users = "SELECT id, username, role FROM users ORDER BY id DESC";
$result_users = mysqli_query($conn, $sql_users);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (Clean Blue & White)
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6; /* Very light gray background */
            color: #333; /* Dark text for high contrast */
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1300px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05); /* Soft, professional shadow */
        }

        h2 {
            color: #173f5f; /* Dark Blue Header */
            padding-bottom: 10px;
            border-bottom: 3px solid #e0e0e0;
            margin-bottom: 25px;
        }

        h3 {
            color: #20639b; /* Medium Blue Sub-header */
            margin-top: 25px;
            margin-bottom: 15px;
        }

        a {
            color: #20639b;
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #173f5f;
            text-decoration: underline;
        }

        hr {
            border: 0;
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }

        /* ==================================
           2. METRICS CARDS
           ================================== */
        .metrics-card-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }

        .metric-card {
            flex: 1;
            padding: 20px;
            background-color: #ffffff; /* White card background */
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-3px);
        }

        .metric-card h4 {
            margin-top: 0;
            color: #555;
            font-weight: 500;
            font-size: 1em;
        }

        .metric-card p {
            font-size: 2em;
            font-weight: 700;
            color: #173f5f; /* Deep Blue for Metric Values */
            margin: 5px 0 0 0;
        }

        /* Specific colors for sales to highlight financial data */
        .metric-card:nth-child(3) p, 
        .metric-card:nth-child(4) p {
            color: #008080; /* Teal for sales */
        }

        /* ==================================
           3. DASHBOARD CONTENT (Tables)
           ================================== */
        .dashboard-content-wrapper {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            margin-top: 20px;
        }

        .dashboard-content-wrapper > div {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
        }

        table th, table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        table th {
            background-color: #e6f0fa; /* Very light blue header background */
            color: #173f5f;
            font-weight: 600;
            text-transform: uppercase;
        }

        table tr:hover {
            background-color: #f9f9f9;
        }

        /* Action Links in tables */
        td a {
            padding: 0;
            margin-right: 5px;
            font-size: 0.9em;
        }
        
        /* Specific action link colors */
        td a[href*="edit"] { color: #20639b; }
        td a[href*="delete"] { color: #cc0000; }

        /* ==================================
           4. UTILITY & MESSAGES
           ================================== */
        .message-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            font-weight: bold;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            font-weight: bold;
        }

        /* Search Form Styling */
        .product-search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .product-search-form input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            flex-grow: 1;
        }

        .product-search-form input[type="submit"] {
            background-color: #20639b;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .product-search-form input[type="submit"]:hover {
            background-color: #173f5f;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! </h2>
    <p><a href="logout.php">Logout</a></p>

    <?php 
    if (isset($_GET['success_product_deleted'])) {
        echo '<p class="message-success">Product deleted successfully!</p>';
    }
    if (isset($_GET['success_product_added'])) {
        echo '<p class="message-success">Product "' . htmlspecialchars($_GET['success_product_added']) . '" added successfully!</p>';
    }
    if (isset($_GET['success_product_updated'])) {
        echo '<p class="message-success">Product "' . htmlspecialchars($_GET['success_product_updated']) . '" updated successfully!</p>';
    }
    if (isset($_GET['success_user_deleted'])) {
        echo '<p class="message-success">User deleted successfully!</p>';
    }
    if (isset($_GET['error'])) {
        echo '<p class="message-error">An error occurred during an operation. ' . htmlspecialchars($_GET['error']) . '</p>';
    }
    ?>

    <h3>Quick Metrics Overview</h3>
    <div class="metrics-card-container">
        <div class="metric-card">
            <h4>Total Products</h4>
            <p><?php echo number_format($products_count); ?></p>
        </div>
        <div class="metric-card">
            <h4>Total Users</h4>
            <p><?php echo number_format($users_count); ?></p>
        </div>
        <div class="metric-card">
            <h4>Total POS Sales</h4>
            <p>₱ <?php echo number_format($pos_sales, 2); ?></p>
        </div>
        <div class="metric-card">
            <h4>Total Online Sales</h4>
            <p>₱ <?php echo number_format($online_sales, 2); ?></p>
        </div>
    </div>
    
    <h3>Sales and Financial Reporting</h3>
    <p>
        <a href="admin_sales_report.php" style="font-weight: bold; color: #008080; text-decoration: none;">
            📈 View Detailed Sales, Barista Performance, and Total Revenue Reports
        </a>
    </p>
    <hr>
    
    <div class="dashboard-content-wrapper"> 
        <div>






            <h3>User Management <a href="add_user.php" style="font-size: 0.7em;">[Add New User]</a></h3>
            <table width="100%">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
                <?php while($user = mysqli_fetch_assoc($result_users)): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>">Edit</a> |
                        <a href="delete_user.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div>




        
            <h3>Product Management <a href="add_product.php" style="font-size: 0.7em;">[Add New Product]</a></h3>
 
            <form action="" method="get" class="product-search-form">
                <input type="text" name="product_search" placeholder="Search by Name or ID" value="<?php echo htmlspecialchars($search_term); ?>">
                <input type="submit" value="Search">
                <?php if (!empty($search_term)): ?>
                    <a href="admin_dashboard.php" style="margin-left: 10px;">Clear Filter</a>
                <?php endif; ?>
            </form>
            
            <table width="100%">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
                <?php while($product = mysqli_fetch_assoc($result_products)): ?>
                <tr>
                    <td><?php echo $product['id']; ?></td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>₱ <?php echo number_format($product['price'], 2); ?></td>
                    <td>
                        <a href="edit_product.php?id=<?php echo $product['id']; ?>">Edit</a> |
                        <a href="delete_product.php?id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                    </td>
                </tr>
                
                <?php endwhile; ?>
            </table>

            <?php 
            if (isset($stmt_products) && $stmt_products) {
                mysqli_stmt_close($stmt_products);
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>