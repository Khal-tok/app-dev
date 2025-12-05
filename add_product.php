<?php
session_start();
include 'db.php';

// Check if a directory for product images exists, if not, create it
$target_dir = "product_images/";
if (!is_dir($target_dir)) {
    // Attempt to create the directory with necessary permissions
    mkdir($target_dir, 0755, true); 
}

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $price = $_POST['price'];
    $category = $_POST['category'];
    $image_path = 'placeholder.png'; // Default placeholder image 
    $uploadOk = 1;
    $error_message = "";

    // --- Image Upload Handling ---
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_name = basename($_FILES["product_image"]["name"]);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Create a unique filename
        $unique_file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $unique_file_name;
        
        // 1. Check file size (5MB limit)
        if ($_FILES["product_image"]["size"] > 5000000) {
            $error_message = "Sorry, your file is too large (max 5MB).";
            $uploadOk = 0;
        }

        // 2. Allow specific file formats
        if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif" ) {
            $error_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        // 3. Move file if checks pass
        if ($uploadOk == 1) {
            // **CRITICAL STEP:** This is the only place move_uploaded_file should be called.
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                $image_path = $unique_file_name; // Save the unique filename
            } else {
                $error_message = "Sorry, there was an error uploading your file.";
                $uploadOk = 0; 
            }
        }
    }
    
    // --- Database Insertion ---
    if ($uploadOk == 1) { 
        // SQL now includes the image_path column
        $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, category, image_path) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sdss", $name, $price, $category, $image_path); 
        
        if (mysqli_stmt_execute($stmt)) {
            // **CRITICAL FIX:** Redirect immediately to prevent re-submission on refresh.
            header("location: admin_dashboard.php?success_product_added=" . urlencode($name));
            exit; // Stop all further script execution immediately
        } else {
            $error_message = "Error inserting product into database: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Product</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Add New Product</h2>
    <?php if (!empty($error_message)): ?>
        <p class="message-error"><?php echo $error_message; ?></p>
    <?php endif; ?>
    <form action="" method="post" enctype="multipart/form-data">
        Product Name: <input type="text" name="name" required><br><br>
        Price: <input type="number" step="0.01" name="price" required><br><br>
        
        Category:
        <select name="category" required>
            <option value="drink">Drink</option>
            <option value="snack">Snack</option>
        </select><br><br>

        Product Image:
        <input type="file" name="product_image" accept=".jpg, .jpeg, .png, .gif"><br><br>
        
        <input type="submit" value="Add Product">
    </form>
    <br><a href="admin_dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>