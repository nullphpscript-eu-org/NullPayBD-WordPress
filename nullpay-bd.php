<?php
/*
 * Plugin Name: Nullpay BD
 * Plugin URI: https://wordpress.org/plugins/
 * Description: This plugin allows your customers to pay with Bkash, Nagad, Rocket and all BD gateways via nullpaybd
 * Author: Developar Nullphpscript
 * Author URI: https://pay.nullphpscript.eu.org
 * Version: 1.0.0
 * Requries at least: 5.2
 * Requries PHP: 7.2
  License: GPL v2 or later
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nullpaybd
 */
 
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action( 'plugins_loaded', 'nullpaybd_init_gateway_class' );

function nullpaybd_init_gateway_class() {
    global $nullpaybd_gateway_instance;

    class WC_nullpaybd_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            
            $this->id = 'nullpaybd';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __('nullpaybd','nullpaybd-gateway');
            $this->method_description = __('Pay With nullpaybd','nullpaybd-gateway');

            $this->supports = array(
                'products'
            );

            $this->init_form_fields();

            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'handle_webhook'));
            
            if(isset($_GET['success1'])){
                $order = new WC_Order($_GET['success1']);
                $this->update_order_status(wc_get_order($_GET['success1']));
                wp_redirect($this->get_return_url($order)); 
                exit;
            }
            
        
        }

        

        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable nullpaybd',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'NullPayBD Gateway',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay via Nullpaybd.',
                ),
                'apikeys' => array(
                    'title'       => 'Enter Api Key',
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '###################',
                    'desc_tip'    => true,
                ),
                'secretkey' => array(
                    'title'       => 'Enter Secret Key',
                    'type'        => 'text',
                    'description' => '',
                    'default'     => '#############',
                    'desc_tip'    => true,
                ),
                'hostname' => array(
                    'title'       => 'Enter Host Name',
                    'type'        => 'text',
                    'description' => '',
                    'default'     => 'pay.nullphpscript.eu.org',
                    'desc_tip'    => true,
                ),
                'currency_rate' => array(
                    'title'       => 'Enter USD Rate',
                    'type'        => 'number',
                    'description' => '',
                    'default'     => '85',
                    'desc_tip'    => true,
                ),
                'is_digital' => array(
                    'title'       => 'Enable/Disable Digital product',
                    'label'       => 'Enable Digital product',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'payment_site' => array(
                    'title'       => 'Enter payment url',
                    'type'        => 'text',
                    'description' => '',
                    'default'     => 'https://secure-pay.nullphpscript.eu.org',
                    'desc_tip'    => '',
                ),
                
            );
        }

        public function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            
            $current_user = wp_get_current_user();

            foreach ( $order->get_items() as $item_id => $item ) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $product = $item->get_product();
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $subtotal = $item->get_subtotal();
                $total = $item->get_total();
                $tax = $item->get_subtotal_tax();
                $tax_class = $item->get_tax_class();
                $tax_status = $item->get_tax_status();
                $allmeta = $item->get_meta_data();
                $somemeta = $item->get_meta( '_whatever', true );
                $item_type = $item->get_type();
            }

            $subtotal                = WC()->cart->subtotal;
            $shipping_total          = WC()->cart->get_shipping_total();
            $fees                    = WC()->cart->get_fee_total();
            $discount_excl_tax_total = WC()->cart->get_cart_discount_total();
            $discount_tax_total      = WC()->cart->get_cart_discount_tax_total();
                        
            $discount_total          = $discount_excl_tax_total + $discount_tax_total;
            $total = $subtotal + $shipping_total + $fees - $discount_total;

            if($order->get_currency() == 'USD'){
                $total = $total * $this->get_option('currency_rate');
            }

            if ($order->get_status() != 'completed') {
                $order->update_status('pending', __('Customer is being redirected to nullpaybd', 'nullpaybd'));
            }
            $redirect_url = $this->get_return_url($order);
            
            $data   = array(
                "cus_name"          => $current_user->user_firstname,
                "cus_email"         => $current_user->user_email,
                "amount"            => $total,
                "success_url"       => wc_get_page_permalink( 'checkout' ).'?success1='.$order->get_id(),
                "cancel_url"        => wc_get_page_permalink( 'checkout' )
            );
            $header   = array(
                "api"               => $this->get_option('apikeys'),
                "secret"            => $this->get_option('secretkey'),
                "position"          => $this->get_option('hostname'),
                "url"               => $this->get_option('payment_site').'/request/payment/woocommerce',
            );

            $response = $this->create_payment($data, $header);
            $data = json_decode($response, true);

            return array(
                'result'    => 'success',
                'redirect'  => $data['payment_url']
            );
        }

        public function create_payment($data = "",$header='') {
            
            $headers = array(
                'Content-Type: application/x-www-form-urlencoded',
                'app-key: ' . $header['api'],
                'secret-key: ' . $header['secret'],
                'host-name: ' . $header['position'],
            );
            $url = $header['url'];
            $curl = curl_init();
            $data = http_build_query($data);
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_VERBOSE => true
            ));
             
            $response = curl_exec($curl);
            curl_close($curl);
            
            return $response;
        } 
        public function update_order_status($order){
            
            $transactionId = $_GET['transactionId'];
            $data   = array(
                "transaction_id"          => $transactionId,
            );
            $header   = array(
                "api"               => $this->get_option('apikeys'),
                "secret"            => $this->get_option('secretkey'),
                "position"          => $this->get_option('hostname'),
                "url"               => $this->get_option('payment_site').'/request/payment/verify',
            );

            
            $response = $this->create_payment($data,$header);
            $data = json_decode($response, true);
            

            if ($order->get_status() != 'completed') {
                if ($data['status'] == 1) {
                    $transaction_id = $data['transaction_id'];
                    $amount = $data['amount'];
                    $sender_number = $data['cus_email'];
                    $payment_method = 'superpay';
                    if ($this->get_option('is_digital') === 'yes') {
                        $order->update_status('completed', __("NullPayBD payment was successfully completed. Payment Method: {$payment_method}, Amount: {$amount}, Transaction ID: {$transaction_id}, Sender Number: {$sender_number}", 'nullpaybd-gateway'));
                        // Reduce stock levels
                        $order->reduce_order_stock();
                        $order->add_order_note( __( 'Payment completed via PGW URL checkout. trx id: '.$transaction_id, 'nullpaybd-gateway' ) );
                        $order->payment_complete();
                    } else {
                        $order->update_status('processing', __("NullPayBD payment was successfully processed. Payment Method: {$payment_method}, Amount: {$amount}, Transaction ID: {$transaction_id}, Sender Number: {$sender_number}", 'nullpaybd-gateway'));
                        // Reduce stock levels
                        $order->reduce_order_stock();
                        $order->payment_complete();
                    }
                    return true;
                } elseif($_GET['p_type']=="bank") {
                    $order->update_status('on-hold', __('NullPayBD payment was successfully on-hold.Bank Transaction is successful. Please check it manually and inform the Site owner.', 'nullpaybd-gateway'));
                    return true;
                }else{
                    $order->update_status('on-hold', __('NullPayBD payment was successfully on-hold. Transaction id not found. Please check it manually.', 'nullpaybd-gateway'));
                    return true;
                }
            }

        }      
        
    }
    function nullpaybd_add_gateway_class( $gateways ) {
        $gateways[] = 'WC_nullpaybd_Gateway';
        return $gateways;
    }
    add_filter( 'woocommerce_payment_gateways', 'nullpaybd_add_gateway_class' );
}
