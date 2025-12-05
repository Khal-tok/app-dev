<?php
$extra_shot_price_default = 20.00;

if (isset($conn)) {
    $sql_setting = "SELECT setting_value FROM system_settings WHERE setting_key = 'EXTRA_SHOT_PRICE'";
    $result_setting = @mysqli_query($conn, $sql_setting);
    
    if ($result_setting && $row = mysqli_fetch_assoc($result_setting)) {
        $extra_shot_price_default = (float)$row['setting_value'];
    }
}

if (!isset($EXTRA_SHOT_PRICE)) {
    $EXTRA_SHOT_PRICE = $extra_shot_price_default;
}

function display_customizations($customizations, $extra_shot_price) {
    if (empty($customizations)) {
        return '';
    }
    
    $custom_parts = [];
    
    if (isset($customizations['size'])) {
        $custom_parts[] = "Size: " . htmlspecialchars($customizations['size']);
    }

    if (isset($customizations['ice_level'])) {
        $custom_parts[] = "Ice: " . htmlspecialchars(ucfirst($customizations['ice_level']));
    }
    if (isset($customizations['addon']) && $customizations['addon'] == 'extra_shot') {
        $custom_parts[] = "Extra Shot (+₱" . number_format($extra_shot_price, 2) . ")";
    }
    
    if (empty($custom_parts)) {
        return '';
    }
    
    return " <small>(" . implode(', ', $custom_parts) . ")</small>";
}
?>