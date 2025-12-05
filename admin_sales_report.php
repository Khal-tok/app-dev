<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$result_pos_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_pos_sales FROM transactions");
$total_pos_sales = mysqli_fetch_assoc($result_pos_sales)['total_pos_sales'];

$result_online_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_online_sales FROM online_orders WHERE status != 'Canceled'");
$total_online_sales = mysqli_fetch_assoc($result_online_sales)['total_online_sales'];

$grand_total_revenue = $total_pos_sales + $total_online_sales;

$sql_baristas = "SELECT id, username FROM users WHERE role = 'barista' ORDER BY username ASC";
$result_baristas = mysqli_query($conn, $sql_baristas);

$selected_barista_id = isset($_GET['barista_id']) ? (int)$_GET['barista_id'] : null;
$selected_barista_username = null;
$barista_pos_sales = 0;
$barista_online_prepared = 0;
$result_barista_transactions = null;
$result_barista_petty_cash = null;
$stmt_transactions = null;
$stmt_petty_cash = null;


if ($selected_barista_id) {
    $stmt_user = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ? AND role = 'barista'");
    mysqli_stmt_bind_param($stmt_user, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user = mysqli_fetch_assoc($result_user)) {
        $selected_barista_username = $user['username'];
    }
    mysqli_stmt_close($stmt_user);

    $stmt_sales = mysqli_prepare($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_sales FROM transactions WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_sales, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_sales);
    $result_sales = mysqli_stmt_get_result($stmt_sales);
    $barista_pos_sales_row = mysqli_fetch_assoc($result_sales);
    $barista_pos_sales = $barista_pos_sales_row['total_sales'];
    mysqli_stmt_close($stmt_sales);

    $stmt_online = mysqli_prepare($conn, "SELECT COUNT(id) AS total_prepared FROM online_orders WHERE prepared_by = ?");
    mysqli_stmt_bind_param($stmt_online, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_online);
    $result_online = mysqli_stmt_get_result($stmt_online);
    $barista_online_row = mysqli_fetch_assoc($result_online);
    $barista_online_prepared = $barista_online_row['total_prepared'];
    mysqli_stmt_close($stmt_online);

    $stmt_transactions = mysqli_prepare($conn, "SELECT id, total_amount, transaction_date FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC");
    mysqli_stmt_bind_param($stmt_transactions, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_transactions);
    $result_barista_transactions = mysqli_stmt_get_result($stmt_transactions);
    
    $stmt_petty_cash = mysqli_prepare($conn, "SELECT id, amount, description, entry_date, type FROM petty_cash WHERE user_id = ? ORDER BY entry_date DESC");
    mysqli_stmt_bind_param($stmt_petty_cash, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_petty_cash);
    $result_barista_petty_cash = mysqli_stmt_get_result($stmt_petty_cash);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Sales Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .metrics-card-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .metric-card {
            flex: 1;
            padding: 15px;
            margin: 0 10px;
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        .metric-card h4 {
            margin-top: 0;
            color: #333;
        }
        .metric-card p {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }
        .grand-total-card {
            background-color: #d4edda;
            border-color: #155724;
        }
        .grand-total-card p {
            color: #155724;
        }
        .barista-list {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }
        .barista-list li {
            display: inline-block;
            margin-right: 15px;
        }
        .barista-list a {
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            background-color: #007bff;
            color: white;
            transition: background-color 0.2s;
        }
        .barista-list a:hover {
            background-color: #0056b3;
        }
        .barista-list a.active {
            background-color: #28a745; 
            font-weight: bold;
        }
        .report-section {
            margin-top: 20px;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        .clickable-row:hover {
            background-color: #f1f1f1;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Sales and Financial Reporting</h2>
    <p><a href="admin_dashboard.php">Back to Admin Dashboard</a> | <a href="logout.php">Logout</a></p>

    <h3>Overall Revenue Metrics (All Time)</h3>
    <div class="metrics-card-container">
        
        <div class="metric-card">
            <h4>Total POS Sales</h4>
            <p>₱ <?php echo number_format($total_pos_sales, 2); ?></p>
        </div>

        <div class="metric-card">
            <h4>Total Online Sales</h4>
            <p>₱ <?php echo number_format($total_online_sales, 2); ?></p>
        </div>

        <div class="metric-card grand-total-card">
            <h4>GRAND TOTAL REVENUE</h4>
            <p>₱ <?php echo number_format($grand_total_revenue, 2); ?></p>
        </div>
    </div>
    
    <hr>

    <h3>Barista Performance Breakdown (POS & Petty Cash)</h3>
    
    <h4>Select Barista:</h4>
    <ul class="barista-list">
        <?php 
        mysqli_data_seek($result_baristas, 0);
        while($barista = mysqli_fetch_assoc($result_baristas)): 
        ?>
        <li>
            <a href="?barista_id=<?php echo $barista['id']; ?>" 
               class="<?php echo ($barista['id'] == $selected_barista_id) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($barista['username']); ?>
            </a>
        </li>
        <?php endwhile; ?>
    </ul>

    <?php if ($selected_barista_id && $selected_barista_username): ?>
    
        <div class="report-section">
            <h3 style="color: #007bff;">Report for: <?php echo htmlspecialchars($selected_barista_username); ?></h3>
            
            <div class="metrics-card-container" style="justify-content: flex-start; margin-left: -10px;">
                <div class="metric-card" style="flex: 0 0 300px; background-color: #e6f7ff; border-color: #91d5ff;">
                    <h4>Total POS Sales by <?php echo htmlspecialchars($selected_barista_username); ?></h4>
                    <p style="color: #007bff;">
                        ₱ <?php echo number_format($barista_pos_sales, 2); ?>
                    </p>
                </div>
                
                <div class="metric-card" style="flex: 0 0 300px; background-color: #e6f7ff; border-color: #91d5ff;">
                    <h4>Online Orders Prepared by <?php echo htmlspecialchars($selected_barista_username); ?></h4>
                    <p style="color: #007bff;">
                        <?php echo number_format($barista_online_prepared); ?> Orders
                    </p>
                </div>
            </div>

            <h4>POS Transaction History</h4>
            <table border="1" width="100%">
                <tr>
                    <th>Transaction ID</th>
                    <th>Date/Time</th>
                    <th>Amount</th>
                </tr>
                <?php if ($result_barista_transactions && mysqli_num_rows($result_barista_transactions) > 0): ?>
                    <?php while($transaction = mysqli_fetch_assoc($result_barista_transactions)): ?>
                    <tr>
                        <td>
                            <a href="receipt_module_admin.php?type=pos&id=<?php echo $transaction['id']; ?>" target="_blank">
                                <?php echo $transaction['id']; ?>
                            </a>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['transaction_date'])); ?></td>
                        <td>₱ <?php echo number_format($transaction['total_amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No POS transactions found for this barista.</td></tr>
                <?php endif; ?>
            </table>
            
            <br>

            <h4>Petty Cash Entries</h4>
            <table border="1" width="100%">
                <tr>
                    <th>Entry ID</th>
                    <th>Date/Time</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
                <?php if ($result_barista_petty_cash && mysqli_num_rows($result_barista_petty_cash) > 0): ?>
                    <?php while($petty_cash = mysqli_fetch_assoc($result_barista_petty_cash)): ?>
                    <?php 
                        $js_description = htmlspecialchars($petty_cash['description'], ENT_QUOTES);
                        $js_type = htmlspecialchars(ucfirst($petty_cash['type']), ENT_QUOTES);
                        $js_amount = "₱ " . number_format($petty_cash['amount'], 2);
                    ?>
                    <tr class="clickable-row" onclick="alert('Petty Cash Entry <?php echo $petty_cash['id']; ?>:\n\nDescription: <?php echo $js_description; ?>\nType: <?php echo $js_type; ?>\nAmount: <?php echo $js_amount; ?>');">
                        <td><?php echo $petty_cash['id']; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($petty_cash['entry_date'])); ?></td>
                        <td><?php echo htmlspecialchars($petty_cash['description']); ?></td>
                        <td style="color: <?php echo ($petty_cash['type'] == 'in') ? 'green' : 'red'; ?>;">
                            <?php echo htmlspecialchars(ucfirst($petty_cash['type'])); ?>
                        </td>
                        <td>₱ <?php echo number_format($petty_cash['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">No petty cash entries found for this barista.</td></tr>
                <?php endif; ?>
            </table>
            
        </div>
    <?php elseif ($result_baristas && mysqli_num_rows($result_baristas) > 0): ?>
        <p>Please select a barista from the list above to view their performance report.</p>
    <?php else: ?>
        <p>No baristas found in the system.</p>
    <?php endif; ?>

</div>
</body>
</html>