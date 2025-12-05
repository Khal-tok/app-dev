<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $message = "success_product_deleted";
        } else {
            error_log("Error deleting product ID $id: " . mysqli_stmt_error($stmt));
            $message = "error=db_delete_fail";
        }
        
        mysqli_stmt_close($stmt);
        header("location: admin_dashboard.php?$message");
        exit;
    } else {
        error_log("Database error: Could not prepare delete statement for products.");
        header("location: admin_dashboard.php?error=db_prepare_fail");
        exit;
    }
} else {
    header("location: admin_dashboard.php");
    exit;
}
?>