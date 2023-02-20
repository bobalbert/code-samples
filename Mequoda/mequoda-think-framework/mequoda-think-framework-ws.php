<?php
/**
 * Created by PhpStorm.
 * User: balbert
 * Date: 12/3/15
 * Time: 3:11 PM
 *
 * SAOP Web Service to handle THINK Event Queue notifications
 *
 */

class mqThinkEventAcknowledgment{
	public $status;
	public $failure_reason;
}

/**
 * Class mqThinkEvent
 *
 * handles the THINK event notifications
 */
class mqThinkEvent
{

	private $_errors = array();

	/**
	 * Logger to from-think.log. outputs WS actions on event calls from THINK
	 *
	 * @param $title - message title
	 * @param $data - data to output. array/object or text
	 */
	public function from_think_log( $title, $data ){

		//$where = WP_PLUGIN_DIR . '/mequoda-think-framework/logs/from-think.log';
		$where = '/home/countrysidenetwork.com/logs/from-think.log';

		$when = date('Y-m-d H:i:s');
		if( is_array( $data ) || is_object( $data ) ){
			error_log("[{$when}] {$title}: " . print_r( $data, 1 ) . "\n",3, $where );
		} else {
			error_log("[{$when}] {$title}: {$data}" . "\n",3, $where );
		}

	}

	/**
	 * handle the THINK Event Notification posted data
	 *
	 * @param $data - the xml data that was posted as an object
	 *
	 * @return mqThinkEventAcknowledgment
	 */

	public function ThinkEvent( $data ){


		$settings = get_option( 'meq-think-framework' );

		//instantiate the response class
		$response = new mqThinkEventAcknowledgment();

		// did we get a valid transaction event?
		if( $data->transaction_data->transaction_event ){

			$transaction_event = $data->transaction_data->transaction_event;
			$this->from_think_log( 'ThinkEvent - transaction id', $transaction_event );

			// dev logging - set in THINK settings admin.
			if( 'on' == $settings['wslogging'] ) {
				// just log the event
				$transaction_event = 100;
			}

		} else {

			$this->from_think_log( 'ThinkEvent - NO transaction id', $data );

			$this->_errors['NO transaction id'] = $data;
			$this->_sendErrors();

			$response->status = 'failure';
			$response->failure_reason = 'no transaction id';

			return $response;
		}

		$this->from_think_log( 'ThinkEvent - begin', $data );

		$postdata = file_get_contents("php://input");
		$this->from_think_log( 'ThinkEvent - Post Data', $postdata );

		// based on the event type, set the function to process the data on Haven side
		switch ( $transaction_event ) {

			// (29)TE_CUSTOMER_ADDED
			// a new customer record was added
			case 29:
				$mqws_function = 'processCustomerAdded';
                break;

			//(07)TE_PAYMENT_ADDED
			// a payment was added
			case 7:
				$mqws_function = 'processPaymentAdded';
				break;

			// (08)TE_ORDER_CANCELED
			// an order item was canceled
			case 8:
				$mqws_function = 'processOrderCanceled';
				break;

			// (22)TE_SUSPENSION_BEGUN - the suspension of an order item just began, either because an operator entered a suspension of the order for some reason such as bad behavior or undeliverable address, or some other event triggered the suspension to begin, such as non-payment or a requested temporary suspension due to being on vacation
			case 22:
				$mqws_function = 'processSuspensionBegun';
				break;

			// (23)TE_SUSPENSION_ENDED - the suspension of an order item just ended, either because an operator lifted the suspension, or some other event triggered the termination, such as payment of a non-paid order
			case 23:
				$mqws_function = 'processSuspensionEnded';
				break;

			// (25)TE_EXPIRE_DATE_CHANGED - an order item’s expire_date changed
			case 25:
				$mqws_function = 'processExpireDateChanged';
				break;

			// (36)TE_DEPOSIT_USED - payment was added from a deposit account
			case 36:
				$mqws_function = 'processDepositUsed';
				break;

			default:
				// just return success to clear queue... no need to process on Haven side
				$response = new mqThinkEventAcknowledgment();
				$response->status = 'success';
				return $response;
		}

		$result = $this->$mqws_function( $data->transaction_data );

		if( $result ){
			$response->status = 'success';

			$this->from_think_log( 'ThinkEvent', 'success');

		} else {

			$this->from_think_log( 'ThinkEvent', 'failure' );
			$this->_errors['ThinkEvent'] = 'failure - rutroh, foo equals bar';
			$this->_sendErrors();

			$response->status = 'failure';
			$response->failure_reason = 'rutroh, foo equals bar';

		}

		// send response back to THINK
		return $response;
	}

	/** procces functions */

	// (29)TE_CUSTOMER_ADDED
	// a new customer record was added
	protected function processCustomerAdded( $data ){

		$this->from_think_log( 'processCustomerAdded - begin data', $data );

		//See if the user has an email
		if( $data->td_customer->email ){

			$user = get_user_by( 'email', $data->td_customer->email );

			if( !$user ){
				// user doesn't exist, create them

				$this->from_think_log( 'processCustomerAdded', 'no user by email' );

				// begin Create a username/user_login
				if ( $data->td_customer->fname == '' && $data->td_customer->lname == '' ) {
					$user_login = sanitize_user( $data->td_customer->email );
				} else {
					$user_login = sanitize_user( strtolower( str_replace( array('&', ' ', '/'), '', $data->td_customer->fname ) ) . strtolower( $data->td_customer->lname{0} ) );
				}

				// If empty, put something in user login so error displayed is ONLY for E-Mail
				if ( $user_login == '' ) {
					$user_login = 'thinkcreated';
				}

				if ( username_exists($user_login) ) {
					$integer_suffix = 2;
					while ( username_exists($user_login . $integer_suffix) ) {
						$integer_suffix++;
					}
					$user_login .= $integer_suffix;
				}
				// end Create a username/user_login

				$_POST['first_name'] = $data->td_customer->fname;
				$_POST['last_name']  = $data->td_customer->lname;

				$sanitized_email = sanitize_email( $data->td_customer->email );

				// create the new user
				$new_user = register_new_user( $user_login, $sanitized_email );

				if( !is_wp_error( $new_user ) ){
					// new user created
					$this->from_think_log( 'processCustomerAdded - new user created', $new_user );

					// update new user with think_customer_id
					$this->from_think_log( 'processCustomerAdded - update think customer id', $data->td_customer->customer_id );

					$usermetas = array(
						'think_customer_id' => $data->td_customer->customer_id,
						'address'           => $data->td_customer->address1,
						'address2'          => $data->td_customer->address2,
						'city'              => $data->td_customer->city,
						'state'             => $data->td_customer->state,
						'zip_code'          => $data->td_customer->zip
					);
					foreach( $usermetas as $meta_key => $meta_value ){
						update_user_meta( $new_user, $meta_key, $meta_value );
					}

					$user = get_user_by( 'id', $new_user );


				} else {
					// user create error
					$this->from_think_log( 'processCustomerAdded - error creating user', $new_user );
					$this->_errors['customer_added'] = array( 'error creating new user', $new_user, $data );

					$this->_sendErrors();
				}

				// user created, clear event
				$result = true;

			} else {
				// existing user
				$this->from_think_log( 'processCustomerAdded - existing user', $user );

				// get the user's THINK customer id to check if existing THINK customer
				$think_customer_id = get_user_meta( $user->ID, 'think_customer_id', true);

				$this->from_think_log( 'processCustomerAdded - existing user - Think customer id', $think_customer_id );

				if( '' == $think_customer_id ){
					// update user's think_customer_id
					update_user_meta(  $user->ID, 'think_customer_id', $data->td_customer->customer_id );
					$this->from_think_log( 'processCustomerAdded - existing user - UPDATE THINK customer id', $user );
				} else {

					if( $think_customer_id != $data->td_customer->customer_id ){

						$this->from_think_log( 'processCustomerAdded - error duplicate users', $data );
						$this->_errors['customer_added'] = array(
							'issue' => 'Duplicate THINK users?',
							'haven_think_id' => $think_customer_id,
							'think_think_id' => $data->td_customer->customer_id,
							'haven_user' => $user,
							'think_data' => $data );

						$this->_sendErrors();
					}

				}

				// do nothing and clear the event
				$result = true;
			}

		} else {
			// no email, then can not create them.
			$this->from_think_log( 'processCustomerAdded - no email', 'cannot create user' );

			// do nothing and clear the event
			$result = true;
		}

		$this->from_think_log( 'processCustomerAdded', 'end' );

		return $result;
	}

	//(07)TE_PAYMENT_ADDED
	// a payment was added
	protected function processPaymentAdded( $data ){

		$this->from_think_log( 'processPaymentAdded - begin data', $data );

		$t_order = $data->td_payment->td_item_paid_for;

		if( $t_order == null ){
			$this->from_think_log( 'processPaymentAdded - No Order for Payment', $data );
			$this->_errors['payment_added'] = array( 'No Order for Payment', $data );

			$this->_sendErrors();

			return true;
		}

		// no email for customer, send error and stop
		if( empty( $data->td_customer->email ) || '' == $data->td_customer->email ){

			$this->from_think_log( 'processPaymentAdded - No Email', $data );

			return true;
		}

		// find out if this is a multi item oder
		if( !is_array( $t_order ) ){
			$order_items[] = $t_order;
			$this->from_think_log( 'processPaymentAdded', 'single item' );
		} else {
			$order_items = $t_order;
			$this->from_think_log( 'processPaymentAdded', 'multiple items' );
		}

		// process each order item
		foreach ( $order_items as $order_item ) {

			$product_id = $order_item->product_id;

			// if there is a product id, then this is a shopp order so skip everything
			if ( empty( $product_id ) || '' == $product_id ) {

				// is this a gift?
				$think_customer_id          = $data->td_customer->customer_id;
				$payment_customer_id        = $data->td_payment->customer_id;
				//$order_item_for_customer_id = $data->td_payment->td_item_paid_for->customer_id;
				$order_item_for_customer_id = $order_item->customer_id;

				if( $payment_customer_id != $order_item_for_customer_id ){
					// this appears to be a gift.

					// get the Recipient's info
					$recipient_user = $this->find_think_recipent( $order_item_for_customer_id );
					$recipient_user->think_customer_id = $order_item_for_customer_id;


					// get the donor's info
					$donor_user = $this->find_think_user( $payment_customer_id, $data );
					$donor_user->think_customer_id = $payment_customer_id;

				} else {
					
					// single user order
					$recipient_user = $this->find_think_user( $think_customer_id, $data );
					$recipient_user->think_customer_id = $think_customer_id;

				}

				// if an error don't create the order.
				if( !empty( $this->_errors ) ){
					$this->from_think_log( 'processPaymentAdded - duplicate user issue', $this->_errors );
					return true;
				}

				// look to see if there is a previous order
				$orderhdr_id = $order_item->orderhdr_id;
				$subscrip_id = $order_item->subscrip_id;

				// check if this order is already in Haven
				$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

				if ( is_null( $m_order ) ) {
					/*****
					 * Order DOES NOT exist in Haven.
					 * Create the new order
					 */


					// the rest of the THINK order data
					$order_item_seq      = $order_item->order_item_seq;
					$order_code_id       = $order_item->order_code_id;
					$subscription_def_id = $order_item->subscription_def_id;
					$expire_date         = $order_item->expire_date;

					$product_offer = mq_get_products_for_think( $order_code_id, $subscription_def_id );

					// don't process order for THINK via mequoda-ordered hook.
					global $mqThinkFramework;
					remove_action( 'mequoda-ordered', array( $mqThinkFramework, 'processOrder' ) );


					// clean up bad data
					if( isset( $data->td_customer->email_authorization ) ){
						unset( $data->td_customer->email_authorization );
					}

					// add the order to mequoda order table

					// setup the think data json
					$think_order                        = new stdClass();
					$think_order->thinkevent            = true;
					$think_order->customer              = $data->td_customer;
					$think_order->subscrip->subscrip_id = $order_item->subscrip_id;
					$think_order->payment               = $data->td_payment;
					$think_order->orderhdr->orderhdr_id = $orderhdr_id;
					$think_order->orderhdr->order_item  = $order_item;


					$args['product_offer'] = $product_offer;
					$args['user']          = $recipient_user;
					$args['think_data']    = $data;
					$args['think_order']   = json_encode( $think_order );
					$args['order_item']    = $order_item;
					$args['donor_id']      = $donor_user->ID;


					$order_id = $this->storeThinkEventOrder( $args );

					if ( empty( $this->_errors ) ) {

						$this->from_think_log( 'processPaymentAdded - order created', $order_id );

					} else {

						// issue with saving the order.
						$this->_errors['think_data'] = $data;
						$this->_sendErrors();
						$this->from_think_log( 'processPaymentAdded - issue saving order', $this->_errors );
					}


				} else {
					/*****
					 * order DOES exists already. don't add?
					 */
					$this->from_think_log( 'processPaymentAdded - duplicate order submitted', $data );
					$this->_errors['payment_added'] = array( 'Existing order submitted', $data );

					$this->_sendErrors();

				}
			}else{
				$this->from_think_log( 'processPaymentAdded - shopp order. PRODUCT ID:', $product_id );
			}
		}
		// always true to clear event?
		$result = true;

		return $result;
	}

	//(36) DEPOSIT_USED
	// a payment was added
	protected function processDepositUsed( $data ){

		$this->from_think_log( 'processDepositUsed - begin data', $data );

		$t_order = $data->td_order;

		if( $t_order == null ){
			$this->from_think_log( 'processDepositUsed - No Order for Payment', $data );
			$this->_errors['payment_added'] = array( 'No Order for Payment', $data );

			$this->_sendErrors();

			return true;
		}

		// no email for customer, send error and stop
		if( empty( $data->td_customer->email ) || '' == $data->td_customer->email ){

			$this->from_think_log( 'processDepositUsed - No Email', $data );

			return true;
		}

		// find out if this is a multi item oder
		if( !is_array( $t_order ) ){
			$order_items[] = $t_order->td_item;
			$this->from_think_log( 'processDepositUsed', 'single item' );
		} else {
			$order_items = $t_order;
			$this->from_think_log( 'processDepositUsed', 'multiple items' );
		}

		// process each order item
		foreach ( $order_items as $order_item ) {

			$product_id = $order_item->product_id;

			// if there is a product id, then this is a shopp order so skip everything
			if ( empty( $product_id ) || '' == $product_id ) {

				// is this a gift?
				$think_customer_id          = $data->td_customer->customer_id;
				$payment_customer_id        = $data->td_payment->customer_id;
				$order_item_for_customer_id = $data->td_payment->td_item_paid_for->customer_id;

				// this might never happen checking with THINK.
				if( $payment_customer_id != $order_item_for_customer_id ){
					// this appears to be a gift.

					// get the Recipient's info
					$recipient_user = $this->find_think_recipent( $order_item_for_customer_id );
					$recipient_user->think_customer_id = $order_item_for_customer_id;

					// get the donor's info
					$donor_user = $this->find_think_user( $payment_customer_id, $data );
					$donor_user->think_customer_id = $payment_customer_id;

				} else {

					// single user order
					$recipient_user = $this->find_think_user( $think_customer_id, $data );
					$recipient_user->think_customer_id = $think_customer_id;
				}

				// if an error don't create the order.
				if( !empty( $this->_errors ) ){
					$this->from_think_log( 'processDepositUsed - duplicate user issue', $this->_errors );
					return true;
				}

				// look to see if there is a previous order
				$orderhdr_id = $order_item->orderhdr_id;
				$subscrip_id = $order_item->subscrip_id;

				// check if this order is already in Haven
				$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

				if ( is_null( $m_order ) ) {
					/*****
					 * Order DOES NOT exist in Haven.
					 * Create the new order
					 */

					// the rest of the THINK order data
					$order_item_seq      = $order_item->order_item_seq;
					$order_code_id       = $order_item->order_code_id;
					$subscription_def_id = $order_item->subscription_def_id;
					$expire_date         = $order_item->expire_date;

					$product_offer = mq_get_products_for_think( $order_code_id, $subscription_def_id );


					// don't process order for THINK via mequoda-ordered hook.
					global $mqThinkFramework;
					remove_action( 'mequoda-ordered', array( $mqThinkFramework, 'processOrder' ) );

					// clean up bad data
					if( isset( $data->td_customer->email_authorization ) ){
						unset( $data->td_customer->email_authorization );
					}

					// add the order to mequoda order table

					// setup the think data json
					$think_order                        = new stdClass();
					$think_order->thinkevent            = true;
					$think_order->customer              = $data->td_customer;
					$think_order->subscrip->subscrip_id = $order_item->subscrip_id;
					$think_order->payment               = 'Deposit Used';
					$think_order->orderhdr->orderhdr_id = $orderhdr_id;
					$think_order->orderhdr->order_item  = $order_item;

					$order_item->total_paid = $order_item->total;

					$args['product_offer'] = $product_offer;
					$args['user']          = $recipient_user;
					$args['think_data']    = $data;
					$args['think_order']   = json_encode( $think_order );
					$args['order_item']    = $order_item;
					$args['donor_id']      = $donor_user->ID;


					$order_id = $this->storeThinkEventOrder( $args );

					if ( empty( $this->_errors ) ) {

						$this->from_think_log( 'processDepositUsed - order created', $order_id );

					} else {

						// issue with saving the order.
						$this->_errors['think_data'] = $data;
						$this->_sendErrors();
						$this->from_think_log( 'processDepositUsed - issue saving order', $this->_errors );
					}


				} else {
					/*****
					 * order DOES exists already. don't add?
					 */
					$this->from_think_log( 'processDepositUsed - duplicate order submitted', $data );
					$this->_errors['deposit_used'] = array( 'Existing order submitted', $data );

					$this->_sendErrors();

				}
			}else{
				$this->from_think_log( 'processDepositUsed - shopp order. PRODUCT ID:', $product_id );
			}
		}
		// always true to clear event?
		$result = true;

		return $result;
	}


	// (08)TE_ORDER_CANCELED
	// an order item was canceled
	protected function processOrderCanceled( $data ){

		$this->from_think_log( 'processOrderCanceled - begin data', $data );

		$order_item = $data->td_order->td_item;
		$product_id = $order_item->product_id;

		// if not a shopp order, then cancel the order
		if( empty( $product_id ) || '' == $product_id ){

			$orderhdr_id = $order_item->orderhdr_id;
			$subscrip_id = $order_item->subscrip_id;

			// get the order
			$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

			if( !is_null( $m_order ) ){

				$m_product_id = $m_order->product_id;
				$user_id = $m_order->user_id;

				$user = get_user_by( 'id', $user_id );

				// cancels them now...
				$cancel_expire_date = date( 'Ymd', strtotime( '-1 days' ) );
				$access_control_name = get_post_meta( $m_product_id, 'access_control_name', true);

				if ( strpos($access_control_name, ',') !== false ) {
					$access_names = explode(',', $access_control_name);
					foreach ( $access_names as $access_name ) {
						$access_name = trim( $access_name );
						update_user_meta( $user->ID, 'expire_date_'.$access_name, $cancel_expire_date );

						$success[] = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $cancel_expire_date );
					}
				} else {
					update_user_meta( $user->ID, 'expire_date_'.$access_control_name, $cancel_expire_date );

					$success = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $cancel_expire_date );
				}

				$this->from_think_log( 'processOrderCanceled - Success', $success );

			} else {

				//no order to cancel... do we care?
				// shopp order... no cancel to be done

				$this->from_think_log( 'processOrderCanceled - Order not found', $data );

			}

		} else {
			$this->from_think_log( 'processOrderCanceled - shopp order', $product_id );
		}

		// always clear the event
		$result = true;

		return $result;
	}

	// (22)TE_SUSPENSION_BEGUN - the suspension of an order item just began, either because an operator entered a suspension of the order for some reason such as bad behavior or undeliverable address, or some other event triggered the suspension to begin, such as non-payment or a requested temporary suspension due to being on vacation
	protected function processSuspensionBegun( $data ){

		$this->from_think_log( 'processSuspensionBegun - begin.', $data );

		$order_item = $data->td_order->td_item;
		$product_id = $order_item->product_id;

		// if not a shopp order, then cancel the order
		if( empty( $product_id ) || '' == $product_id ){

			$orderhdr_id = $order_item->orderhdr_id;
			$subscrip_id = $order_item->subscrip_id;
			$suspend_from_date = str_replace( "-", "", $order_item->td_suspension->suspend_from_date );

			// get the order
			$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

			if( !is_null( $m_order ) ){

				$m_product_id = $m_order->product_id;
				$user_id = $m_order->user_id;

				$user = get_user_by( 'id', $user_id );

				// suspend them
				$access_control_name = get_post_meta( $m_product_id, 'access_control_name', true);

				if ( strpos($access_control_name, ',') !== false ) {
					$access_names = explode(',', $access_control_name);
					foreach ( $access_names as $access_name ) {
						$access_name = trim( $access_name );
						update_user_meta( $user->ID, 'expire_date_'.$access_name, $suspend_from_date );

						$success[] = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'suspend_from_date' => $suspend_from_date );
					}
				} else {
					update_user_meta( $user->ID, 'expire_date_'.$access_control_name, $suspend_from_date );

					$success = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'suspend_from_date' => $suspend_from_date );
				}

				$this->from_think_log( 'processSuspensionBegun - success.', $success );

			} else {

				//no order to cancel... do we care?
				// shopp order... no cancel to be done

				$this->from_think_log( 'processSuspensionBegun - Order not found.', $data );

			}

		} else {
			$this->from_think_log( 'processSuspensionBegun - shopp order', $product_id );
		}

		// always clear the event
		$result = true;

		return $result;

	}

	// (23)TE_SUSPENSION_ENDED - the suspension of an order item just ended, either because an operator lifted the suspension, or some other event triggered the termination, such as payment of a non-paid order
	protected function processSuspensionEnded( $data ){

		$this->from_think_log( 'processSuspensionEnded - begin.', $data );

		$order_item = $data->td_order->td_item;
		$product_id = $order_item->product_id;

		// if not a shopp order, then cancel the order
		if( empty( $product_id ) || '' == $product_id ){

			$orderhdr_id = $order_item->orderhdr_id;
			$subscrip_id = $order_item->subscrip_id;
			$expire_date = str_replace( "-", "", $order_item->expire_date );

			// get the order
			$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

			if( !is_null( $m_order ) ){

				$m_product_id = $m_order->product_id;
				$user_id = $m_order->user_id;

				$user = get_user_by( 'id', $user_id );

				// suspend them
				$access_control_name = get_post_meta( $m_product_id, 'access_control_name', true);

				if ( strpos($access_control_name, ',') !== false ) {
					$access_names = explode(',', $access_control_name);
					foreach ( $access_names as $access_name ) {
						$access_name = trim( $access_name );
						update_user_meta( $user->ID, 'expire_date_'.$access_name, $expire_date );

						$success[] = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $expire_date );
					}
				} else {
					update_user_meta( $user->ID, 'expire_date_'.$access_control_name, $expire_date );

					$success = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $expire_date );
				}

				$this->from_think_log( 'processSuspensionEnded - success.', $success );

			} else {

				//no order to cancel... do we care?
				// shopp order... no cancel to be done

				$this->from_think_log( 'processSuspensionEnded - Order not found.', $data );

			}

		} else {
			$this->from_think_log( 'processSuspensionEnded - shopp order', $product_id );
		}

		$this->from_think_log( 'processSuspensionEnded - end.', $data );

		// always clear the event
		$result = true;

		return $result;
	}

	// (25)TE_EXPIRE_DATE_CHANGED - an order item’s expire_date changed
	protected function processExpireDateChanged( $data ){

		$this->from_think_log( 'processExpireDateChanged - begin data.', $data );

		$order_item = $data->td_order->td_item;
		$product_id = $order_item->product_id;

		// if not a shopp order, then cancel the order
		if( empty( $product_id ) || '' == $product_id ){

			$orderhdr_id = $order_item->orderhdr_id;
			$subscrip_id = $order_item->subscrip_id;
			$expire_date = str_replace( "-", "", $order_item->expire_date );

			// get the order
			$m_order = mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id );

			if( !is_null( $m_order ) ){

				$m_product_id = $m_order->product_id;
				$user_id = $m_order->user_id;

				$user = get_user_by( 'id', $user_id );

				// suspend them
				$access_control_name = get_post_meta( $m_product_id, 'access_control_name', true);

				if ( strpos($access_control_name, ',') !== false ) {
					$access_names = explode(',', $access_control_name);
					foreach ( $access_names as $access_name ) {
						$access_name = trim( $access_name );
						update_user_meta( $user->ID, 'expire_date_'.$access_name, $expire_date );

						$success[] = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $expire_date );
					}
				} else {
					update_user_meta( $user->ID, 'expire_date_'.$access_control_name, $expire_date );

					$success = array( 'access_control_name' => 'expire_date_'.$access_control_name, 'expire_date' => $expire_date );
				}

				$this->from_think_log( 'processExpireDateChanged - success', $success );

			} else {

				//no order to cancel... do we care?
				// shopp order... no cancel to be done
				$this->from_think_log( 'processExpireDateChanged - Order not found.', $data );

			}

		} else {
			$this->from_think_log( 'processExpireDateChanged - shopp order', $product_id );
		}

		$this->from_think_log( 'processExpireDateChanged', 'end' );

		// always clear the event
		$result = true;

		return $result;
	}

	/**
	 * create a mequoda order for the submitted THINK event data
	 *
	 * @param $args
	 *
	 * @return false|int
	 */
	protected function storeThinkEventOrder( $args ){

		global $wpdb;

		$defaults = array(
			'price'							=> 0,
			'payment'						=> 0,
			'paypal_fee'					=> 0,
			'refund'						=> 0,
			'payment_type'					=> '',
			'card_expire'					=> '',
			'purchase_order'				=> '',
			'payment_status'				=> '',
			'pending_reason'				=> '',
			'reason_code'					=> '',
			'product_name'					=> '',
			'file_name'						=> '',
			'offer_code'					=> '',
			'premiums'						=> '',
			'term'							=> '',
			'donor_id'						=> 0,
			'user_id'						=> 0,
			'first_name'					=> '',
			'last_name'						=> '',
			'address'						=> '',
			'address2'						=> '',
			'city'							=> '',
			'state'							=> '',
			'zip_code'						=> '',
			'country'						=> '',
			'phone'							=> '',
			'user_email'					=> '',
			'title'							=> '',
			'company'						=> '',
			'user_ip'						=> '',
			'RESULT'						=> '',
			'RESPMSG'						=> '',
			'HOSTCODE'						=> '',
			'RESPTEXT'						=> '',
			'PNREF'							=> '',
			'BAID'							=> '',
			'AUTHCODE'						=> '',
			'AVSADDR'						=> 'X',
			'AVSZIP'						=> 'X',
			'IAVS'							=> 'X',
			'CVV2MATCH'						=> 'X',
			'DUPLICATE'						=> '',
			'RB_RESULT'						=> '',
			'PROFILEID'						=> '',
			'RB_RESPMSG'					=> '',
			'RPREF'							=> '',
			'cancel_rpref'					=> '',
			'ack'							=> '',
			'profile_id'					=> '',
			'transaction_id'				=> '',
			'avs_code'						=> '',
			'cvv2_match'					=> '',
			'correlation_id'				=> '',
			'authorizenet_response_reason_text'	=> '',
			'authorizenet_account_number'	=> '',
			'arb_response_code'				=> '',
			'arb_response_text'				=> '',
			'order_summary'					=> '',
			'track'							=> '',
			'order_type'					=> 'n',
			'cancelled'						=> 'n',
			'rb_cancelled'					=> 'n',
			'comments'						=> '',
			'authorizenet_xml_check'		=> '',
			'payflow_pro_response'			=> '',
			'payflow_pro_rb_response'		=> '',
			'chargebee_json'				=> '',
			'chargebee_id'					=> '',
			'chargebee_status'				=> '',
			'chargebee_current_term_end'	=> 0,
			'renewal_date'					=> 0,
			'think_orderhdr_id'				=> 0,
			'think_data'					=> '',
			'think_subscrip_id'				=> 0,
		);

		global $wpdb;

		$product_offer = $args['product_offer'];
		$user          = $args['user'];
		$think_data    = $args['think_data'];
		$order_item   = $args['order_item'];
		$donor_id = $args['donor_id'];

		$table = $wpdb->prefix . 'mequoda_orders';

		//2016-01-11 17:43:36
		$order_time = date( 'Y-m-d H:i:s', strtotime( $order_item->order_date ) );

		$new_values = array(
			'order_time'        => $order_time,
			'product_id'        => $product_offer->product_id,
			'price'             => $product_offer->amt,
			'payment'           => $order_item->total_paid,
			'payment_type'      => 'CK',
			'product_name'      => $product_offer->title,
			'offer_code'        => $product_offer->id,
			'user_id'           => $user->ID,
			'donor_id'          => $donor_id,
			'user_email'        => $user->user_email,
			'first_name'        => $user->first_name, //$think_data->td_customer->fname,
			'last_name'         => $user->last_name, //$think_data->td_customer->lname,
			'address'           => $user->address, //$think_data->td_customer->address1,
			'address2'          => $user->address2, //$think_data->td_customer->address2,
			'city'              => $user->city, //$think_data->td_customer->city,
			'state'             => $user->state, //$think_data->td_customer->state,
			'zip_code'          => $user->zip_code, //$think_data->td_customer->zip,
			'phone'             => $user->phone, //$think_data->td_customer->phone,
			'user_ip'           => $_SERVER['HTTP_X_FORWARDED_FOR'],
			'order_type'        => 'n',
			'think_orderhdr_id' => $order_item->orderhdr_id,
			'think_data'        => $args['think_order'],
			'think_subscrip_id' => $order_item->subscrip_id,
			'RESULT'            => 1,
			'RESPMSG'           => 'success'
		);

		$new_values = array_filter($new_values);

		$sql_data = array_merge($defaults, $new_values);

		$result = $wpdb->insert(
			$table,
			$sql_data
		);

		// update entitlements
		if( $result ){
			$order_id = $wpdb->insert_id;

			$args['order_id'] = $order_id;

			$access_control_name = get_post_meta( $product_offer->product_id, 'access_control_name', true);
			$expire_date = str_replace( "-", "", $order_item->expire_date );

			if ( strpos($access_control_name, ',') !== false ) {
				$access_names = explode(',', $access_control_name);
				foreach ( $access_names as $access_name ) {
					$access_name = trim( $access_name );
					update_user_meta( $user->ID, 'expire_date_'.$access_name, $expire_date );
				}
			} else {
				update_user_meta( $user->ID, 'expire_date_'.$access_control_name, $expire_date );
			}

			// update order summary ???
			$wpdb->update( $table, array( 'order_summary' => $this->getThinkOrderSummary( $args ) ), array( 'id' => $order_id ) );


		} else {
			$order_id = $result;
			$this->_errors['think_store_event_order'] = $wpdb->last_error;
		}

		return $order_id;

	}

	/*
	 * Create an Order Summary data for THINK order
	 */
	protected function getThinkOrderSummary( $args ){

		$product_offer = $args['product_offer'];
		$think_data    = $args['think_data'];
		$order_id      = $args['order_id'];
		$order_item    = $args['order_item'];

		$order_number        = str_pad( $order_id, 6, '0', STR_PAD_LEFT );
		$purchase_date       = date( 'F j, Y g:i a T', strtotime( $order_item->order_date ) );
		$order_product_title = $product_offer->order_summary_product_name;
		$price_label         = "Price: ";
		$price               = $product_offer->amt;

		$order_summary = "Order Number: " . $order_number . "\n" .
		                       "Purchase Date: " . $purchase_date . "\n" .
		                       "Product: " . $order_product_title . "\n";
		$order_summary .=  "Buyer Name: " . $think_data->td_customer->fname . " " . $think_data->td_customer->lname . "\n";
		$order_summary .=  "Paid by Check" . "\n" ;
		$order_summary .= $price_label . $price . "\n";

		return $order_summary;

	}

	/**
	 *  send WS error
	 */

	protected function _sendErrors(){

		$message = print_r( $this->_errors, 1 );
		//@todo make this config driven
		wp_mail( 'bob@mequoda.com', 'THINK WS Error', $message );

		$this->from_think_log( 'send think WS error', $message );
	}

	protected function find_think_user( $think_customer_id, $data ){

		$user_id = mq_get_user_from_think_customer_id( $think_customer_id);

		// no user found by think customer id
		if ( empty( $user_id ) ) {

			$this->from_think_log( 'processPaymentAdded', 'no user by think id' );

			// find by email?
			$user = get_user_by( 'email', $data->td_customer->email );

			if ( ! $user ) {
				// user doesn't exist, create them
				$this->from_think_log( 'processPaymentAdded', 'no user by email' );

				// begin Create a username/user_login
				if ( $data->td_customer->fname == '' && $data->td_customer->lname == '' ) {
					$user_login = sanitize_user( $data->td_customer->email );
				} else {
					$user_login = sanitize_user( strtolower( str_replace( array( '&', ' ', '/' ), '', $data->td_customer->fname ) . strtolower( $data->td_customer->lname{0} ) ) );
				}

				// If empty, put something in user login so error displayed is ONLY for E-Mail
				if ( $user_login == '' ) {
					$user_login = 'thinkcreated';
				}

				if ( username_exists( $user_login ) ) {
					$integer_suffix = 2;
					while ( username_exists( $user_login . $integer_suffix ) ) {
						$integer_suffix ++;
					}
					$user_login .= $integer_suffix;
				}

				// // end Create a username/user_login
				if(  $data->td_customer->fname == '' || $data->td_customer->lname == '' ){

					if( $data->td_customer->company != '' ){
						$company_name = explode( ' ', $data->td_customer->company );

						if( count( $company_name ) > 1){
							$_POST['first_name'] = $company_name[0];
							$_POST['last_name']  = $company_name[1];
						}else{
							$_POST['first_name'] = $company_name[0];
							$_POST['last_name']  = $company_name[0]{0};
						}
					} else {
						$_POST['first_name'] = 'Firstname';
						$_POST['last_name']  = 'Lastname';
					}


				} else {

					$_POST['first_name'] = $data->td_customer->fname;
					$_POST['last_name']  = $data->td_customer->lname;
				}



				// create the new user
				$new_user_id = register_new_user( $user_login, $data->td_customer->email );

				if ( ! is_wp_error( $new_user_id ) ) {
					// new user created
					$this->from_think_log( 'processPaymentAdded - new user created', $new_user_id );

					// update new user with think_customer_id
					$this->from_think_log( 'processPaymentAdded - update think customer id', $data->td_customer->customer_id );

					$usermetas = array(
							'think_customer_id' => $data->td_customer->customer_id,
							'address'           => $data->td_customer->address1,
							'address2'          => $data->td_customer->address2,
							'city'              => $data->td_customer->city,
							'state'             => $data->td_customer->state,
							'zip_code'          => $data->td_customer->zip
						);
					foreach( $usermetas as $meta_key => $meta_value ){
						update_user_meta( $new_user_id, $meta_key, $meta_value );
					}

					$user = get_user_by( 'id', $new_user_id );

				} else {
					// user create error
					$this->from_think_log( 'processPaymentAdded - error creating user', $new_user_id );
					$this->_errors['customer_added'] = array( 'error creating new user', $new_user_id, $data );

					$this->_sendErrors();

					return true;
				}


			} else {
				// existing user
				$this->from_think_log( 'processPaymentAdded - existing user', $user );

				// get the user's THINK customer id to check if existing THINK customer
				$think_customer_id = get_user_meta( $user->ID, 'think_customer_id', true );

				$this->from_think_log( 'processPaymentAdded - existing user - Think customer id', $think_customer_id );

				if ( '' == $think_customer_id ) {
					// update user's think_customer_id
					update_user_meta( $user->ID, 'think_customer_id', $data->td_customer->customer_id );
					$this->from_think_log( 'processPaymentAdded - existing user - UPDATE THINK customer id', $user );
				} else {

					if ( $think_customer_id != $data->td_customer->customer_id ) {

						$this->from_think_log( 'processPaymentAdded - error duplicate users', $data );
						$this->_errors['payment_added'] = array(
							'issue'          => 'Duplicate THINK users?',
							'haven_think_id' => $think_customer_id,
							'think_think_id' => $data->td_customer->customer_id,
							'haven_user'     => $user,
							'think_data'     => $data
						);

						$this->_sendErrors();

						// do nothing and clear the event
						return true;
					}
				}
			}

		} else {

			$user = get_user_by( 'id', $user_id[0] );
			$this->from_think_log( 'processPaymentAdded - Existing user by think id', $user );
		}

		return $user;
	}

	protected function find_think_recipent( $think_customer_id ){

		$user_id = mq_get_user_from_think_customer_id( $think_customer_id );

		$errors = new stdClass();

		// no user found by think customer id
		if ( empty( $user_id ) ) {

			// ask get data from think.

			global $mqThinkFramework;

			$response = $mqThinkFramework->getCustomer( $think_customer_id );
			$think_user = $response['result'];

			if( !$think_user->customer ){
				// no data
				mqThinkEvent::from_think_log( 'find think donor - error can not find Donor user', $think_customer_id );
				$errors->error = array( 'error can not find Donor user', $think_customer_id );
				return $errors;
			}

			// find by email?
			$user = get_user_by( 'email', $think_user->customer->email );

			if ( ! $user ) {
				// user doesn't exist, create them
				mqThinkEvent::from_think_log( 'processPaymentAdded', 'no Donor user by email' );

				// begin Create a username/user_login
				if ( $think_user->customer->fname == '' && $think_user->customer->lname == '' ) {
					$user_login = sanitize_user( $think_user->customer->email );
				} else {
					$user_login = sanitize_user( strtolower( str_replace( array('&', ' ', '/'), '', $think_user->customer->fname ) ) . strtolower( $think_user->customer->lname{0} ) );
				}

				// If empty, put something in user login so error displayed is ONLY for E-Mail
				if ( $user_login == '' ) {
					$user_login = 'thinkcreated';
				}

				if ( username_exists( $user_login ) ) {
					$integer_suffix = 2;
					while ( username_exists( $user_login . $integer_suffix ) ) {
						$integer_suffix ++;
					}
					$user_login .= $integer_suffix;
				}

				// // end Create a username/user_login
				if(  $think_user->customer->fname == '' || $think_user->customer->lname == '' ){

					if( $think_user->customer->company != '' ){
						$company_name = explode( ' ', $think_user->customer->company );

						if( count( $company_name ) > 1){
							$_POST['first_name'] = $company_name[0];
							$_POST['last_name']  = $company_name[1];
						}else{
							$_POST['first_name'] = $company_name[0];
							$_POST['last_name']  = $company_name[0]{0};
						}
					} else {
						$_POST['first_name'] = 'Firstname';
						$_POST['last_name']  = 'Lastname';
					}


				} else {

					$_POST['first_name'] = $think_user->customer->fname;
					$_POST['last_name']  = $think_user->customer->lname;
				}

				// create the new user
				$new_user_id = register_new_user( $user_login, $think_user->customer->email );

				if ( ! is_wp_error( $new_user_id ) ) {
					// new user created
					mqThinkEvent::from_think_log( 'processPaymentAdded - new Donor user created', $new_user_id );

					// update new user with think_customer_id
					mqThinkEvent::from_think_log( 'processPaymentAdded - Donor update think customer id', $think_customer_id );
					update_user_meta( $new_user_id, 'think_customer_id', $think_customer_id );

					$user = get_user_by( 'id', $new_user_id );

				} else {
					// user create error
					mqThinkEvent::from_think_log( 'processPaymentAdded - error creating Donor user', $new_user_id );
					$errors->error = array( 'error creating new Donor user', $new_user_id, $think_customer_id );

					return $errors;
				}


			} else {
				// existing user
				mqThinkEvent::from_think_log( 'processPaymentAdded - existing Donor user', $user );

				// get the user's THINK customer id to check if existing THINK customer
				$mq_think_customer_id = get_user_meta( $user->ID, 'think_customer_id', true );

				mqThinkEvent::from_think_log( 'processPaymentAdded - existing Donor user - Think customer id', $think_customer_id );

				if ( '' == $mq_think_customer_id ) {
					// update user's think_customer_id
					update_user_meta( $user->ID, 'think_customer_id', $think_customer_id );
					mqThinkEvent::from_think_log( 'processPaymentAdded - existing user - Donor UPDATE THINK customer id', $user );
				} else {

					if ( $mq_think_customer_id != $think_customer_id ) {

						mqThinkEvent::from_think_log( 'processPaymentAdded - Donor error duplicate users', $user );
						$errors->error = array(
							'issue'          => 'Duplicate THINK users?',
							'haven_think_id' => $mq_think_customer_id,
							'think_think_id' => $think_customer_id,
							'haven_user'     => $user
						);

						// do nothing and clear the event
						return $errors;
					}
				}
			}

		} else {

			$user = get_user_by( 'id', $user_id[0] );
		}

		return $user;

	}

}

/* Define a constant that describes our base URI */
define ( 'MQ_SOAP_WS_URI', 'mqsws' );

/**
 * Class mqSoapWS
 *
 * Mequoda SOAP Web Service
 */
class mqSoapWS
{
	/**
	 * intercept the url if a SOAP WS post and proccess
	 */
	public static function intercept_query( )
	{
		global $wp;

		//$mqThinkEvent = mqThinkEvent::getInstance();

		// preg_match('#^'.MQ_SOAP_WS_URI.'/#', $wp->request)
		if ( MQ_SOAP_WS_URI == $wp->request ) {

			ini_set("soap.wsdl_cache_enabled", "0");

			$wsdl = WP_PLUGIN_DIR . '/mequoda-think-framework/resources/ThinkEvent.wsdl';

			$server = new SoapServer($wsdl, array("cache_wsdl" => 0));
			$server->setClass( 'mqThinkEvent' );

			$server->handle( );

			// We can bail out of WP loop now; shutdown hooks will still run
			exit();
		}

	}

}

// intercept the request if a SOAP post/call
add_action('parse_query', array('mqSoapWS', 'intercept_query'));