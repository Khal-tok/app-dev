<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $new_extra_shot_price = filter_var($_POST['extra_shot_price'], FILTER_VALIDATE_FLOAT);

    if ($new_extra_shot_price !== false && $new_extra_shot_price >= 0) {
        $sql_update = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'EXTRA_SHOT_PRICE'";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "s", $new_extra_shot_price);

        if (mysqli_stmt_execute($stmt_update)) {
            $message = "<p style='color:green;'>Settings updated successfully!</p>";
        } else {
            $message = "<p style='color:red;'>Database error: " . mysqli_error($conn) . "</p>";
        }
        mysqli_stmt_close($stmt_update);
    } else {
        $message = "<p style='color:red;'>Invalid price entered.</p>";
    }
}

$sql_fetch = "SELECT setting_key, setting_value, description FROM system_settings WHERE setting_key = 'EXTRA_SHOT_PRICE'";
$result_fetch = mysqli_query($conn, $sql_fetch);
$settings_data = mysqli_fetch_all($result_fetch, MYSQLI_ASSOC);
$current_shot_price = $settings_data[0]['setting_value'] ?? '20.00';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage System Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>System Settings Configuration</h2>
    <p><a href="admin_dashboard.php">Back to Dashboard</a> | <a href="logout.php">Logout</a></p>

    <?php echo $message; ?>

    <h3>Customize Add-on Prices</h3>
    <form action="" method="post">
        <label for="extra_shot_price">Extra Espresso Shot Price (₱):</label>
        <input type="number" step="0.01" name="extra_shot_price" value="<?php echo htmlspecialchars($current_shot_price); ?>" required><br><br>
        
        <input type="submit" name="update_settings" value="Save Settings">
    </form>
    
    <p style="margin-top: 20px;">*Note: Size modifiers (16oz, 20oz) are controlled within the database directly for now.</p>
</div>
</body>
</html>