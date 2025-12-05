<?php
session_start();
include 'db.php';
include 'utility_functions.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = null;
$items = [];
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id_to_update = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];
    
    $valid_barista_statuses = ['Prepared', 'Ready for Pickup', 'Canceled'];
    if (!in_array($new_status, $valid_barista_statuses)) {
        $error = "Invalid status selected.";
    } elseif ($order_id_to_update <= 0) {
        $error = "Invalid Order ID.";
    } else {
        $sql_update = "UPDATE online_orders SET status = ? WHERE id = ? AND status != 'Delivered' AND status != 'Canceled'";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "si", $new_status, $order_id_to_update);

        if (mysqli_stmt_execute($stmt_update)) {
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $success = "Order #" . $order_id_to_update . " status updated to " . htmlspecialchars($new_status) . " successfully!";
                $order_id = $order_id_to_update;
            } else {
                $error = "Order #" . $order_id_to_update . " could not be updated.";
            }
        } else {
            $error = "Error updating status: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_update);
    }
}


if ($order_id > 0) {
    // 1. Fetch Order details
    $sql_order = "SELECT o.*, u.username, b.username AS prepared_by_user 
                  FROM online_orders o 
                  JOIN users u ON o.user_id = u.id 
                  LEFT JOIN users b ON o.prepared_by_user_id = b.id 
                  WHERE o.id = ?";
    $stmt_order = mysqli_prepare($conn, $sql_order);
    mysqli_stmt_bind_param($stmt_order, "i", $order_id);
    mysqli_stmt_execute($stmt_order);
    $result_order = mysqli_stmt_get_result($stmt_order);
    $order = mysqli_fetch_assoc($result_order);
    mysqli_stmt_close($stmt_order);

    // 2. Fetch Order Items, including customization JSON
    if ($order) {
        $sql_items = "SELECT ooi.quantity, ooi.price, p.name, ooi.customizations_json
                      FROM online_order_items ooi 
                      JOIN products p ON ooi.product_id = p.id 
                      WHERE ooi.order_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        mysqli_stmt_execute($stmt_items);
        $result_items = mysqli_stmt_get_result($stmt_items);
        while($item = mysqli_fetch_assoc($result_items)) {
            $items[] = $item;
        }
        mysqli_stmt_close($stmt_items);
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista Scan Order</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .order-summary { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .item-list th { background-color: #f0f0ff; }
        .prep-item { font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
<div class="container">
    <h2>QR Order Check-in (Barista)</h2>
    <p><a href="barista_dashboard.php">Back to POS</a> | <a href="logout.php">Logout</a></p>

    <?php if ($order_id == 0): ?>
        <p>Awaiting QR Scan or Order ID in URL.</p>
    <?php elseif (!$order): ?>
        <p style='color:red;'>Order ID #<?php echo htmlspecialchars($order_id); ?> not found.</p>
    <?php else: ?>
        <?php if (!empty($error)): ?><p style='color:red;'><?php echo $error; ?></p><?php endif; ?>
        <?php if (!empty($success)): ?><p style='color:green;'><?php echo $success; ?></p><?php endif; ?>

        <div class="order-summary">
            <h3>Order #<?php echo htmlspecialchars($order['id']); ?> Status: **<?php echo htmlspecialchars($order['status']); ?>**</h3>
            <p>Customer: <?php echo htmlspecialchars($order['username']); ?><br>
               Total: ₱ <?php echo number_format($order['total_amount'], 2); ?></p>
        </div>

        <h4>Items & Preparation Guide (Ingredient Check)</h4>
        <table border="1" class="item-list">
            <tr>
                <th>Qty</th>
                <th>Product</th>
                <th>Preparation Details</th>
            </tr>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="prep-item"><?php echo $item['quantity']; ?>x</td>
                <td class="prep-item"><?php echo htmlspecialchars($item['name']); ?></td>
                <td>
                    <?php 
                    $customizations = json_decode($item['customizations_json'], true) ?? [];
                    echo display_customizations($customizations, $EXTRA_SHOT_PRICE); 
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <hr>

        <?php if ($order['status'] != 'Delivered' && $order['status'] != 'Canceled'): ?>
        <h4>Update Status</h4>
        <form action="" method="post">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            <label for="new_status">Action:</label>
            <select name="new_status" id="new_status" required>
                <option value="Prepared">Mark as Prepared</option>
                <option value="Ready for Pickup">Ready for Pickup</option>
                <option value="Out for Delivery">Out for Delivery</option>
                <option value="Canceled">Cancel Order</option>
            </select>
            <input type="submit" name="update_status" value="Confirm Update">
        </form>
        <?php else: ?>
        <p style='color:blue;'>**Status is <?php echo htmlspecialchars($order['status']); ?>. No further action required.**</p>
        <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>