<?php
define('PAYPAL_CLIENT_ID', 'AZkafM5IZnjdxRPo6L2VG-mj_vHgXrMO29gNbGgx3UlMLr1s_QfptxVMhSDoDIl-qwxwqq-YH7Oy0XA2 ');
define('PAYPAL_SECRET', 'EArXaYhreoXnip6cHW2assZ_Kg6vNAYMnyJyD5hH7rN7G2PyJoJ2WElC5VOxv1e8tK_1ErjE45kfiYAt');
define('PAYPAL_ENVIRONMENT', 'sandbox');
define('PAYPAL_BASE_URL', (PAYPAL_ENVIRONMENT === 'live')
    ? 'https://api.paypal.com'
    : 'https://sandbox.paypal.com');
define('CURRENCY', 'PHP'); 
?>