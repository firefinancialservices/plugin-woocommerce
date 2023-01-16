# WooCommerce Payment Gateway plugin for Fire Open Banking
WooCommerce plugin to allow use of Fire Open Banking to recieve payments.


## Installation
1. Install woocommerce-gateway-fireob.zip to an existing WooCommerce installation
2. Ensure e.g. "WP Crontrol" plugin is also installed
3. Activate and configure the Fire Open Banking plugin with your own Fire API credentials - see below
4. Check crontrol to ensure two hourly entries are added - /wp-cron.php?fireob_set_status=1 and /wp-cron.php?fireob_set_status_code=1

## Creating API Credentials
Follow the instructions in the [FAQ](https://www.fire.com/business-account-faqs/#toggle-id-39) to configure a key with the following permissions:

- Get list of all Payment Requests sent and their details (PERM_BUSINESS_GET_PAYMENT_REQUESTS)
- Get list of all Payment Attempts related to a Payment Request (PERM_BUSINESS_GET_PAYMENT_REQUEST_PAYMENTS)
- Create a Payment Request (PERM_BUSINESS_POST_PAYMENT_REQUEST)
- Get Payment Details (PERM_BUSINESS_GET_PAYMENT)

This will create the API key you need to copy into the WooCommerce settings page. 
