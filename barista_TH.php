<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// 1. Fetch all POS transactions (Secure query - no direct user input)
$sql_transactions = "SELECT t.*, u.username 
                     FROM transactions t 
                     JOIN users u ON t.user_id = u.id 
                     ORDER BY t.transaction_date DESC";
$result_transactions = mysqli_query($conn, $sql_transactions);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista POS Transaction History</title>
    <link rel="stylesheet" href="style.css">
    <script>
    function toggleItems(transactionId) {
        var element = document.getElementById('items-' + transactionId);
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
    <h2>POS Transaction History</h2>
    <p><a href="barista_dashboard.php">Back to POS</a> | <a href="barista_OOH.php">Online Order History</a> | <a href="logout.php">Logout</a></p>

    <h3>All POS Transactions</h3>
    <?php if (mysqli_num_rows($result_transactions) == 0): ?>
        <p>No POS transactions found.</p>
    <?php else: ?>
        <table border="1" width="100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Change</th>
                    <th>Cashier</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while($transaction = mysqli_fetch_assoc($result_transactions)): ?>
                <tr>
                    <td><?php echo $transaction['id']; ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['transaction_date'])); ?></td>
                    <td>₱ <?php echo number_format($transaction['total_amount'], 2); ?></td>
                    <td>₱ <?php echo number_format($transaction['amount_paid'], 2); ?></td>
                    <td>₱ <?php echo number_format($transaction['amount_paid'] - $transaction['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                    <td><button onclick="toggleItems(<?php echo $transaction['id']; ?>)">View Items</button></td>
                </tr>
                <tr id="items-<?php echo $transaction['id']; ?>" style="display:none;">
                    <td colspan="7">
                        <div style="padding: 10px; background: #f0f0f0;">
                            Items Purchased:
                            <table width="90%" style="margin: 5px auto;">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // --- SECURED SELECT FOR TRANSACTION ITEMS ---
                                    $transaction_id = $transaction['id'];
                                    $sql_items = "SELECT oi.quantity, oi.price, p.name 
                                                  FROM order_items oi 
                                                  JOIN products p ON oi.product_id = p.id 
                                                  WHERE oi.transaction_id = ?";
                                    $stmt_items = mysqli_prepare($conn, $sql_items);
                                    mysqli_stmt_bind_param($stmt_items, "i", $transaction_id);
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
                            <p><a href="receipt_module_barista.php?type=pos&id=<?php echo $transaction['id']; ?>" target="_blank">View Printable Receipt</a></p>
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