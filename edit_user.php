<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $username = $_POST['username'];
    $password = $_POST['password'];
    $contact_number = $_POST['contact_number']; // ✅ NEW: Fetch contact_number

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // ✅ UPDATE: Added contact_number to the UPDATE query (with password)
        $sql = "UPDATE users SET username = ?, password = ?, contact_number = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $username, $hashed_password, $contact_number, $id);
    } else {
        // ✅ UPDATE: Added contact_number to the UPDATE query (without password)
        $sql = "UPDATE users SET username = ?, contact_number = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $username, $contact_number, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// 🧲 UPDATE: Fetched contact_number from the database
$sql = "SELECT id, username, role, contact_number FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Edit User</h2>
    <form action="" method="post">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
        Username: <input type="text" name="username" value="<?php echo htmlspecialchars($row['username']); ?>" required><br><br>
        
        Contact Number: <input type="text" name="contact_number" value="<?php echo htmlspecialchars($row['contact_number'] ?? ''); ?>"><br><br>

        Password (Leave blank to keep current): <input type="password" name="password"><br><br>
        
        <p>Role: <?php echo htmlspecialchars(ucfirst($row['role'])); ?> (Not editable here)</p>
        <input type="submit" value="Save Changes">
    </form>
    <p><a href="admin_dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>