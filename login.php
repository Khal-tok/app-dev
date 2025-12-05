<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $error = "";

    // 1. Fetch user data (only need ID, password, and role)
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        // 2. Verify password
        if (password_verify($password, $row['password'])) {
            // 3. Login successful - Set session and redirect immediately (NO OTP)
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] == 'admin') {
                header("location: admin_dashboard.php");
            } else if ($row['role'] == 'barista') {
                header("location: barista_dashboard.php");
            } else if ($row['role'] == 'delivery') {
                header("location: delivery_module.php");  
            } 
            } else {
                // Safety net: Account role is not permitted here
                $error = "Your account role is not permitted here.";
            }
            exit;

        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>POS System Login</h2>
    <?php if (isset($error)): ?>
        <p style='color:red;'><?php echo $error; ?></p>
    <?php endif; ?>
    <form action="" method="post">
        Username: <input type="text" name="username"><br><br>
        Password: <input type="password" name="password"><br><br>
        <input type="submit" value="Login">
    </form>
    <p><a href="customer_login.php">Customer Login</a></p>
</div>
</body>
</html>