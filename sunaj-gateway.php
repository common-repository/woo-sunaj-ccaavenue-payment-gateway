<?php
/*
 * Plugin Name: WooCommerce Sunaj Ccavenue Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/woo-sunaj-ccaavenue-payment-gateway/
 * Description: WooCommerce Ccavenue Payment Gateway.
 * Author: Dev @ Sunaj
 * Author URI: 
 * Version: 1.0
 *
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Hey you can/t access this page..' );
 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'sunaj_add_gateway_class' );
function sunaj_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Sunaj_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'sunaj_init_gateway_class' );
function sunaj_init_gateway_class() {
 
	class WC_Sunaj_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
			$this->id = 'sunaj'; // payment gateway plugin ID
			$this->icon = plugins_url('images/ccAvenue_logo.png', __FILE__); // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'Sunaj Payment Gateway';
			$this->method_description = 'Sunaj payment gateway redirects customers to Ccavenue to enter their payment information.'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but this is with simple payments
			
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			// $this->testmode = 'yes' === $this->get_option( 'testmode' );
			
			// $this->merchant_id = $this->testmode ? $this->get_option( 'test_merchant_id' ) : $this->get_option( 'merchant_id' );
			// $this->working_key = $this->testmode ? $this->get_option( 'test_working_key' ) : $this->get_option( 'working_key' );


			$this->merchant_id =  $this->get_option( 'merchant_id' );
			$this->working_key =  $this->get_option( 'working_key' );

			$this->minimum_payment_info = 'yes' === $this->get_option( 'minimum_payment_info' );

		    $this->liveurl    = 'https://www.ccavenue.com/shopzone/cc_details.jsp?';
            $this->notify_url = home_url('/wc-api/WC_Sunaj_Cc');

         
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
			// You can also register a webhook here
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

			add_action( 'woocommerce_api_wc_sunaj_cc', array( $this, 'check_ccavenue_response' ) );
			add_action('woocommerce_receipt_ccavenue', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_ccavenue',array($this, 'thankyou_page'));

 
 		}

 		 /**
         * Receipt Page
         **/
        function receipt_page($order){
			
            //echo $this->generate_ccavenue_form($order);
        }
	 	/*** Thankyou Page**/
        function thankyou_page($order){
          if (!empty($this->instructions))
        	echo wpautop( wptexturize( $this->instructions ) );
		
        }

		/**
		* Check for valid CCAvenue server callback
		**/
		function check_ccavenue_response(){
			global $woocommerce;

			$msg['class']   = 'error';
			$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";


			$order = NULL;
			$response = $_REQUEST;
			$order_id = 0;
			$order_status = NULL;
			$bank_ref_no = NULL;
			$veriChecksum = FALSE;
			if(isset($_REQUEST['encResp']))
			{
				$encResponse = $_REQUEST["encResp"];         
				$rcvdString  = $this->decrypt($encResponse,$this->working_key);      

				$decryptValues = array();

				parse_str( $rcvdString, $decryptValues );
				if(!empty($decryptValues) && is_array($decryptValues))
				{
					$veriChecksum = TRUE;
					if(isset($decryptValues['order_id']))
					{
						$order_id_time = $decryptValues['order_id'];
						$order_id = explode('_', $order_id_time);
						$order_id = (int)$order_id[0];
					}
					
					if(isset($decryptValues['order_status']))
					{
						$order_status = $decryptValues['order_status'];
						switch ($decryptValues['order_status']) {
							case 'Success':
								$order_status = 'Y';
								break;
							case 'Aborted':
								$order_status = 'N';
								break;
							case 'Failure':
								$order_status = 'N';
								break;
							
						}
					}
					if(isset($decryptValues['bank_ref_no']))
					{
						$bank_ref_no = $decryptValues['bank_ref_no'];
					}
					
				}
				
			}
			if( isset($_POST['AuthDesc']) && !empty($response) && is_array($response))
			{ 
				$AuthDesc = NULL;
				$MerchantId = NULL;
				$OrderId = NULL;
				$Amount = NULL;
				$Checksum = NULL;
				if(isset( $response['AuthDesc']))
				{ 
					$AuthDesc = strtoupper($response['AuthDesc']);
					
					$order_status = $AuthDesc;
				}
				if(isset( $response['Merchant_Id']))
				{ 
					$MerchantId = $response['Merchant_Id'];
				}
				if(isset( $response['Order_Id']))
				{ 
					$OrderId = $response['Order_Id'];
					
					$order_id = explode('_', $OrderId);
					
					$order_id = (int)$order_id[0];
				}
				if(isset( $response['Amount']))
				{ 
					$Amount = $response['Amount'];
				}
				if(isset( $response['Checksum']))
				{ 
					$Checksum = $response['Checksum'];
				}

				if(!empty($AuthDesc) && !empty($MerchantId) && !empty($OrderId) && !empty($Amount) && !empty($Checksum))
				{
					$rcvdString = $MerchantId.'|'.$OrderId.'|'.$Amount.'|'.$AuthDesc.'|'.$this->working_key;
					$veriChecksum = $this->verifyChecksum($this->genchecksum($rcvdString), $Checksum);

					
				}

				
			}

			
			if( $veriChecksum === TRUE && $order_id > 0 && !empty($order_status) )
			{ 
				try
				{
					$order = new WC_Order($order_id);
					$transauthorised = false;
					
					if($order->get_status() !== 'completed' )
					{   
						if( $order_status === 'Y' )
						{  
							$transauthorised = true;
							$msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
							$msg['class'] = 'success';

							if($order->get_status() != 'processing'){ 
								$order->payment_complete();
								$order->add_order_note('CCAvenue payment successful<br/>Bank Ref Number: '.$bank_ref_no);
								$woocommerce->cart -> empty_cart();
							}
						}
						else if( $order_status === 'B' )
						{  
							$msg['message'] = "Thank you for shopping with us. We will keep you posted regarding the status of your order through e-mail";
							$msg['class'] = 'success';
							
							if($order->get_status() != 'processing'){ 
								
								$order->add_order_note('CCAvenue payment successful<br/>Bank Ref Number: '.$bank_ref_no);
								$woocommerce->cart -> empty_cart();
							} 
						}
						else if( $order_status === 'N' )
						{
							$msg['class'] = 'error';
							$msg['message'] = "Thank you for shopping with us. However,the transaction has been declined.";
							
						}

						if($transauthorised == false){
							
							$order->update_status('failed');
							$order->add_order_note('Failed');
							$order->add_order_note($msg['message']);
						}
					}


				}
				catch(Exception $e)
				{
					$msg['class'] = 'error';
					$msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
					
				}

			}

			

			if ( function_exists( 'wc_add_notice' ) )
			{
				wc_add_notice( $msg['message'], $msg['class'] );
				
			}
			else 
			{
				if($msg['class']=='success'){
					$woocommerce->add_message( $msg['message']);
					
				}else{
					$woocommerce->add_error( $msg['message'] );
					

				}
				$woocommerce->set_messages();
				
			}
			
			$redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
			//$redirect_url = $this->get_return_url( $order );
			wp_redirect( $redirect_url );
			
			exit;

		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Ccavenue',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				// 'testmode' => array(
				// 	'title'       => 'Test mode',
				// 	'label'       => 'Enable Test Mode',
				// 	'type'        => 'checkbox',
				// 	'description' => 'Place the payment gateway in test mode using test API keys.',
				// 	'default'     => 'yes',
				// 	'desc_tip'    => true,
				// ),
				// 'test_merchant_id' => array(
				// 	'title'       => 'Test Merchant Id',
				// 	'type'        => 'text'
				// ),
				// 'test_working_key' => array(
				// 	'title'       => 'Test Working Key',
				// 	'type'        => 'text',
				// ),
				'merchant_id' => array(
					'title'       => 'Live Merchant Id',
					'type'        => 'text'
				),
				'working_key' => array(
					'title'       => 'Live Working Key',
					'type'        => 'text'
				),
				'minimum_payment_info' => array(
					'title'       => 'Minimum Data',
					'label'       => 'Minimum Payment Information',
					'type'        => 'checkbox',
					'description' => 'Sharing minimum payment information with Ccavenue.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
			);
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
		//...
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
 
		 //...
 
	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
		 //...
 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 	

 			global $woocommerce;
            $order         = new WC_Order($order_id);
            $order_id      = $order_id.'_'.time();
            $ccavenue_args = array(
                'Merchant_Id' => $this->merchant_id,
                'Amount' => $order->get_total(),
                'Order_Id' => $order_id,
                'Redirect_Url' => $this->notify_url,
                'Cancel_url' => $this->notify_url,
            );

            if(!$this->minimum_payment_info)
            {
            	

            	$billing_country = wc()->countries->countries[$order->get_billing_country()];
            	$shipping_country = wc()->countries->countries[$order->get_shipping_country()];

            	if( strpos($billing_country, '(') !== false)
            	{
            		$billing_country = substr($billing_country, 0, strpos($billing_country, '('));
            		$billing_country = trim($billing_country);
            	}

            	if( strpos($shipping_country, '(') !== false)
            	{
            		$shipping_country = substr($shipping_country, 0, strpos($shipping_country, '('));
            		$shipping_country = trim($shipping_country);
            	}

            	$ccavenue_args = array_merge( $ccavenue_args, [
            		'billing_cust_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
	                'billing_cust_address' => trim($order->get_billing_address_1() . ',' .$order->get_billing_address_2()),
	                'billing_cust_country' => $billing_country,
	                'billing_cust_state' => $order->get_billing_state(),
	                'billing_cust_city' => $order->get_billing_city(),
	                'billing_zip_code' => $order->get_billing_postcode(),
	                'billing_cust_tel' => $order->get_billing_phone(),
	                'billing_cust_email' => $order->get_billing_email(),
	                'delivery_cust_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
	                'delivery_cust_address' => trim($order->get_shipping_address_1(). ','. $order->get_shipping_address_2()),
	                'delivery_cust_country' => $shipping_country,
	                'delivery_cust_state' => $order->get_shipping_state(),
	                'delivery_cust_tel' => $order->get_billing_phone(),
	                'delivery_cust_city' => $order->get_shipping_city(),
	                'delivery_zip_code' => $order->get_shipping_postcode(),
            	]);
            }

            $ccavenue_args['language'] = 'EN';
            $ccavenue_args['currency'] = get_woocommerce_currency();
            $ccavenue_args['Checksum'] = $this->getchecksum($this->merchant_id, $order->order_total, $order_id, urlencode($this->notify_url), $this->working_key);

			$ccavenue_args['TxnType']        = 'A';
			$ccavenue_args['ActionID']       = 'TXN';

			

			$merchant_data   = http_build_query($ccavenue_args);
			$encrypted_data  = $this->encrypt($merchant_data, $this->working_key);

			

			$ccavenue_args_array = [
				'encRequest' => $encrypted_data,
				'Merchant_Id' => $this->merchant_id
			];

			$redirect_url = $this->liveurl .'' . http_build_query( $ccavenue_args_array, '', '&' );



			$res = array(
			    'result' => 'success',
			    'redirect' => $redirect_url
			);

			
 			
 			return $res;
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
 
		 //...
 
	 	}

	 	function encrypt($plainText,$key)
		{
			$key = $this->hextobin(md5($key));
			$iv = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
			$openMode = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
			$encryptedText = bin2hex($openMode);
			return $encryptedText;
		}

		function decrypt($encryptedText,$key)
		{
		  $key = $this->hextobin(md5($key));
		  $iv = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
		  $encryptedText = hextobin($encryptedText);
		  $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
		  return $decryptedText;

		}

	 	function encrypt_old($plainText, $key)
	    {
	        $secretKey  = $this->hextobin(md5($key));
	        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
	        $openMode   = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
	        $blockSize  = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
	        $plainPad   = $this->pkcs5_pad($plainText, $blockSize);
	        if (mcrypt_generic_init($openMode, $secretKey, $initVector) != -1) {
	            $encryptedText = mcrypt_generic($openMode, $plainPad);
	            mcrypt_generic_deinit($openMode);
	        }
	        return bin2hex($encryptedText);
	    }

	    function decrypt_old($encryptedText, $key)
	    {
	        $secretKey     = $this->hextobin(md5($key));
	        $initVector    = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
	        $encryptedText = $this->hextobin($encryptedText);
	        $openMode      = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
	        mcrypt_generic_init($openMode, $secretKey, $initVector);
	        $decryptedText = mdecrypt_generic($openMode, $encryptedText);
	        $decryptedText = rtrim($decryptedText, "\0");
	        mcrypt_generic_deinit($openMode);
	        return $decryptedText;
	    }

		    function pkcs5_pad($plainText, $blockSize)
		    {
		        $pad = $blockSize - (strlen($plainText) % $blockSize);
		        return $plainText . str_repeat(chr($pad), $pad);
		    }

		    function hextobin($hexString)
		    {
		        $length    = strlen($hexString);
		        $binString = "";
		        $count     = 0;
		        while ($count < $length) {
		            $subString    = substr($hexString, $count, 2);
		            $packedString = pack("H*", $subString);
		            if ($count == 0) {
		                $binString = $packedString;
		            }
		            
		            else {
		                $binString .= $packedString;
		            }
		            
		            $count += 2;
		        }
		        return $binString;
		    }

		    function getchecksum($MerchantId,$Amount,$OrderId ,$URL,$WorkingKey)
		    {
		        $str ="$MerchantId|$OrderId|$Amount|$URL|$WorkingKey";
		        $adler = 1;
		        $adler = $this->adler32($adler,$str);
		        return $adler;
		    }

		    function genchecksum($str)
		    {
		        $adler = 1;
		        $adler = $this->adler32($adler,$str);
		        return $adler;
		    }

		    function verifyChecksum($getCheck, $avnChecksum)
		    {
		        $verify=false;
		        if($getCheck==$avnChecksum) $verify=true;
		        return $verify;
		    }

		    function adler32($adler , $str)
		    {
		        $BASE =  65521 ;
		        $s1 = $adler & 0xffff ;
		        $s2 = ($adler >> 16) & 0xffff;
		        for($i = 0 ; $i < strlen($str) ; $i++)
		        {
		            $s1 = ($s1 + Ord($str[$i])) % $BASE ;
		            $s2 = ($s2 + $s1) % $BASE ;
		        }
		        return $this->leftshift($s2 , 16) + $s1;
		    }

		    function leftshift($str , $num)
		    {

		        $str = DecBin($str);

		        for( $i = 0 ; $i < (64 - strlen($str)) ; $i++)
		            $str = "0".$str ;

		        for($i = 0 ; $i < $num ; $i++) 
		        {
		            $str = $str."0";
		            $str = substr($str , 1 ) ;
		            //echo "str : $str <BR>";
		        }
		        return $this->cdec($str) ;
		    }

		    function cdec($num)
		    {
		        $dec=0;
		        for ($n = 0 ; $n < strlen($num) ; $n++)
		        {
		           $temp = $num[$n] ;
		           $dec =  $dec + $temp*pow(2 , strlen($num) - $n - 1);
		        }

		        return $dec;
		    }
 	}
}