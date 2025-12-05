<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$sql_orders = "SELECT o.*, u.username, u.contact_number 
               FROM online_orders o 
               JOIN users u ON o.user_id = u.id 
               ORDER BY o.order_date DESC";
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista Online Order History</title>
    <link rel="stylesheet" href="style.css">
    <script>
    function toggleItems(orderId) {
        var element = document.getElementById('items-' + orderId);
        if (element.style.display === "none") {
            element.style.display = "table-row";
        } else {
            element.style.display = "none";
        }
    }
    </script>
</head>
<body>
<div class="container">
    <h2>Online Order History (Barista View)</h2>
    <p><a href="barista_dashboard.php">Back to POS</a> | <a href="logout.php">Logout</a></p>

    <h3>All Online Orders</h3>
    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>No online orders found.</p>
    <?php else: ?>
        <table border="1" width="100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($order = mysqli_fetch_assoc($result_orders)): ?>
                <tr onclick="toggleItems(<?php echo $order['id']; ?>)" style="cursor: pointer;">
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                    <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                    <td><a href="barista_order_details.php?order_id=<?php echo $order['id']; ?>">View/Update</a></td>
                </tr>
                <tr id="items-<?php echo $order['id']; ?>" style="display: none;">
                    <td colspan="6">
                        <div style="padding: 10px; border-left: 3px solid #007bff; margin: 5px;">
                            <strong>Customer Contact:</strong> <?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?><br>
                            <strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?><br>
                            <strong>Notes:</strong> <?php echo htmlspecialchars($order['customer_notes'] ?? 'N/A'); ?><br>
                            <h4>Items:</h4>
                            <table border="1" width="90%" style="margin: 5px auto;">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $order_id = $order['id'];
                                    $sql_items = "SELECT p.name, ooi.quantity, ooi.price 
                                                  FROM online_order_items ooi 
                                                  JOIN products p ON ooi.product_id = p.id 
                                                  WHERE ooi.order_id = ?";
                                    $stmt_items = mysqli_prepare($conn, $sql_items);
                                    mysqli_stmt_bind_param($stmt_items, "i", $order_id);
                                    mysqli_stmt_execute($stmt_items);
                                    $result_items = mysqli_stmt_get_result($stmt_items);
                                    
                                    while($item = mysqli_fetch_assoc($result_items)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                                        echo "<td>" . $item['quantity'] . "</td>";
                                        echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_stmt_close($stmt_items);
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>