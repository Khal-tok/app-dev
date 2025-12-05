<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        if (!empty($name) && $price > 0 && $stock >= 0) {
            $sql = "INSERT INTO products (name, price, stock) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sdi", $name, $price, $stock);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Product '{$name}' added successfully.";
            } else {
                $message = "Error adding product: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
            header("Location: product_management.php");
            exit;
        } else {
            $message = "Invalid input for product creation.";
        }
    }

    if (isset($_POST['update_product'])) {
        $id = intval($_POST['product_id']);
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']);

        if ($id > 0 && !empty($name) && $price > 0 && $stock >= 0) {
            $sql = "UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sddi", $name, $price, $stock, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Product ID {$id} updated successfully.";
            } else {
                $message = "Error updating product: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
            header("Location: product_management.php");
            exit;
        } else {
            $message = "Invalid input for product update.";
        }
    }
}
?>

<div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-2xl h-fit sticky top-4">
    <h2 class="text-2xl font-semibold mb-4 text-blue-600 border-b pb-2">
        <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
    </h2>
    
    <form method="POST" action="product_management.php" class="space-y-4">
        <?php if ($edit_product): ?>
            <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
            <input type="hidden" name="update_product" value="1">
        <?php else: ?>
            <input type="hidden" name="add_product" value="1">
        <?php endif; ?>

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input type="text" name="name" id="name" required class="mt-1 w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>">
        </div>
        
        <div>
            <label for="price" class="block text-sm font-medium text-gray-700">Price (₱)</label>
            <input type="number" name="price" id="price" required min="0.01" step="0.01" class="mt-1 w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                value="<?php echo htmlspecialchars($edit_product['price'] ?? ''); ?>">
        </div>

        <div>
            <label for="stock" class="block text-sm font-medium text-gray-700">Initial Stock/Inventory</label>
            <input type="number" name="stock" id="stock" required min="0" class="mt-1 w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                value="<?php echo htmlspecialchars($edit_product['stock'] ?? '0'); ?>">
        </div>

        <button type="submit" class="w-full py-3 px-4 rounded-lg font-semibold text-white 
            <?php echo $edit_product ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-600 hover:bg-blue-700'; ?> transition duration-150 shadow-md">
            <?php echo $edit_product ? 'Save Changes' : 'Add Product to Menu'; ?>
        </button>
        
        <?php if ($edit_product): ?>
            <a href="product_management.php" class="w-full block text-center py-2 text-sm text-gray-600 hover:text-gray-800 transition duration-150">
                Cancel Edit
            </a>
        <?php endif; ?>
    </form>
</div>