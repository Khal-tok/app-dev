<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}


$username = $_SESSION['username'];
$user_id_sql = "SELECT id, username, password, contact_number FROM users WHERE username='$username'";
$user_id_result = mysqli_query($conn, $user_id_sql);
$user_data = mysqli_fetch_assoc($user_id_result);
$user_id = $user_data['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];
    $new_contact_number = $_POST['contact_number'];

    
    $check_sql = "SELECT id FROM users WHERE username='$new_username' AND id != $user_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $error = "Error: Username already taken.";
    } else {

        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username='$new_username', password='$hashed_password', contact_number='$new_contact_number' WHERE id=$user_id";
        } else {
            $sql = "UPDATE users SET username='$new_username', contact_number='$new_contact_number' WHERE id=$user_id";
        }
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['username'] = $new_username;
            $user_data['username'] = $new_username; 
            $user_data['contact_number'] = $new_contact_number;
            $success = "Account updated successfully!";
        } else {
            $error = "Error updating record: " . mysqli_error($conn);
        }
    }
}


if (!isset($success) && !isset($error)) {
    $sql = "SELECT username, contact_number FROM users WHERE id=$user_id";
    $result = mysqli_query($conn, $sql);
    $user_data = mysqli_fetch_assoc($result);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Update Account</h2>
    <p>You can update your username, password, and contact number here.</p>
    
    <?php if (isset($success)): ?>
        <p style='color:green;'><?php echo $success; ?></p>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <p style='color:red;'><?php echo $error; ?></p>
    <?php endif; ?>

    <form action="" method="post">
        Username: <input type="text" name="username" value="<?php echo $user_data['username']; ?>" required><br><br>
        Contact Number: <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>" required><br><br>
        Password: <input type="password" name="password" placeholder="Leave blank to keep current password"><br><br>
        <input type="submit" value="Update Account">
    </form>
    <p><a href="customer_dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>