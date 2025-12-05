<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

if (!isset($_SESSION['online_cart'])) {
    $_SESSION['online_cart'] = [];
}

const EXTRA_SHOT_PRICE = 20.00; 

function display_customizations($customizations, $extra_shot_price) {
    if (empty($customizations) || !is_array($customizations)) {
        return '';
    }
    
    $custom_parts = [];
    if (isset($customizations['ice_level'])) {
        $custom_parts[] = "Ice: " . htmlspecialchars(ucfirst($customizations['ice_level']));
    }
    if (isset($customizations['extra_shot']) && $customizations['extra_shot']) {
        $custom_parts[] = "Extra Shot (+₱" . number_format($extra_shot_price, 2) . ")";
    }
    
    if (empty($custom_parts)) {
        return '';
    }
    
    return " <small style='display:block; font-size: 0.8em; color: #5D4037;'>(" . implode(', ', $custom_parts) . ")</small>";
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $base_price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    
    // Quantity logic (retained from original file)
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($quantity <= 0) {
        $quantity = 1;
    }

    // --- NEW: Customization Logic ---
    $ice_level = $_POST['ice_level'] ?? 'regular';
    $extra_shot = isset($_POST['extra_shot']) ? true : false;
    
    $customizations = [
        'ice_level' => $ice_level,
        'extra_shot' => $extra_shot
    ];
    $customization_price = $extra_shot ? EXTRA_SHOT_PRICE : 0.00;
    $final_price = $base_price + $customization_price;
    
    // Create a unique cart key based on product ID and customization
    $custom_key_string = json_encode($customizations);
    $cart_key = $product_id . '_' . md5($custom_key_string);
    // --- END NEW: Customization Logic ---
    

    if (isset($_SESSION['online_cart'][$cart_key])) {
        $_SESSION['online_cart'][$cart_key]['quantity'] += $quantity;
    } else {
        $_SESSION['online_cart'][$cart_key] = [
            'product_id' => $product_id, 
            'name' => $product_name,
            'base_price' => $base_price, 
            'customization_price' => $customization_price, 
            'price' => $final_price, 
            'quantity' => $quantity, 
            'customizations' => $customizations, 
            'customizations_json' => $custom_key_string
        ];
    }
    header("location: customer_dashboard.php");
    exit;
}
// --- NEW: Remove Item Logic (to allow removal by unique cart key) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_item'])) {
    $cart_key = $_POST['remove_item'];
    if (isset($_SESSION['online_cart'][$cart_key])) {
        unset($_SESSION['online_cart'][$cart_key]);
    }
    header("location: customer_dashboard.php");
    exit;
}
// --- END NEW: Remove Item Logic ---


// === FETCH PRODUCTS GROUPED BY CATEGORY ===

// 1. Fetch Drinks
$sql_drinks = "SELECT id, name, price, image_path FROM products WHERE category = 'drink' ORDER BY name ASC";
$result_drinks = mysqli_query($conn, $sql_drinks);

// 2. Fetch Snacks
$sql_snacks = "SELECT id, name, price, image_path FROM products WHERE category = 'snack' ORDER BY name ASC";
$result_snacks = mysqli_query($conn, $sql_snacks);


// Calculate Total Cart
$total_cart = 0;
foreach ($_SESSION['online_cart'] as $item) {
    $total_cart += $item['price'] * $item['quantity'];
}

// SECURE FETCH USER ID (Retained from original file)
$username = $_SESSION['username'];
$stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$user_id_result = mysqli_stmt_get_result($stmt_user);
$user_id_row = mysqli_fetch_assoc($user_id_result);
$user_id = $user_id_row['id'];
mysqli_stmt_close($stmt_user);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* General Layout/Design */
       body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; 
            color: #4E342E; 
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2, h3, h4 {
            color: #4E342E; 
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 5px;
            margin-top: 20px;
        }

        a {
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            padding: 5px;
        }

        a:hover {
            color: #5D4037;
            text-decoration: underline;
        }


        /* ==================================
           PRODUCT GRID & CARD STYLES
           ================================== */
        
        .product-image {
            width: 120px; 
            height: 120px;
            object-fit: cover; 
            border-radius: 8px; 
            margin: 0 auto 10px auto; 
            display: block; 
            border: 2px solid #E0E0E0;
        }
        
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 40px; 
        }

        .product-card {
            background: #FDFDFD;
            border: 1px solid #E0E0E0;
            width: 100%; 
            padding:30px 10px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
          
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            border-color: #8B4513; 
        }

        .product-card h4 {
            margin: 0 0 5px 0;
            font-size: 1.1em;
            color: #4E342E;
            border-bottom: none;
            padding-bottom: 0;
        }

        .product-card p {
            font-size: 1.3em;
            color: #8B4513; 
            font-weight: bold;
            margin: 5px 0 15px 0;
        }

        /* Quantity input styling */
        .quantity-input {
            width: 50px; 
            padding: 5px; 
            margin: 5px auto 10px auto; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            text-align: center;
            display: block; 
        }
        
        /* Button Styles */
        .product-card input[type="submit"] {
            width: 100%;
            padding: 10px 5px;
            background-color: #66BB6A; 
            color: white;
            border: none;
            border-radius: 5px;
        }
        .product-card input[type="submit"]:hover {
            background-color: #4CAF50;
        }

        input[type="submit"], button {
            background-color: #8B4513; 
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
            text-transform: uppercase;
            font-weight: bold;
        }
        
        /* New Styles for Checkout Form */
        .checkout-form textarea {
            resize: vertical; 
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .checkout-form label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #4E342E;
        }
        .checkout-form input[type="submit"] {
            width: 100%;
            background-color: #66BB6A; 
        }
        .checkout-form input[type="submit"]:hover {
            background-color: #4CAF50; 
        }

        /* New Styles for Customization in Card */
        .custom-select {
            display: block;
            width: 100%;
            padding: 5px;
            margin-bottom: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .custom-checkbox-label {
            display: block;
            text-align: left;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

    </style>
</head>
<body>
<div class="container">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
    <p>
        <a href="customer_edit_account.php">Update Account</a> | 
        <a href="customer_order_history.php">Order History</a> | 
        <a href="logout_customer.php">Logout</a>
    </p>
    
    <?php if (isset($_SESSION['message_success'])): ?>
        <p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?>
        <p class="message-error"><?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?></p>
    <?php endif; ?>

    <div class="online-cart-section">
        <h3>Your Online Cart</h3>
        <table border="1">
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
                <th>Action</th>
            </tr>
            <?php
            if (empty($_SESSION['online_cart'])) {
                echo "<tr><td colspan='5'>Your cart is empty.</td></tr>";
            } else {
                $total_cart = 0;
                foreach ($_SESSION['online_cart'] as $cart_key => $item) {
                    $subtotal = $item['price'] * $item['quantity'];
                    $total_cart += $subtotal;
                    $custom_html = display_customizations($item['customizations'], EXTRA_SHOT_PRICE);
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($item['name']) . $custom_html . "</td>";
                    echo "<td>" . htmlspecialchars($item['quantity']) . "</td>";
                    echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                    echo "<td>₱ " . number_format($subtotal, 2) . "</td>";
                    echo "<td>
                        <form action=\"\" method=\"post\" style=\"display:inline;\">
                            <input type=\"hidden\" name=\"remove_item\" value=\"{$cart_key}\">
                            <input type=\"submit\" value=\"X\" style=\"background-color: #f44336; border: none; color: white; cursor: pointer; padding: 5px 10px;\">
                        </form>
                    </td>";
                    echo "</tr>";
                }
            }
            ?>
        </table>

        <br>
        <h4>Cart Total: ₱ <?php echo number_format($total_cart, 2); ?></h4>

        <?php if ($total_cart > 0): ?>
            <div class="checkout-form" style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px;">
                <h4>Delivery Details</h4>
                <form action="place_order.php" method="post">
                    <input type="hidden" name="total_amount" value="<?php echo $total_cart; ?>">
                    
                    <label for="delivery_address">Delivery Address:</label>
                    <textarea name="delivery_address" rows="3" required style="width: 95%; margin-bottom: 10px;"></textarea>
                    
                    <label for="customer_notes">Customer Notes (Optional):</label>
                    <textarea name="customer_notes" rows="2" style="width: 95%; margin-bottom: 15px;"></textarea>
                    
                    <input type="submit" name="place_order" value="Place Order (₱ <?php echo number_format($total_cart, 2); ?>)">
                </form>
            </div>
        <?php endif; ?>
    </div>

    <h3>☕ Drinks Menu</h3>
    <div class="product-list">
        <?php 
        if (mysqli_num_rows($result_drinks) > 0) {
            while($row = mysqli_fetch_assoc($result_drinks)): 
                $image_file = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'placeholder.png';
            ?>
            <div class="product-card">
                <img src="./product_images/<?php echo $image_file; ?>" 
                     alt="<?php echo htmlspecialchars($row['name']); ?>" 
                     class="product-image">

                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                <p>₱ <?php echo number_format($row['price'], 2); ?></p>
                
                <form action="" method="post">
                    <label for="qty-<?php echo $row['id']; ?>" style="font-size: 0.9em; display: block; margin-bottom: 5px;">Quantity:</label>
                    <input type="number" 
                           id="qty-<?php echo $row['id']; ?>" 
                           name="quantity" 
                           value="1" 
                           min="1" 
                           class="quantity-input" 
                           required>
                    
                    <label for="ice-<?php echo $row['id']; ?>" style="display: block; text-align: left; font-size: 0.9em; margin-top: 5px;">Ice Level:</label>
                    <select name="ice_level" id="ice-<?php echo $row['id']; ?>" class="custom-select">
                        <option value="regular">Regular</option>
                        <option value="less ice">Less Ice</option>
                        <option value="more ice">More Ice</option>
                    </select>

                    <label class="custom-checkbox-label">
                        <input type="checkbox" name="extra_shot" style="margin-right: 5px;">
                        Extra Shot (+₱<?php echo number_format(EXTRA_SHOT_PRICE, 2); ?>)
                    </label>

                    
                    <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                    <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                    
                    <input type="submit" name="add_to_cart" value="Add to Cart">
                </form>
            </div>
        <?php endwhile; 
        } else {
            echo "<p>No drinks are currently available.</p>";
        }
        ?>
    </div>
    
    <h3>🍩 Snacks</h3>
    <div class="product-list">
        <?php 
        if (mysqli_num_rows($result_snacks) > 0) {
            while($row = mysqli_fetch_assoc($result_snacks)): 
                $image_file = !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'placeholder.png';
            ?>
            <div class="product-card">
                <img src="./product_images/<?php echo $image_file; ?>" 
                     alt="<?php echo htmlspecialchars($row['name']); ?>" 
                     class="product-image">

                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                <p>₱ <?php echo number_format($row['price'], 2); ?></p>
                
                <form action="" method="post">
                    <label for="qty-<?php echo $row['id']; ?>" style="font-size: 0.9em; display: block; margin-bottom: 5px;">Quantity:</label>
                    <input type="number" 
                           id="qty-<?php echo $row['id']; ?>" 
                           name="quantity" 
                           value="1" 
                           min="1" 
                           class="quantity-input" 
                           required>
                           
                    <label for="ice-snack-<?php echo $row['id']; ?>" style="display: block; text-align: left; font-size: 0.9em; margin-top: 5px;">Ice Level:</label>
                    <select name="ice_level" id="ice-snack-<?php echo $row['id']; ?>" class="custom-select">
                        <option value="regular">Regular</option>
                        <option value="less ice">Less Ice</option>
                        <option value="more ice">More Ice</option>
                    </select>

                    <label class="custom-checkbox-label">
                        <input type="checkbox" name="extra_shot" style="margin-right: 5px;">
                        Extra Shot (+₱<?php echo number_format(EXTRA_SHOT_PRICE, 2); ?>)
                    </label>

                    
                    <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                    <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                    
                    <input type="submit" name="add_to_cart" value="Add to Cart">
                </form>
            </div>
        <?php endwhile;
        } else {
            echo "<p>No snacks are currently available.</p>";
        }
        ?>
    </div>
</div>
</body>
</html>