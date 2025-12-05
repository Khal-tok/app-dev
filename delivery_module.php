<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'delivery') {
    header("location: login.php");
    exit;
}

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_delivered'])) {
    $order_id_to_update = intval($_POST['order_id']);
    $new_status = 'Delivered';

    $sql_update = "UPDATE online_orders SET status = ? WHERE id = ? AND status IN ('Prepared', 'Out for Delivery')";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "si", $new_status, $order_id_to_update);

    if (mysqli_stmt_execute($stmt_update)) {
        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
            $success = "Order #$order_id_to_update marked as Delivered successfully!";
        } else {
            $error = "Order #$order_id_to_update could not be updated (status might not be 'Prepared' or 'Out for Delivery').";
        }
    } else {
        $error = "Error updating status: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_update);
}

$sql_orders = "SELECT o.*, u.username, u.contact_number 
               FROM online_orders o 
               JOIN users u ON o.user_id = u.id 
               WHERE o.status IN ('Prepared', 'Out for Delivery') 
               ORDER BY o.order_date ASC";
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Module</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Delivery Module</h2>
    <p><a href="delivery_history.php">View History</a> | <a href="logout.php">Logout</a></p>

    <?php if ($success): ?><p style='color:green;'><?php echo $success; ?></p><?php endif; ?>
    <?php if ($error): ?><p style='color:red;'><?php echo $error; ?></p><?php endif; ?>

    <h3>Orders Ready for Delivery</h3>
    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>No orders currently prepared for delivery.</p>
    <?php else: ?>
        <table border="1" width="100%">
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Contact</th>
                <th>Total</th>
                <th>Address</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while($order = mysqli_fetch_assoc($result_orders)): ?>
            <tr>
                <td><?php echo $order['id']; ?></td>
                <td><?php echo htmlspecialchars($order['username']); ?></td>
                <td><?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?></td>
                <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($order['delivery_address']); ?></td>
                <td><?php echo htmlspecialchars($order['customer_notes']); ?></td>
                <td><?php echo htmlspecialchars($order['status']); ?></td>
                <td>
                    <?php if ($order['status'] != 'Delivered'): ?>
                        <a href="delivery_scan.php?order_id=<?php echo $order['id']; ?>" class="nav-link" style="padding: 5px 10px; background-color: #007bff;">
                            Scan/Complete
                         </a>
                    <?php else: ?>
                    DELIVERED
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>