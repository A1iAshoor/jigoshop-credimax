<?php
/*
Plugin Name: Jigoshop CrediMax Payment Gateway
Plugin URI: 
Description: This plugin extends the Jigoshop payment gateways to add in Credimax Payment Gateway.
Version: 1.0.0
Author: Ali Ashoor
Author URI: http://uskistudios.com
*/

/*  Copyright 2014 Ali Ashoor (email: info@uskistudios.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'JIGOSHOP_CREDIMAX_PATH', plugin_dir_path( __FILE__ ) );
define( 'JIGOSHOP_CREDIMAX_URL', plugin_dir_url( __FILE__ ) );

/* Add credimax payment class to Jigoshop
------------------------------------------------------------ */
function jigoshop_credimax_payment_gateway() {
	
		if ( !class_exists( 'jigoshop_payment_gateway' ) ) return; // if the Jigoshop payment gateway class is not available, do nothing
	
		class jigoshop_credimax_gateway extends jigoshop_payment_gateway {
		
			private static $request_url;
			private static $status;
			private $allowed_currency = array('USD');

			const CREDIMAX_LIVE_URL = 'https://migs.mastercard.com.au/vpcpay'; // Live phase.
			const CREDIMAX_SANDBOX_URL = 'https://migs.mastercard.com.au/vpcpay'; // Demo and testing phase.

			public function __construct() {

				parent::__construct();

				$options = Jigoshop_Base::get_options();

		    	$this->id				= 'credimax_gateway';
		    	$this->icon 			= apply_filters( 'jigoshop_credimax_icon', JIGOSHOP_CREDIMAX_URL . 'images/icon.png' );
		    	$this->has_fields 		= false;

				self::$request_url  	= $options->get_option('jigoshop_credimax_gateway_mode') == 'no' ? self::CREDIMAX_LIVE_URL : self::CREDIMAX_SANDBOX_URL;
				self::$status 			= $options->get_option('jigoshop_credimax_gateway_mode') == 'no' ? '0' : '1';
				$this->enabled			= $options->get_option( 'jigoshop_credimax_gateway_enabled' );
				$this->title 			= $options->get_option( 'jigoshop_credimax_gateway_title' );
				$this->description 		= $options->get_option( 'jigoshop_credimax_gateway_description' );
				$this->merchant_id		= $options->get_option( 'jigoshop_credimax_gateway_merchant_id' );
				$this->access_code		= $options->get_option( 'jigoshop_credimax_gateway_access_code' );
				$this->secure_secret	= $options->get_option( 'jigoshop_credimax_gateway_secure_secret' );

				add_option( 'jigoshop_credimax_gateway_enabled', 'yes' );
				add_option( 'jigoshop_credimax_gateway_title', 'CrediMax' );
				add_option( 'jigoshop_credimax_gateway_description', 'Pay with CrediMax; Pay with your credit card through CrediMax payment gateway.' );

				add_action( 'init', array( &$this, 'check_credimax_response' ) );
				add_action( 'jigoshop_update_options', array( &$this, 'process_admin_options' ) );
				add_action( 'valid_credimax_request', array( &$this, 'successful_request' ), 10, 2 );
				add_action( 'receipt_credimax_gateway', array( &$this, 'receipt_page' ) );
				// add_action( 'thankyou_credimax_gateway', array( &$this, 'thankyou_page' ) );
			}

		    protected function get_default_options() {

				$defaults = array();

				// Define the Section name for the Jigoshop_Options
				$defaults[] = array(
					'name' => __('CrediMax', 'jigoshop'),
					'type' => 'title',
					'desc' => __('Allows CrediMax payments. Allows you to make test purchases without having to use the sandbox area of a payment gateway. Quite useful for demonstrating to clients and for testing order emails and the \'success\' pages etc.', 'jigoshop')
				);

				// List each option in order of appearance with details
				$defaults[] = array(
					'name'		=> __('Enable CrediMax Payment','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> '',
					'id' 		=> 'jigoshop_credimax_gateway_enabled',
					'std' 		=> 'yes',
					'type' 		=> 'checkbox',
					'choices'	=> array(
						'no'			=> __('No', 'jigoshop'),
						'yes'			=> __('Yes', 'jigoshop')
					)
				);

				$defaults[] = array(
					'name'		=> __('Method Title','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('This controls the title which the user sees during checkout.','jigoshop'),
					'id' 		=> 'jigoshop_credimax_gateway_title',
					'std' 		=> __('CrediMax Payment','jigoshop'),
					'type' 		=> 'text'
				);

				$defaults[] = array(
					'name'		=> __('Customer Message','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('Let the customer know the payee and where they should be sending the cheque to and that their order won\'t be shipping until you receive it.','jigoshop'),
					'id' 		=> 'jigoshop_credimax_gateway_description',
					'std' 		=> __('Pay with CrediMax; Pay with your credit card through CrediMax payment gateway.', 'jigoshop'),
					'type' 		=> 'longtext'
				);

				$defaults[] = array(
					'name'		=> __('Merchant ID','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('This field is for your CrediMax Merchant ID.','jigoshop'),
					'id' 		=> 'jigoshop_credimax_gateway_merchant_id',
					'std' 		=> '',
					'type' 		=> 'text'
				);

				$defaults[] = array(
					'name'		=> __('Access Code','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('This field is for your CrediMax Access Code.','jigoshop'),
					'id' 		=> 'jigoshop_credimax_gateway_access_code',
					'std' 		=> '',
					'type' 		=> 'text'
				);

				$defaults[] = array(
					'name'		=> __('Secure Secret Code','jigoshop'),
					'desc' 		=> '',
					'tip' 		=> __('This field is for your CrediMax Secure Secret Code.','jigoshop'),
					'id' 		=> 'jigoshop_credimax_gateway_secure_secret',
					'std' 		=> '',
					'type' 		=> 'longtext'
				);

				$defaults[] = array(
					'name'		=> __('Enable Sandbox','jigoshop'),
					'desc' 		=> __('Turn on to enable the CrediMax sandbox for testing.', 'jigoshop'),
					'tip' 		=> '',
					'id' 		=> 'jigoshop_credimax_gateway_mode',
					'std' 		=> 'no',
					'type' 		=> 'checkbox',
					'choices'	=> array(
						'no'		=> __('No', 'jigoshop'),
						'yes'		=> __('Yes', 'jigoshop')
					)
				);

				return $defaults;
			}

			/* Display description for payment fields and thank you page
			------------------------------------------------------------ */
			function payment_fields() {
				if ( $this->description )
					echo wpautop( wptexturize( $this->description ) );
			}
			

			/* Update options in the database upon save
			------------------------------------------------------------ */
			public function process_admin_options() {
				if( isset( $_POST['jigoshop_credimax_gateway_enabled'] ) ) update_option( 'jigoshop_credimax_gateway_enabled', jigowatt_clean( $_POST['jigoshop_credimax_gateway_enabled'] ) ); else @delete_option( 'jigoshop_credimax_gateway_enabled' );
				if( isset( $_POST['jigoshop_credimax_gateway_title'] ) ) update_option( 'jigoshop_credimax_gateway_title', jigowatt_clean( $_POST['jigoshop_credimax_gateway_title'] ) ); else @delete_option( 'jigoshop_credimax_gateway_title' );
				if( isset( $_POST['jigoshop_credimax_gateway_description'] ) ) update_option( 'jigoshop_credimax_gateway_description', 	jigowatt_clean( $_POST['jigoshop_credimax_gateway_description'] ) ); else @delete_option( 'jigoshop_credimax_gateway_description' );
				if( isset( $_POST['jigoshop_credimax_gateway_merchant_id'] ) ) update_option( 'jigoshop_credimax_gateway_merchant_id', 	jigowatt_clean( $_POST['jigoshop_credimax_gateway_merchant_id'] ) ); else @delete_option( 'jigoshop_credimax_gateway_merchant_id' );
				if( isset( $_POST['jigoshop_credimax_gateway_access_code'] ) ) update_option( 'jigoshop_credimax_gateway_access_code', 	jigowatt_clean( $_POST['jigoshop_credimax_gateway_access_code'] ) ); else @delete_option( 'jigoshop_credimax_gateway_access_code' );
				if( isset( $_POST['jigoshop_credimax_gateway_secure_secret'] ) ) update_option( 'jigoshop_credimax_gateway_secure_secret', 	jigowatt_clean( $_POST['jigoshop_credimax_gateway_secure_secret'] ) ); else @delete_option( 'jigoshop_credimax_gateway_secure_secret' );
			}

			/* Generate the futurepay payment iframe
			------------------------------------------------------------ */
			protected function call_credimax( $order_id ) {

				// Get the order
				$order = new jigoshop_order( $order_id );

				$data = array(
					'reference' 				=> $order_id.'-'.uniqid(),
					'email' 					=> $order->billing_email,
					'first_name' 				=> $order->billing_first_name,
					'last_name' 				=> $order->billing_last_name,
					'company' 					=> $order->billing_company,
					'address_line_1' 			=> $order->billing_address_1,
					'address_line_2' 			=> $order->billing_address_2,
					'city' 						=> $order->billing_city,
					'state' 					=> $order->billing_state,
					'country' 					=> $order->billing_country,
					'zip' 						=> $order->billing_postcode,
					'phone' 					=> $order->billing_phone,
					'shipping_address_line_1' 	=> $order->shipping_address_1,
					'shipping_address_line_2' 	=> $order->shipping_address_2,
					'shipping_city' 			=> $order->shipping_city,
					'shipping_state' 			=> $order->shipping_state,
					'shipping_country' 			=> $order->shipping_country,
					'shipping_zip' 				=> $order->shipping_postcode,
					'shipping_date' 			=> date('Y/m/d g:i:s'),
				);
				
				// All product titles will be comma delimited with their quantities
				$item_names = array();
				if ( sizeof( $order->items ) > 0 ) foreach ( $order->items as $item ) {
					$_product = $order->get_product_from_item( $item );
					$title = $_product->get_title();
					// if variation, insert variation details into product title
					if ( $_product instanceof jigoshop_product_variation ) {
						$title .= ' (' . jigoshop_get_formatted_variation( $_product, $item['variation'], true) . ')';
					}
					$item_names[] = $item['qty'] . ' x ' . $title;
				}

				// Now add the one line item to the necessary product field arrays
				$data['price'] = $order->order_total; // futurepay only needs final order amount
				$data['description'][] = sprintf( __('Order %s' , 'jigoshop'), $order->get_order_number() ) . ' = ' . implode(', ', $item_names);

				// Define Constants
				// ----------------
				// This is secret for encoding the MD5 hash
				// This secret will vary from merchant to merchant
				// To not create a secure hash, let SECURE_SECRET be an empty string - ""

				// $SECURE_SECRET = "secure-hash-secret";
				$SECURE_SECRET = $this->secure_secret;

				// filter redirect page
				$checkout_redirect = apply_filters( 'jigoshop_get_checkout_redirect_page_id', jigoshop_get_page_id('thanks') );

				// Post data
				// $_POST["vpc_OrderInfo"] = implode(', ', $data['description']);
				// file_lm(self::$status);

				if(self::$status == '0'){
					$_POST["vpc_Amount"] 			= $data['price'];
				} else {
					$_POST["vpc_Amount"] 			= 0.01;
				}

				$_POST["virtualPaymentClientURL"] 	= self::$request_url;
				$_POST["vpc_Version"] 				= '1';
				$_POST["vpc_Command"] 				= 'pay';
				$_POST["vpc_AccessCode"] 			= $this->access_code;
				$_POST["vpc_MerchTxnRef"] 			= date('YmdHis');
				$_POST["vpc_Merchant"] 				= $this->merchant_id;
				$_POST["vpc_Amount"] 				= intval($_POST["vpc_Amount"]*100);
				$_POST["vpc_Locale"] 				= 'en';
				$_POST["vpc_ReturnURL"] 			= add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( $checkout_redirect ) ) );

				// add the start of the vpcURL querystring parameters
				$vpcURL = $_POST["virtualPaymentClientURL"] . "?";

				// Remove the Virtual Payment Client URL from the parameter hash as we 
				// do not want to send these fields to the Virtual Payment Client.
				unset($_POST["virtualPaymentClientURL"]);

				// The URL link for the receipt to do another transaction.
				// Note: This is ONLY used for this example and is not required for 
				// production code. You would hard code your own URL into your application.

				// Get and URL Encode the AgainLink. Add the AgainLink to the array
				// Shows how a user field (such as application SessionIDs) could be added
				//$_POST['AgainLink']=urlencode($HTTP_REFERER);

				// Create the request to the Virtual Payment Client which is a URL encoded GET
				// request. Since we are looping through all the data we may as well sort it in
				// case we want to create a secure hash and add it to the VPC data if the
				// merchant secret has been provided.
				$md5HashData = $SECURE_SECRET;
				ksort ($_POST);

				// // set a parameter to show the first pair in the URL
				$appendAmp = 0;

				foreach($_POST as $key => $value) {

				    // create the md5 input and URL leaving out any fields that have no value
				    if (strlen($value) > 0) {
				        
				        // this ensures the first paramter of the URL is preceded by the '?' char
				        if ($appendAmp == 0) {
				            $vpcURL .= urlencode($key) . '=' . urlencode($value);
				            $appendAmp = 1;
				        } else {
				            $vpcURL .= '&' . urlencode($key) . "=" . urlencode($value);
				        }
				        $md5HashData .= $value;
				    }
				}

				// Create the secure hash and append it to the Virtual Payment Client Data if
				// the merchant secret has been provided.
				if (strlen($SECURE_SECRET) > 0) {
				    $vpcURL .= "&vpc_SecureHash=" . strtoupper(md5($md5HashData));
				}

				$_POST['vpc_SecureHash'] = strtoupper(md5($md5HashData));

				// FINISH TRANSACTION - Redirect the customers using the Digital Order
				// ===================================================================
				// header("Location: ".$vpcURL);

				// $args_array = array();

				// foreach ($_POST as $key => $value) {
				// $args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
				// }

				return '<form action="'.$vpcURL.'" method="post" id="credimax_payment_form">
						<input type="submit" class="button-alt" id="submit_credimax_payment_form" value="Pay via CrediMax" />
						<script type="text/javascript">
							(function($){
								$(document).ready(function(){
									$("#submit_credimax_payment_form").click();
								})
							})(jQuery);
						</script>
						</form>';

			}

			/* Process order 
			------------------------------------------------------------ */
			function process_payment( $order_id ) {
				$order = new jigoshop_order( $order_id );
				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink(jigoshop_get_page_id('pay')) ))
				);
			}

			/* Receipt page
			------------------------------------------------------------ */
			function receipt_page( $order ) {
				echo '<p>'.__('Thank you for your order, please click the button below to pay with CrediMax.', 'jigoshop').'</p>';
				echo $this->call_credimax( $order );
			}

			/* Check for CrediMax Response
			------------------------------------------------------------ */
			public function check_credimax_response() {

				if( isset( $_GET['vpc_TxnResponseCode'] ) ) {

					// Define Constants
					// ----------------
					// This is secret for encoding the MD5 hash
					// This secret will vary from merchant to merchant
					// To not create a secure hash, let SECURE_SECRET be an empty string - ""
					// $SECURE_SECRET = "secure-hash-secret";
					$SECURE_SECRET = $this->secure_secret;

					// get and remove the vpc_TxnResponseCode code from the response fields as we
					// do not want to include this field in the hash calculation
					$vpc_Txn_Secure_Hash = $_GET["vpc_SecureHash"];
					unset($_GET["vpc_SecureHash"]);

					// set a flag to indicate if hash has been validated
					$errorExists = false;

					if (strlen($SECURE_SECRET) > 0 && $_GET["vpc_TxnResponseCode"] != "7" && $_GET["vpc_TxnResponseCode"] != "No Value Returned") {

					    $md5HashData = $SECURE_SECRET;

					    // sort all the incoming vpc response fields and leave out any with no value
					    foreach($_GET as $key => $value) {
					        if ($key != "vpc_SecureHash" or strlen($value) > 0) {
					        	if($key != "order" && $key != "key"){
					            	$md5HashData .= $value;
					        	}
					        }
					    }
					    
					    // Validate the Secure Hash (remember MD5 hashes are not case sensitive)
						// This is just one way of displaying the result of checking the hash.
						// In production, you would work out your own way of presenting the result.
						// The hash check is all about detecting if the data has changed in transit.
					    if (strtoupper($vpc_Txn_Secure_Hash) == strtoupper(md5($md5HashData))) {
					        // Secure Hash validation succeeded, add a data field to be displayed
					        // later.
					        $hashValidated = "CORRECT";
					    } else {
					        // Secure Hash validation failed, add a data field to be displayed
					        // later.
					        $hashValidated = "Hack attempt, Got ya!";
					        wp_die($hashValidated);
					        $errorExists = true;
					        exit;
					    }

					} else {
					    // Secure Hash was not validated, add a data field to be displayed later.
					    $hashValidated = "Hack attempt, Got ya!";
						wp_die($hashValidated);
						exit;
					}

					// Define Variables
					// ----------------
					// Extract the available receipt fields from the VPC Response
					// If not present then let the value be equal to 'No Value Returned'

					// Standard Receipt Data
					$amount          	= $this->null2unknown($_GET["vpc_Amount"]);
					$locale          	= $this->null2unknown($_GET["vpc_Locale"]);
					$batchNo         	= $this->null2unknown($_GET["vpc_BatchNo"]);
					$command         	= $this->null2unknown($_GET["vpc_Command"]);
					$message         	= $this->null2unknown($_GET["vpc_Message"]);
					$version         	= $this->null2unknown($_GET["vpc_Version"]);
					$cardType        	= $this->null2unknown($_GET["vpc_Card"]);
					$orderInfo       	= $this->null2unknown($_GET["vpc_OrderInfo"]);
					$receiptNo       	= $this->null2unknown($_GET["vpc_ReceiptNo"]);
					$merchantID      	= $this->null2unknown($_GET["vpc_Merchant"]);
					$authorizeID     	= $this->null2unknown($_GET["vpc_AuthorizeId"]);
					$merchTxnRef     	= $this->null2unknown($_GET["vpc_MerchTxnRef"]);
					$transactionNo   	= $this->null2unknown($_GET["vpc_TransactionNo"]);
					$acqResponseCode 	= $this->null2unknown($_GET["vpc_AcqResponseCode"]);
					$txnResponseCode 	= $this->null2unknown($_GET["vpc_TxnResponseCode"]);


					// 3-D Secure Data
					$verType         	= array_key_exists("vpc_VerType", $_GET)          ? $_GET["vpc_VerType"]          : "No Value Returned";
					$verStatus       	= array_key_exists("vpc_VerStatus", $_GET)        ? $_GET["vpc_VerStatus"]        : "No Value Returned";
					$token           	= array_key_exists("vpc_VerToken", $_GET)         ? $_GET["vpc_VerToken"]         : "No Value Returned";
					$verSecurLevel   	= array_key_exists("vpc_VerSecurityLevel", $_GET) ? $_GET["vpc_VerSecurityLevel"] : "No Value Returned";
					$enrolled        	= array_key_exists("vpc_3DSenrolled", $_GET)      ? $_GET["vpc_3DSenrolled"]      : "No Value Returned";
					$xid             	= array_key_exists("vpc_3DSXID", $_GET)           ? $_GET["vpc_3DSXID"]           : "No Value Returned";
					$acqECI          	= array_key_exists("vpc_3DSECI", $_GET)           ? $_GET["vpc_3DSECI"]           : "No Value Returned";
					$authStatus      	= array_key_exists("vpc_3DSstatus", $_GET)        ? $_GET["vpc_3DSstatus"]        : "No Value Returned";

					// *******************
					// END OF MAIN PROGRAM
					// *******************

					// FINISH TRANSACTION - Process the VPC Response Data
					// =====================================================
					// For the purposes of demonstration, we simply display the Result fields on a
					// web page.

					// Show 'Error' in title if an error condition
					$errorTxt = "";

					// Show this page as an error page if vpc_TxnResponseCode equals '7'
					if ($txnResponseCode == "7" || $txnResponseCode == "No Value Returned" || $errorExists) {
					    $errorTxt = "Error ";
					    wp_die( $errorTxt );
						exit;
					}

					if ( isset( $_GET['order'] ) ) {
						$order_id = $_GET['order'];
						$response = $txnResponseCode;

						// Response is valid but lets check it more closly
						do_action( "valid_credimax_request", $response, $order_id );

						// set the $_GET query vars for the thankyou page, this empties the Cart
						wp_safe_redirect( jigoshop_get_page_id('thanks') );
						exit;
					}
				}
			}

			/* Successful Payment!
			------------------------------------------------------------ */
			function successful_request( $response, $order_id ) {

				$order = new jigoshop_order( (int) $order_id );

				switch ( $response ) {
					case '0':
						$order->add_order_note( __('Payment Authorized', 'jigoshop') );
						jigoshop_log( "CrediMax: payment authorized for Order ID: " . $order->id );
						$order->payment_complete();
						break;
					default:
						// Hold order
						$status = $this->getResponseDescription($response);
				        $order->update_status( 'on-hold', sprintf(__('Status %s via CrediMax.', 'jigoshop'), $status ) );
				        jigoshop_log( "CrediMax: failed order for Order ID: " . $order->id );
						wp_die($status);
						break;
				}

			}

			/* Successful Payment page
			------------------------------------------------------------ 
			function thankyou_page() {
				
			}
			*/

			//  -----------------------------------------------------------------------------

			// This method uses the QSI Response code retrieved from the Digital
			// Receipt and returns an appropriate description for the QSI Response Code
			//
			// @param $responseCode String containing the QSI Response Code
			//
			// @return String containing the appropriate description
			//
			function getResponseDescription($responseCode) {
				switch ($responseCode) {
				    case "0" : $result = "Transaction Successful"; break;
				    case "?" : $result = "Transaction status is unknown"; break;
				    case "1" : $result = "Unknown Error"; break;
				    case "2" : $result = "Bank Declined Transaction"; break;
				    case "3" : $result = "No Reply from Bank"; break;
				    case "4" : $result = "Expired Card"; break;
				    case "5" : $result = "Insufficient funds"; break;
				    case "6" : $result = "Error Communicating with Bank"; break;
				    case "7" : $result = "Payment Server System Error"; break;
				    case "8" : $result = "Transaction Type Not Supported"; break;
				    case "9" : $result = "Bank declined transaction (Do not contact Bank)"; break;
				    case "A" : $result = "Transaction Aborted"; break;
				    case "C" : $result = "Transaction Cancelled"; break;
				    case "D" : $result = "Deferred transaction has been received and is awaiting processing"; break;
				    case "F" : $result = "3D Secure Authentication failed"; break;
				    case "I" : $result = "Card Security Code verification failed"; break;
				    case "L" : $result = "Shopping Transaction Locked (Please try the transaction again later)"; break;
				    case "N" : $result = "Cardholder is not enrolled in Authentication scheme"; break;
				    case "P" : $result = "Transaction has been received by the Payment Adaptor and is being processed"; break;
				    case "R" : $result = "Transaction was not processed - Reached limit of retry attempts allowed"; break;
				    case "S" : $result = "Duplicate SessionID (OrderInfo)"; break;
				    case "T" : $result = "Address Verification Failed"; break;
				    case "U" : $result = "Card Security Code Failed"; break;
				    case "V" : $result = "Address Verification and Card Security Code Failed"; break;
				    default  : $result = "Unable to be determined";
				}
				return $result;
			}

			//  -----------------------------------------------------------------------------

			// This method uses the verRes status code retrieved from the Digital
			// Receipt and returns an appropriate description for the QSI Response Code

			// @param statusResponse String containing the 3DS Authentication Status Code
			// @return String containing the appropriate description
			function getStatusDescription($statusResponse) {
			    if ($statusResponse == "" || $statusResponse == "No Value Returned") {
			        $result = "3DS not supported or there was no 3DS data provided";
			    } else {
			        switch ($statusResponse) {
			            case "Y"  : $result = "The cardholder was successfully authenticated."; break;
			            case "E"  : $result = "The cardholder is not enrolled."; break;
			            case "N"  : $result = "The cardholder was not verified."; break;
			            case "U"  : $result = "The cardholder's Issuer was unable to authenticate due to some system error at the Issuer."; break;
			            case "F"  : $result = "There was an error in the format of the request from the merchant."; break;
			            case "A"  : $result = "Authentication of your Merchant ID and Password to the ACS Directory Failed."; break;
			            case "D"  : $result = "Error communicating with the Directory Server."; break;
			            case "C"  : $result = "The card type is not supported for authentication."; break;
			            case "S"  : $result = "The signature on the response received from the Issuer could not be validated."; break;
			            case "P"  : $result = "Error parsing input from Issuer."; break;
			            case "I"  : $result = "Internal Payment Server system error."; break;
			            default   : $result = "Unable to be determined"; break;
			        }
			    }
			    return $result;
			}

			//  -----------------------------------------------------------------------------
   
			// If input is null, returns string "No Value Returned", else returns input
			function null2unknown($data) {
			    if ($data == "") {
			        return "No Value Returned";
			    } else {
			        return $data;
			    }
			} 

		}


	/* Add our new payment gateway to the Jigoshop gateways 
	------------------------------------------------------------ */
	function add_credimax_payment_gateway( $methods ) {
		$methods[] = 'jigoshop_credimax_gateway';
		return $methods;
	}
	add_filter( 'jigoshop_payment_gateways', 'add_credimax_payment_gateway', 55);

}
add_action( 'plugins_loaded', 'jigoshop_credimax_payment_gateway', 0 );
