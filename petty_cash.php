<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

if (isset($_POST['add_petty_cash'])) {
    $amount = $_POST['amount'];
    $type = $_POST['type'];
    $description = $_POST['description'];

    $user_id_sql = "SELECT id FROM users WHERE username='{$_SESSION['username']}'";
    $user_id_result = mysqli_query($conn, $user_id_sql);
    $user_id_row = mysqli_fetch_assoc($user_id_result);
    $user_id = $user_id_row['id'];

    $sql_petty = "INSERT INTO petty_cash (user_id, type, amount, description) VALUES ($user_id, '$type', $amount, '$description')";
    mysqli_query($conn, $sql_petty);
    header("location: petty_cash.php");
}

$petty_cash_balance = 0;
$sql_petty_in = "SELECT SUM(amount) AS total_in FROM petty_cash WHERE type='in'";
$result_in = mysqli_query($conn, $sql_petty_in);
$row_in = mysqli_fetch_assoc($result_in);
$total_in = $row_in['total_in'] ? $row_in['total_in'] : 0;

$sql_petty_out = "SELECT SUM(amount) AS total_out FROM petty_cash WHERE type='out'";
$result_out = mysqli_query($conn, $sql_petty_out);
$row_out = mysqli_fetch_assoc($result_out);
$total_out = $row_out['total_out'] ? $row_out['total_out'] : 0;

$petty_cash_balance = $total_in - $total_out;

?>

<!DOCTYPE html>
<html>
<head>
    <title>Petty Cash</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Petty Cash Management</h2>
    <p>Welcome, <?php echo $_SESSION['username']; ?>!</p>
    <a href="barista_dashboard.php">Back to Dashboard</a> | <a href="logout.php">Logout</a>
    
    <h3>Current Balance: ₱ <?php echo number_format($petty_cash_balance, 2); ?></h3>

    <form action="" method="post">
        <input type="number" step="0.01" name="amount" placeholder="Amount" required>
        <select name="type">
            <option value="in">Money In</option>
            <option value="out">Money Out</option>
        </select>
        <input type="text" name="description" placeholder="Description" required>
        <input type="submit" name="add_petty_cash" value="Add Entry">
    </form>

    <h4>Recent Entries</h4>
    <table border="1">
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
        <?php
        $sql_entries = "SELECT * FROM petty_cash ORDER BY record_date DESC LIMIT 10";
        $result_entries = mysqli_query($conn, $sql_entries);
        while($row = mysqli_fetch_assoc($result_entries)) {
            echo "<tr>";
            echo "<td>" . $row['record_date'] . "</td>";
            echo "<td>" . $row['type'] . "</td>";
            echo "<td>₱ " . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . $row['description'] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>
</body>
</html>