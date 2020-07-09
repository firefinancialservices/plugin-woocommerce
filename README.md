# WooCommerce Payment Gateway plugin for Fire Open Banking
(Basic) WooCommerce plugin to allow use of Fire Open Banking to recieve payments.


## Installation
1. Install the plugin.zip to an existing WooCommerce installation
2. Ensure e.g. "WP Crontrol" plugin is also installed
3. Activate and configure the Fire Open Banking plugin with your own Fire API credentials
4. Check crontrol to ensure two hourly entries are added - /wp-cron.php?fireob_set_status=1 and /wp-cron.php?fireob_set_status_code=1

