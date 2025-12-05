<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'];
    $price = $_POST['price'];
    $category = $_POST['category'];

    $sql = "UPDATE products SET name = ?, price = ?, category = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdsi", $name, $price, $category, $id);

    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

$sql = "SELECT * FROM products WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    die("Product not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Edit Product</h2>
    <form action="" method="post">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
        Product Name: <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required><br><br>
        Price (in PHP): <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($row['price']); ?>" required><br><br>
        Category:
        <select name="category">
            <option value="drink" <?php echo ($row['category'] == 'drink') ? 'selected' : ''; ?>>Drink</option>
            <option value="snack" <?php echo ($row['category'] == 'snack') ? 'selected' : ''; ?>>Snack</option>
        </select><br><br>
        <input type="submit" value="Update Product">
    </form>
    <br><a href="admin_dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>