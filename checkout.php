<?php
session_start();
include 'db.php';
include 'paypal_config.php'; 
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}
if (!isset($_SESSION['online_cart']) || empty($_SESSION['online_cart'])) {
    header("location: customer_dashboard.php");
    exit;
}
$username = $_SESSION['username'];
$user_id = 0;
$total_amount = 0.00;
$delivery_address = "";
$customer_notes = "";
$message = "";
$stmt_user = mysqli_prepare($conn, "SELECT id, contact_number FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_row = mysqli_fetch_assoc($result_user);
$user_id = $user_row['id'] ?? 0;
$contact_number = $user_row['contact_number'] ?? '';
mysqli_stmt_close($stmt_user);
// Safely calculate total amount, falling back to 0 if final_price is missing
foreach ($_SESSION['online_cart'] as $item) {
    $total_amount += $item['final_price'] ?? ($item['price'] * $item['quantity'] ?? 0);
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $delivery_address = trim($_POST['delivery_address']);
    $customer_notes = trim($_POST['customer_notes']);
    $payment_method = $_POST['payment_method'];
    if (empty($delivery_address)) {
        $message = "<p style='color:red;'>Delivery address is required.</p>";
    } else if ($payment_method == 'cash') {
        $initial_status = 'Pending';
        $payment_type = 'Cash'; 
        $payment_status = 'Awaiting Payment'; 
        $sql_order = "INSERT INTO online_orders (user_id, total_amount, delivery_address, customer_notes, status, payment_type, payment_status, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_order = mysqli_prepare($conn, $sql_order);
        mysqli_stmt_bind_param($stmt_order, "idssssss", $user_id, $total_amount, $delivery_address, $customer_notes, $initial_status, $payment_type, $payment_status, $contact_number);
        if (mysqli_stmt_execute($stmt_order)) {
            $order_id = mysqli_insert_id($conn);
            $sql_items = "INSERT INTO online_order_items (order_id, product_id, quantity, price, customizations) VALUES (?, ?, ?, ?, ?)";
            $stmt_items = mysqli_prepare($conn, $sql_items);
            foreach ($_SESSION['online_cart'] as $item) {
                mysqli_stmt_bind_param($stmt_items, "iiids", $order_id, $item['product_id'], $item['quantity'], $item['final_price'], $item['custom_key_string']);
                mysqli_stmt_execute($stmt_items);
            }
            mysqli_stmt_close($stmt_items);
            unset($_SESSION['online_cart']); 
            $_SESSION['message_success'] = "COD Order #$order_id placed successfully! Please await confirmation.";
            header("location: customer_order_history.php");
            exit;
        } else {
            $message = "<p style='color:red;'>Error placing order: " . mysqli_error($conn) . "</p>";
        }
        mysqli_stmt_close($stmt_order);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=<?php echo CURRENCY; ?>&intent=capture"></script>
</head>
<body>
<div class="container">
    <h2>Checkout & Payment</h2>
    <p><a href="customer_dashboard.php">Back to Shopping</a> | <a href="customer_login.php">Logout</a></p>
    <?php if (isset($_SESSION['message_success'])): ?>
        <p style='color:green;'><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>
    <?php echo $message; ?>
    <h3>Your Cart</h3>
    <table border="1">
        <tr><th>Item</th><th>Qty</th><th>Price</th></tr>
        <?php foreach ($_SESSION['online_cart'] as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td> 
                <td><?php echo $item['quantity']; ?></td>
                <td>₱ <?php 
                    $item_price = $item['final_price'] ?? ($item['price'] * $item['quantity'] ?? 0);
                    $unit_price = $item['quantity'] > 0 ? ($item_price / $item['quantity']) : $item_price;
                    echo number_format($unit_price, 2); 
                ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><td colspan="2"><strong>TOTAL:</strong></td><td><strong>₱ <?php echo number_format($total_amount, 2); ?></strong></td></tr>
    </table>
    <br>
    <form id="checkoutForm" action="" method="post">
        <h3>Delivery Details</h3>
        <label for="delivery_address">Delivery Address (Required):</label>
        <textarea name="delivery_address" id="delivery_address" rows="3" required><?php echo htmlspecialchars($delivery_address); ?></textarea><br><br>
        <label for="customer_notes">Notes for Barista/Delivery:</label>
        <textarea name="customer_notes" id="customer_notes" rows="2"><?php echo htmlspecialchars($customer_notes); ?></textarea><br><br>
        <h3>Payment Method</h3>
        <input type="radio" id="cash" name="payment_method" value="cash" checked>
        <label for="cash">Cash on Delivery (COD)</label><br><br>
        <input type="radio" id="paypal_radio" name="payment_method" value="paypal">
        <label for="paypal_radio">PayPal / Credit Card</label><br><br>
        <div id="paypal-button-container" style="display:none; margin-top: 15px;"></div>
        <div id="cod-submit-button">
            <input type="submit" name="place_order" value="Place COD Order">
        </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paypalRadio = document.getElementById('paypal_radio');
        const cashRadio = document.getElementById('cash');
        const paypalContainer = document.getElementById('paypal-button-container');
        const codSubmit = document.getElementById('cod-submit-button');
        const checkoutForm = document.getElementById('checkoutForm');
        function togglePaymentVisibility() {
            if (paypalRadio.checked) {
                paypalContainer.style.display = 'block';
                codSubmit.style.display = 'none';
            } else {
                paypalContainer.style.display = 'none';
                codSubmit.style.display = 'block';
            }
        }
        paypalRadio.addEventListener('change', togglePaymentVisibility);
        cashRadio.addEventListener('change', togglePaymentVisibility);
        togglePaymentVisibility();
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color:  'blue',
                shape:  'rect',
                label:  'paypal'
            },
            createOrder: function(data, actions) {
                const address = document.getElementById('delivery_address').value;
                const notes = document.getElementById('customer_notes').value;
                if (!address) {
                    alert("Please enter a delivery address before paying with PayPal.");
                    return actions.reject(); 
                }
                const formData = new FormData();
                formData.append('action', 'save-details');
                formData.append('delivery_address', address);
                formData.append('customer_notes', notes);
                return fetch('paypal_api.php', {
                    method: 'post',
                    body: formData
                })
                .then(response => response.json())
                .then(saveResponse => {
                    if (saveResponse.success) {
                        return fetch('paypal_api.php', {
                            method: 'post',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'create-order',
                                total: <?php echo $total_amount; ?>,
                                currency: '<?php echo CURRENCY; ?>'
                            })
                        })
                        .then(response => response.json())
                        .then(orderData => {
                            if (orderData.id) {
                                return orderData.id;
                            } else {
                                alert("Failed to create PayPal order: " + (orderData.error || 'Unknown Error'));
                                return actions.reject();
                            }
                        });
                    } else {
                        alert("Failed to save delivery details: " + saveResponse.error);
                        return actions.reject();
                    }
                });
            },
            onApprove: function(data, actions) {
                return fetch('paypal_api.php', {
                    method: 'post',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'capture-order',
                        orderID: data.orderID
                    })
                })
                .then(response => response.json())
                .then(captureData => {
                    if (captureData.success) {
                        alert('Payment successful! Your order has been placed.');
                        window.location.href = 'customer_order_history.php';
                    } else {
                        alert('Payment failed on server: ' + captureData.error);
                        window.location.reload(); 
                    }
                })
                .catch(error => {
                    console.error('Error capturing payment:', error);
                    alert('An unexpected error occurred during payment processing.');
                });
            },
            onCancel: function(data) {
                alert('Payment cancelled by you. You can select another payment method or try again.');
            },
            onError: function(err) {
                console.error('PayPal Error:', err);
                alert('An error occurred with PayPal payment. Please check console for details.');
            }
        }).render('#paypal-button-container'); 
    });
    </script>
</div>
</body>
</html>