<?php

session_start();
include 'db.php';
include 'paypal_config.php';

header('Content-Type: application/json');

function get_paypal_access_token($client_id, $secret, $base_url) {
    return "ACCESS_TOKEN_PLACEHOLDER_FOR_DEMO";
}

function create_paypal_order($total_amount, $currency, $token, $base_url) {
    if ($token == "ACCESS_TOKEN_PLACEHOLDER_FOR_DEMO") {
        return [
            'id' => 'PAYPAL-' . time(),
            'status' => 'CREATED'
        ];
    } else {
        return ['error' => 'Authentication failed.'];
    }
}
function capture_paypal_order($paypal_order_id, $token, $base_url) {
    if ($token == "ACCESS_TOKEN_PLACEHOLDER_FOR_DEMO") {
         return [
            'status' => 'COMPLETED',
            'id' => 'CAPTURE-' . time(), 
            'purchase_units' => [['payments' => ['captures' => [['amount' => ['value' => '0.00', 'currency_code' => 'PHP']]]]]] 
        ];
    } else {
        return ['error' => 'Authentication failed.'];
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$input = json_decode(file_get_contents('php://input'), true) ?? []; 

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'No action specified.']);
    exit;
}

if ($action === 'save-details') {
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $customer_notes = trim($_POST['customer_notes'] ?? '');

    if (empty($delivery_address)) {
        echo json_encode(['success' => false, 'error' => 'Delivery address cannot be empty.']);
        exit;
    }

    if (isset($_SESSION['online_cart']) && !empty($_SESSION['online_cart'])) {
        $stmt_contact = mysqli_prepare($conn, "SELECT id, contact_number FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_contact, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt_contact);
        $result_contact = mysqli_stmt_get_result($stmt_contact);
        $contact_row = mysqli_fetch_assoc($result_contact);
        mysqli_stmt_close($stmt_contact);

        $total_amount = 0.00;
        foreach ($_SESSION['online_cart'] as $item) {
            $total_amount += $item['final_price'];
        }

        $_SESSION['temp_checkout_data'] = [
            'user_id' => $contact_row['id'] ?? 0, 
            'delivery_address' => $delivery_address,
            'customer_notes' => $customer_notes,
            'contact_number' => $contact_row['contact_number'] ?? '',
            'total_amount' => $total_amount,
            'cart' => $_SESSION['online_cart']
        ];
        
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Cart is empty or session expired.']);
    exit;
}
if ($action === 'create-order') {
    if (!isset($_SESSION['temp_checkout_data'])) {
        echo json_encode(['error' => 'Checkout data not found. Please restart checkout.']);
        exit;
    }

    $data = $_SESSION['temp_checkout_data'];
    $total = $data['total_amount'];
    $currency = CURRENCY;

    try {
        $token = get_paypal_access_token(PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_BASE_URL);
        $order = create_paypal_order($total, $currency, $token, PAYPAL_BASE_URL);

        if (isset($order['id'])) {
            $initial_status = 'Pending';
            $payment_type = 'PayPal';
            $payment_status = 'Awaiting Capture';
            $paypal_order_id = $order['id'];

            $sql_order = "INSERT INTO online_orders (user_id, total_amount, paypal_order_id, delivery_address, customer_notes, status, payment_type, payment_status, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_order = mysqli_prepare($conn, $sql_order);
            mysqli_stmt_bind_param($stmt_order, "idsssssss", $data['user_id'], $data['total_amount'], $paypal_order_id, $data['delivery_address'], $data['customer_notes'], $initial_status, $payment_type, $payment_status, $data['contact_number']);

            if (mysqli_stmt_execute($stmt_order)) {
                $db_order_id = mysqli_insert_id($conn);
                $sql_items = "INSERT INTO online_order_items (order_id, product_id, quantity, price, customizations) VALUES (?, ?, ?, ?, ?)";
                $stmt_items = mysqli_prepare($conn, $sql_items);

                foreach ($data['cart'] as $item) {
                    mysqli_stmt_bind_param($stmt_items, "iiids", $db_order_id, $item['product_id'], $item['quantity'], $item['final_price'], $item['custom_key_string']);
                    mysqli_stmt_execute($stmt_items);
                }
                mysqli_stmt_close($stmt_items);
                $_SESSION['temp_checkout_data']['db_order_id'] = $db_order_id; 
                echo json_encode(['id' => $order['id']]);

            } else {
                 echo json_encode(['error' => 'DB Error: Failed to create order record.']);
            }
            mysqli_stmt_close($stmt_order);


        } else {
            echo json_encode(['error' => 'Failed to get PayPal Order ID from API.']);
        }

    } catch (Exception $e) {
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}
if ($action === 'capture-order') {
    $paypal_order_id = $input['orderID'] ?? null;
    $db_order_id = $_SESSION['temp_checkout_data']['db_order_id'] ?? 0;

    if (!$paypal_order_id || $db_order_id === 0) {
        echo json_encode(['success' => false, 'error' => 'Missing Order ID or session data.']);
        exit;
    }

    try {
        $token = get_paypal_access_token(PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_BASE_URL);
        $capture_data = capture_paypal_order($paypal_order_id, $token, PAYPAL_BASE_URL);

        if (isset($capture_data['status']) && $capture_data['status'] === 'COMPLETED') {
            $new_status = 'Pending';
            $payment_status = 'COMPLETED';
            
            $sql_update = "UPDATE online_orders SET payment_status = ?, status = ? WHERE id = ? AND paypal_order_id = ? AND payment_status = 'Awaiting Capture'";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssis", $payment_status, $new_status, $db_order_id, $paypal_order_id);

            if (mysqli_stmt_execute($stmt_update)) {
                unset($_SESSION['online_cart']);
                unset($_SESSION['temp_checkout_data']);
                
                echo json_encode(['success' => true, 'message' => 'Payment captured and order updated.']);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Database update failed after payment capture. Order ID: ' . $db_order_id . '. Contact support.']);
                exit;
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error_message = $capture_data['error'] ?? 'PayPal capture failed. Please check your PayPal account.';
            $sql_update_fail = "UPDATE online_orders SET payment_status = 'FAILED' WHERE id = ?";
            $stmt_update_fail = mysqli_prepare($conn, $sql_update_fail);
            mysqli_stmt_bind_param($stmt_update_fail, "i", $db_order_id);
            mysqli_stmt_execute($stmt_update_fail);
            mysqli_stmt_close($stmt_update_fail);
            
            unset($_SESSION['online_cart']);
            unset($_SESSION['temp_checkout_data']);
            
            echo json_encode(['success' => false, 'error' => $error_message]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Server error during capture: ' . $e->getMessage()]);
        exit;
    }
}
echo json_encode(['success' => false, 'error' => 'Invalid action.']);
?>