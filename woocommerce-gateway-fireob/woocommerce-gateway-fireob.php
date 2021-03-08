<?php

/**
 * Plugin Name: WooCommerce Fire Open Banking Payment Gateway
 * Plugin URI: https://www.fire.com/fireopenpayments
 * Description: Fire Open Banking Payment gateway for woocommerce. This plugin supports woocommerce version 3.0.0 or greater.
 * Author: Fire Financial Services
 * Author URI: https://www.fire.com
 * Version: 0.0.3
 * Text Domain: woocommerce-gateway-fireob
 */

defined('ABSPATH') or exit;
//ob_start();

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_fireob_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_FireOB';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_fireob_add_to_gateways');


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_fireob_plugin_links($links)
{

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=fireob') . '">' . __('Configure', 'woocommerce-gateway-fireob') . '</a>'
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_fireob_plugin_links');


add_action('plugins_loaded', 'wc_fireob_init', 0);

function wc_fireob_init()
{


    class WC_Gateway_FireOB extends WC_Payment_Gateway
    {

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {

            $this->id                 = 'fireob'; //key is very important
            $this->icon               = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('Fire Open Banking Payment Gateway', 'woocommerce-gateway-fireob');
            $this->method_description = __('Allows online payments using Bank Account. Very handy if you use your Payment By Fire Open Banking gateway for another payment method, and can help with testing. Orders are marked as "completed" when received.', 'woocommerce-gateway-fireob');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');

            $this->url  = $this->get_option('url');

            $this->client_id = $this->settings['client_id'];
            $this->client_key = $this->settings['client_key'];
            $this->refresh_token = $this->settings['refresh_token'];
            $this->testmode = $this->settings['testmode'];

            $this->icanTo_EUR = $this->settings['icanTo_EUR'];
            $this->icanTo_GBP = $this->settings['icanTo_GBP'];
            $this->order_status = $this->settings['order_status'];

            $this->description      = $this->get_option('description');

            $this->instructions        = "";

            // Actions

            add_action('woocommerce_api_fob', array($this, 'fob'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_receipt_fireob', array(&$this, 'receipt_page'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

            $hold_stock_minutes = get_option('woocommerce_hold_stock_minutes');
            if ($hold_stock_minutes < 4320) //72 hours set this limit
                update_option('woocommerce_hold_stock_minutes', '4320');

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        } //end of function



        /**
         * Check if this gateway is enabled and available in the user's country
         */
        public function is_valid_for_use()
        {
            if (!in_array(get_woocommerce_currency(), array('GBP', 'EUR'))) return false;

            return true;
        }


        /**
         * Initialize Gateway Settings Form Fields
         **/

        public function init_form_fields()
        {

            $this->form_fields = apply_filters(
                'wc_hpp_form_fields',
                array(

                    'enabled' => array(
                        'title'   => __('Enable/Disable', 'woocommerce-gateway-fireob'),
                        'type'    => 'checkbox',
                        'label'   => __('Enable Fire Open Banking Payment Gateway', 'woocommerce-gateway-fireob'),
                        'default' => 'yes'
                    ),

                    'title' => array(
                        'title'       => __('Title', 'woocommerce-gateway-fireob'),
                        'type'        => 'text',
                        'description' => __('This controls the title for the payment method the customer sees during checkout.', 'woocommerce-gateway-fireob'),
                        'default'     => __('Payment By Fire Open Banking Gateway', 'woocommerce-gateway-fireob'),
                        'desc_tip'    => true,
                    ),

                    'description' => array(
                        'title'       => __('Description', 'woocommerce-gateway-fireob'),
                        'type'        => 'text',
                        'description' => __('This controls the description for the payment method the customer sees during checkout.', 'woocommerce-gateway-fireob'),
                        'default'     => __('Pay directly from your bank account via Open Banking', 'woocommerce-gateway-fireob'),
                        'desc_tip'    => true,
                    ),

                    'testmode' => array(
                        'title'       => __('Fire Open Banking Sandbox', 'woocommerce-gateway-fireob'),
                        'type'        => 'checkbox',
                        'description' => __('Place the payment gateway in sandbox mode.', 'woocommerce-gateway-fireob'),
                        'default'     => 'yes',
                        'desc_tip'    => true,
                    ),

                    'client_id' => array(
                        'title'       => __('Client ID', 'woocommerce-gateway-fireob'),
                        'type'        => 'password',
                        'description' => __('This is the Client ID, received from Fire Open Banking.', 'woocommerce-gateway-fireob'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),

                    'client_key' => array(
                        'title'       => __('Client Key', 'woocommerce-gateway-fireob'),
                        'type'        => 'password',
                        'description' => __('This is the Client Key, received from Fire Open Banking.', 'woocommerce-gateway-fireob'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),

                    'refresh_token' => array(
                        'title'       => __('Refresh Token', 'woocommerce-gateway-fireob'),
                        'type'        => 'password',
                        'description' => __('This is the Refresh Token, received from Fire Open Banking.', 'woocommerce-gateway-fireob'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),

                    'icanTo_EUR' => array(
                        'title'       => __('icanTo EUR', 'woocommerce-gateway-fireob'),
                        'type'        => 'text',
                        'description' => __('icanTo EURO Account', 'woocommerce-gateway-fireob'),
                        'default'     => '1519',
                        'desc_tip'    => true,
                    ),

                    'icanTo_GBP' => array(
                        'title'       => __('icanTo GBP', 'woocommerce-gateway-fireob'),
                        'type'        => 'text',
                        'description' => __('icanTo GBP Account', 'woocommerce-gateway-fireob'),
                        'default'     => '1520',
                        'desc_tip'    => true,
                    ),

                    'order_status' => array(
                        'title'       => __('Order Status', 'woocommerce-gateway-fireob'),
                        'type'        => 'select',
                        'description' => __('Order Status During Checkout Processing/On-Hold', 'woocommerce-gateway-fireob'),
                        'default'     => 'processing',
                        'desc_tip'    => true,
                        'options' => array('processing' => 'Processing', 'on-hold' => 'On Hold')
                    ),

                )

            );
        } //end of function


        //Call Back Hook from API to track status
        public function fob()
        {

            global $woocommerce;
            $temp_order            = new WC_Order();

            $order  = new WC_Order($_GET['oid']);


            $redirect_url = '';

            if (isset($_GET['paymentUuid']) and isset($_GET['oid'])) {


                if (empty($_SESSION["accessToken"]))
                    $accessToken = $this->get_accessToken();
                else
                    $accessToken = @$_SESSION['accessToken'];

                $fireob_ps = $this->get_payment_status($_GET['paymentUuid'], $accessToken);

                // this should be set in all successful cases
                $payment_uuid = $_GET['paymentUuid'];
                if (!isset($payment_uuid))
                    $payment_uuid = "";

                update_post_meta($order->get_id(), '_fireob_paymentUuid',  $payment_uuid);
                
                // for all statuses, add order note 
                $order->add_order_note(
                    'Fire OB Online Banking payment is ' . $fireob_ps . '!<br/>Payment Uuid: ' . $payment_uuid
                );

                update_post_meta($order->get_id(), '_fireob_payment_code',  @$_SESSION['payment_code']);
                
                unset($_SESSION['payment_code']);
                unset($_SESSION['order_id']); 
                unset($_SESSION["accessToken"]);

                // 'success' statuses 
                if ($fireob_ps == 'AUTHORISED' || $fireob_ps == 'PAID') {
                    $current_version = get_option('woocommerce_version', null);
                    if (version_compare($current_version, '3.0.0', '<')) {
                        $order->reduce_order_stock();
                    } else {
                        wc_reduce_stock_levels($order->get_id());
                    }
                    //Remove cart
                    WC()->cart->empty_cart();

                    // ensure we redirect to order confirmation page
                    $redirect_url = $order->get_checkout_order_received_url();
                    // this should be okay to run now while backend continues processing
                    $this->web_redirect($redirect_url);
                }

                // specific handling for specific statuses
                if ($fireob_ps == 'AUTHORISED') {
                    // set status to settings-defined status
                    $order->update_status($this->order_status);
                    exit;
                } else if ($fireob_ps == 'PAID') {
                    // set status to processing
                    $order->update_status('processing');
                    exit;
                } else if ($fireob_ps == 'NOT_AUTHORISED') {
                    $order->update_status('failed');
                    $redirect = $order->get_cancel_order_url();
                    wp_redirect($redirect);
                    return;
                } else {
                    // same steps as NOT_AUTHORISED for now
                    $order->update_status('failed');
                    $redirect = $order->get_cancel_order_url();
                    wp_redirect($redirect);
                    return;
                }

                exit();
            } //end of Outer If Block 


        } //end of function


        //This can be ACTIVE, EXPIRED or CLOSED. Only ACTIVE requests can be paid.
        public function payment_status_by_code($code, $accessToken)
        {

            if ($this->testmode == 'yes')
                $url = 'https://api-preprod.fire.com/business/v1/paymentrequests/' . $code;
            else
                $url = 'https://api.fire.com/business/v1/paymentrequests/' . $code;


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,

                array(
                    "Authorization: Bearer " . $accessToken,
                    "Content-Type: application/json"
                )

            );

            $res = curl_exec($ch);
            curl_close($ch);
            $jsonA = json_decode($res, 1);
            $status = $jsonA['status'];

            return $status;
        } //end of function

        //This function will return paymentUuid by payment list using payment code
        public function payment_list_by_code($code, $accessToken)
        {

            if ($this->testmode == 'yes')
                $url = 'https://api-preprod.fire.com/business/v1/paymentrequests/' . $code . '/payments';
            else
                $url = 'https://api.fire.com/business/v1/paymentrequests/' . $code . '/payments';


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,

                array(
                    "Authorization: Bearer " . $accessToken,
                    "Content-Type: application/json"
                )

            );

            $res = curl_exec($ch);
            curl_close($ch);
            $jsonA = json_decode($res, 1);

            $paymentUuid = $jsonA['pisPaymentRequestPayments']['0']['paymentUuid'];

            return $paymentUuid;
        } //end of function


        //Generate Access Token
        public function get_accessToken()
        {

            if ($this->testmode == 'yes')
                $url = 'https://api-preprod.fire.com/business/v1/apps/accesstokens';
            else
                $url = 'https://api.fire.com/business/v1/apps/accesstokens';

            $CLIENT_ID = $this->client_id;
            $CLIENT_KEY = $this->client_key;
            $REFRESH_TOKEN = $this->refresh_token;
            // millisecond-accuracy is probably sufficient; second-accuracy is not
            $NONCE = round(microtime(true) * 1000);
            $SECRET = hash("sha256", $NONCE . $CLIENT_KEY, false);

            $postArgs = array(

                'clientId' => $CLIENT_ID,
                'refreshToken' => $REFRESH_TOKEN,
                'nonce' => $NONCE,
                'grantType' => 'AccessToken',
                'clientSecret' => $SECRET

            );

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postArgs));

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                echo 'Error:' . curl_error($ch);
            }
            curl_close($ch);
            $arr = json_decode($result, 1);

            //$businessId = $arr['businessId'];
            //$apiApplicationId = $arr['apiApplicationId'];
            $accessToken = $arr['accessToken'];

            return $accessToken;
        } //end of function


        //Send Payment Request
        public function send_pr($order, $accessToken)
        {
            global $woocommerce;

            if ($this->testmode == 'yes')
                $url = 'https://api-preprod.fire.com/business/v1/paymentrequests';
            else
                $url = 'https://api.fire.com/business/v1/paymentrequests';

            $header = array(
                "Authorization: Bearer " . $accessToken,
                "Content-Type: application/json"
            );

            @$_SESSION['order_id'] = $order->get_id();

            $currency  = $order->get_currency();

            if ($currency == 'GBP')
                $icanTo = $this->icanTo_GBP;
            else
                $icanTo = $this->icanTo_EUR;


            $oid = $order->get_id();
            $returnUrl = $this->getDomain() . '?wc-api=fob&oid=' . $oid;

            if (count($order->get_items()) == 1) {

                foreach ($order->get_items() as $item) {
                    $goodsInfo = $item['name'];
                }
            } else {
                $goodsInfo = count($order->get_items()) . " Items - " . get_bloginfo('name');
            }


            $post = array(

                'type' => 'OTHER',
                'icanTo' => $icanTo,
                'currency' => $currency,
                'amount' => $order->get_total() * 100,
                'myRef' => "WooCommerce Order: " . $order->get_id(),
                'description' => $goodsInfo,
                'maxNumberPayments' => 1,
                'returnUrl' => $returnUrl, //return url must be on ssl

                'orderDetails' => array(

                    'orderId' => $order->get_id(),
                    'customerNumber' => $order->get_billing_email(),
                    'variableReference' => 'WooCommerce',
                    'deliveryAddressLine1' => $order->get_billing_address_1(),
                    'deliveryCity' => $order->get_billing_city(),
                    'deliveryCountry' => $order->get_billing_country()

                ) //inner array


            ); //outer array


            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($curl, CURLOPT_ENCODING, "");
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_TIMEOUT, 0);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post));
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
            $res = curl_exec($curl);
            curl_close($curl);


            $res = json_decode($res, 1);
            $code = $res['code'];

            return $code;
        } //end of function


        //Create Payment URL
        public function payment_url($code)
        {

            if ($this->testmode == 'yes')
                $payment_url = "https://payments-preprod.fire.com/" . $code;
            else
                $payment_url = "https://payments.fire.com/" . $code;

            return $payment_url;
        } //end of function


        /**
         * Output for the order received page.
         */

        public function thankyou_page()
        {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }


        public function getDomain()
        {
            // I don't think this does what it thinks it does (or at least what's using it thinks it does...)

            $sURL    = site_url(); // WordPress function
            $asParts = parse_url($sURL); // PHP function

            $sScheme = $asParts['scheme'];
            $sHost   = $asParts['host'];
            $nPort   = 'https' == $sScheme and 443 == $nPort ? '' : $nPort;
            $sPort   = !empty($sPort) ? ":$nPort" : '';
            $sReturn = 'https://' . $sHost;

            return $sReturn;
        } //end of fucntion

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {

            if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        } //end of function


        public function web_redirect($url)
        {

            echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
        } //end of function

        public function receipt_page($order)
        {

            global $woocommerce;
            $order         = new WC_Order($order);

            $order->update_status('pending');

            echo '<p>' . __('Thank you for your order, please click the button below to pay with Fire Open Banking. Do not close this window (or click the back button). You will be redirected back to the website once your payment has been received.', 'woocommerce-gateway-fireob') . '</p>';

            echo $this->generate_fire_form($order);
        } //end of function

        //Generate Fire Form with redirect URL to API
        public function generate_fire_form($order_id)
        {

            global $woocommerce;
            $order         = new WC_Order($order_id);
            $timeStamp     = time();

            $oid         = $order->get_id();

            $accessToken = $this->get_accessToken();
            @$_SESSION['accessToken'] = $accessToken;

            $payment_code = $this->send_pr($order, $accessToken);

            $processURI = $this->payment_url($payment_code);

            @$_SESSION['payment_code'] = $payment_code;

            update_post_meta($order->get_id(), '_fireob_payment_code',  $payment_code);

            return  $this->web_redirect($processURI);
        } //end of function

        public function get_payment_status($paymentUuid, $accessToken)
        {

            if ($this->testmode == 'yes')
                $url = 'https://api-preprod.fire.com/business/v1/payments/' . $paymentUuid;
            else
                $url = 'https://api.fire.com/business/v1/payments/' . $paymentUuid;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,

                array(
                    "Authorization: Bearer " . $accessToken,
                    "Content-Type: application/json"
                )

            );

            $res = curl_exec($ch);
            curl_close($ch);
            $jsonA = json_decode($res, 1);

            return $jsonA['status'];
        }


        public function process_payment($order_id)
        {

            $order = new WC_Order($order_id);

            return array(
                'result'     => 'success',
                'redirect'    => $order->get_checkout_payment_url(true)
            );
        } //end of function


    } // end \WC_Gateway_HPP class


} //end of outer function



//Cron Job Hooks Code

// Make sure it's called whenever WordPress Frontend loads
add_action('wp', 'fireob_set_services_listings_cron');
function fireob_set_services_listings_cron()
{
    if (!wp_next_scheduled('fireob_set_order_status_cron_act')) {
        wp_schedule_event(time(), 'every_hour', 'fireob_set_order_status_cron_act');
    }

    if (!wp_next_scheduled('fireob_set_order_status_code_cron_act')) {
        wp_schedule_event(time(), 'every_hour', 'fireob_set_order_status_code_cron_act');
    }
} //end of function

//Run this url: http://localhost/wordpress/wp-cron.php?fireob_set_status=1
add_action('fireob_set_order_status_cron_act', 'fireob_set_status');
function fireob_set_status()
{

    global $wpdb;

    $query = $wpdb->get_results("
	
	SELECT pm.meta_value AS fireob_paymentUuid, pm.post_id AS order_id
        FROM {$wpdb->prefix}postmeta AS pm
        LEFT JOIN {$wpdb->prefix}posts AS p
        ON pm.post_id = p.ID
        WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-processing'
        And pm.meta_key = '_fireob_paymentUuid'
        ORDER BY pm.meta_value ASC, pm.post_id DESC
	
	");

    $FireOB = new WC_Gateway_FireOB();
    foreach ($query as $result) {

        $accessToken = $FireOB->get_accessToken();

        $fireob_ps = $FireOB->get_payment_status($result->fireob_paymentUuid, $accessToken);
        $order = new WC_Order($result->order_id);
        if ($fireob_ps == 'PAID') {
            $order->update_status('completed'); // order note is optional, if you want to  add a note to order
        }
    } //end of loop


} //end of function

//Run this url: http://localhost/wordpress/wp-cron.php?fireob_set_status_code=1
add_action('fireob_set_order_status_code_cron_act', 'fireob_set_status_code');
function fireob_set_status_code()
{
    global $wpdb;

    $query = $wpdb->get_results("
	
	SELECT pm.meta_value AS fireob_payment_code, pm.post_id AS order_id
        FROM {$wpdb->prefix}postmeta AS pm
        LEFT JOIN {$wpdb->prefix}posts AS p
        ON pm.post_id = p.ID
        WHERE p.post_type = 'shop_order'
        AND p.post_status = 'wc-pending'
        And pm.meta_key = '_fireob_payment_code'
        ORDER BY pm.meta_value ASC, pm.post_id DESC
	
	");

    $FireOB = new WC_Gateway_FireOB();
    foreach ($query as $result) {

        $accessToken = $FireOB->get_accessToken();
        $fireob_ps = $FireOB->payment_status_by_code($result->fireob_payment_code, $accessToken);
        $fireob_paymentUuid = $FireOB->payment_list_by_code($result->fireob_payment_code, $accessToken);

        $order = new WC_Order($result->order_id);
        if ($fireob_ps == 'ACTIVE') {
            $order->update_status($FireOB->order_status); // order note is optional, if you want to  add a note to order
            update_post_meta($result->order_id, '_fireob_paymentUuid',  $fireob_paymentUuid);
        } else if ($fireob_ps == 'EXPIRED' or $fireob_ps == 'CLOSED') {
            $order->update_status('failed'); // order note is optional, if you want to  add a note to order

        }
    } //end of loop


} //end of function


// Unschedule event upon plugin deactivation
function fireob_cronstarter_deactivate()
{
    // find out when the last event was scheduled
    $timestamp = wp_next_scheduled('fireob_set_order_status_cron_act');
    // unschedule previous event if any
    wp_unschedule_event($timestamp, 'fireob_set_order_status_cron_act');

    $timestamp = wp_next_scheduled('fireob_set_order_status_code_cron_act');
    // unschedule previous event if any
    wp_unschedule_event($timestamp, 'fireob_set_order_status_code_cron_act');
}
register_deactivation_hook(__FILE__, 'fireob_cronstarter_deactivate');
