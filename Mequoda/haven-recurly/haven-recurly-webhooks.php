<?php
/**
 * Created by PhpStorm.
 * User: balbert
 * Date: 6/10/16
 * Time: 4:20 PM

 *
 * Recurly Web Hook info:
 * https://dev.recurly.com/page/webhooks#section-subscription-notifications
 *
 * Recurly PHP Client library:
 * https://dev.recurly.com/page/php
 * https://github.com/recurly/recurly-client-php
 */

/* Define a constant that describes our base URI */
// mqrwh - "Mequoda Recurly Web Hook"
define ( 'MQ_RECURLY_URI', 'mqrwh' );


/**
 * Class mqRecurlyWebhook
 *
 * Mequoda Recurly Webhook
 */
class mqRecurlyWebhook
{
	private $_errors = array();

	public function __construct() {
		// intercept the request if a Recurly Webhook post/call
		add_action('parse_query', array($this, 'intercept_query'));
	}

	/**
	 * Logger to from-recurly.log. outputs WS actions on event calls from Recurly Webhooks
	 *
	 * @param $title - message title
	 * @param $data - data to output. array/object or text
	 */
	public function from_recurly_log( $title, $data ){

		//$where = WP_PLUGIN_DIR . '/haven-recurly/logs/from-recurly.log';
		$where = '/home/cabotwealth.com/logs/from-recurly.log';

		$when = date('Y-m-d H:i:s');
		if( is_array( $data ) || is_object( $data ) ){
			error_log("[{$when}] {$title}: " . print_r( $data, 1 ) . "\n",3, $where );
		} else {
			error_log("[{$when}] {$title}: {$data}" . "\n",3, $where );
		}

	}

	/**
	 * intercept the url if a Recurly Web Hook post and proccess
	 */
	public function intercept_query( )
	{
		global $wp;

		if ( MQ_RECURLY_URI == $wp->request ) {

			global $mqRecurly;

			// Recurly will POST an XML payload to your URL that you designate
			// in your webhooks configuration

			//Get the XML Payload
			$post_xml = file_get_contents ( "php://input" );
			$notification = new Recurly_PushNotification( $post_xml) ;

			$this->from_recurly_log( 'notification type', $notification->type );



//			//each webhook is defined by a type
			switch ($notification->type) {

				case "renewed_subscription_notification":

					$this->from_recurly_log( $notification->type . ' XML', $post_xml );
					$this->from_recurly_log( $notification->type . ' DATA', $notification );

					// look up the subscription uuid (recurly_uuid) and invoice number (recurly_invoice_number)
					$subscription_uuid      = $notification->subscription->uuid->__toString();
					$current_period_ends_at = $notification->subscription->current_period_ends_at->__toString();

					//find the existing order
					$orders = $this->get_order_from_recurly_subscription( $subscription_uuid );

					if ( !empty( $orders ) ) {

						// update the subscription as a renewal

						// update Recurly Table. Mainly for TSI QF export reports
						//@todo do we need to record this? more for TSI then Cabot.
						//$update_recurly = $this->record_recurly_webhook( $post_xml, $notification, $orders[0] );

						// don't update expire dates if TSI
						if ( 'yes' != $mqRecurly->getSetting('tsi') ) {
							$params['itemId']                 = $orders[0]->id;
							$params['user_id']                = $orders[0]->user_id;
							$params['current_period_ends_at'] = $current_period_ends_at;
							meq_record_access( $params );
						}


					} else {

						// No order found for supplied subscription.
						$this->_errors = array(
							'error' => 'No order found for subscription ID',
							'web_hook' => $notification->type,
							'subscription_id' => $subscription_uuid,
							'post_xml' => $post_xml
						);

						$this->_sendErrors();
					}

					break;


				case "successful_payment_notification":

					$this->from_recurly_log( $notification->type . ' XML', $post_xml );
					$this->from_recurly_log( $notification->type . ' DATA', $notification );

					// look up the subscription uuid (recurly_uuid) and invoice number (recurly_invoice_number)
					$subscription_uuid = $notification->transaction->subscription_id->__toString();
					$invoice_number    = $notification->transaction->invoice_number->__toString();
					$payment           = $notification->transaction->amount_in_cents->__toString() / 100;

					$orders = $this->get_order_from_recurly_subscription( $subscription_uuid );

					//check if order was found. throw error email if not
					if ( !empty( $orders ) ) {

						$renewal = true;
						foreach( $orders as $order ){

							// if invoice_number new for given subscription uuid, then it's are renewal
							if( $order->recurly_invoice_number == $invoice_number ){
								$renewal = false;
							}

						}

						if ( $renewal ) {
							// update Recurly Table. Mainly for TSI QF export reports
							$update_recurly = $this->record_recurly_webhook( $post_xml, $notification, $orders[0] );

							// update order table with new Invoice Number??
							//$updated_order = $this->update_order( $order[0], $invoice_number, $payment );

							$dt = new DateTime( $notification->transaction->date->__toString() );
							$dt->setTimeZone(new DateTimeZone('America/New_York'));
							$date = $dt->format('Y-m-d H:i:s');

							// enter new renewal order
							$record_renewal = $this->record_renewal_order( $orders[0], $invoice_number, $payment, $date );

							// track renewal order
							$track_args        = array(
								'itemId'    => $orders[0]->id,
								'type'      => 'order-renewed',
								'type_desc' => 'Order Renewed',
								'user_id'   => $orders[0]->user_id
							);
							$track_transaction = new mqTransactionTracker();
							$track_transaction->addTransaction( $track_args );


						} else {
							// existing order???
						}

					}

					break;
				case "failed_payment_notification":

					$this->from_recurly_log( $notification->type . ' XML', $post_xml );
					$this->from_recurly_log( $notification->type . ' DATA', $notification );

					// if we got failed_payment_notification then card couldn't be charged.

					// lookup the subscription to see if we already know about the failure.
					// recurly_result = 0, recurly_message != 'success'
					$subscription_uuid = $notification->transaction->subscription_id->__toString();

					$orders = $this->get_order_from_recurly_subscription( $subscription_uuid );

					//check if order was found. throw error email if not
					if ( $orders ) {

						$cancel = false;
						foreach( $orders as $order ){

							// find the original order to cancel
							if( empty($order->correlation_id) || $order->correlation_id != '' ){
								$order_to_cancel = $order;
								$cancel = true;
							}

						}

						// if not already there, then expire the subscription for lack of payment?
						if ( $cancel ) {

							// Record the Web Hook
							$update_recurly = $this->record_recurly_webhook( $post_xml, $notification, $order_to_cancel );

							// cancels them now... if NOT TSI
							if ( 'yes' != $mqRecurly->getSetting('tsi') ) {
								do_action( 'recurly_failed_payment', array( 'original_order' => $order_to_cancel ) );
							}


						} else {
							// existing order???
						}
					} else {

						// No order found for supplied subscription.
						$this->_errors = array(
							'error' => 'No order found for subscription ID',
							'web_hook' => $notification->type,
							'subscription_id' => $subscription_uuid,
							'post_xml' => $post_xml
						);

						$this->_sendErrors();
					}

					break;

				case "expired_subscription_notification":

					$this->from_recurly_log( $notification->type . ' XML', $post_xml );
					$this->from_recurly_log( $notification->type . ' DATA', $notification );

					// look up the subscription uuid (recurly_uuid)
					$subscription_uuid      = $notification->subscription->uuid->__toString();

					//find the existing order
					$orders = $this->get_order_from_recurly_subscription( $subscription_uuid );

					if ( $orders ) {

						$cancel = false;
						foreach( $orders as $order ){

							// find the original order to cancel
							if( empty($order->correlation_id) || $order->correlation_id != '' ){
								$order_to_cancel = $order;
								$cancel = true;
							}

						}

						// if not already there, then expire the subscription for lack of payment?
						if ( $cancel ) {

							// Record the Web Hook
							//$update_recurly = $this->record_recurly_webhook( $post_xml, $notification, $order_to_cancel );

							// update original order and cancel it
							global $wpdb;
							$table = 'wp_mequoda_orders';
							$result = $wpdb->update(
								$table,
								array(
									'cancelled' => 'y',	// string
									'renewal_notice' => 'n'
								),
								array( 'ID' => $order_to_cancel->id ),
								array(
									'%s', //cancelled
									'%s' // renewal notice

								),
								array( '%d' )
							);

							// cancels them now... if NOT TSI
							if ( 'yes' != $mqRecurly->getSetting('tsi') ) {
								do_action( 'recurly_expired_subscription', array( 'original_order' => $order_to_cancel ) );
							}


						} else {
							// existing order???
						}
					} else {

						// No order found for supplied subscription.
						$this->_errors = array(
							'error' => 'No order found for subscription ID',
							'web_hook' => $notification->type,
							'subscription_id' => $subscription_uuid,
							'post_xml' => $post_xml
						);

						$this->_sendErrors();
					}


					break;
			}

			echo 'success';

			// We can bail out of WP now; shutdown hooks will still run
			exit();
		}

	}

	/**
	 * find the corresponding order in Haven for the Recurly data.
	 *
	 * @param $subscription
	 *
	 * @return array|mixed|null|object|void
	 */
	public function get_order_from_recurly_subscription( $subscription ){

		global $wpdb;

		$sql = $wpdb->prepare( "select * from wp_mequoda_orders where recurly_uuid = %s ORDER BY order_time", $subscription );

		$orders = $wpdb->get_results( $sql );

		return $orders;

	}

	/**
	 * update the current order record with the new invoice number for next time
	 *
	 * @param $order_id
	 * @param $invoice_number
	 *
	 * @return false|int
	 */
	public function update_order( $order_id, $invoice_number, $payment ){

		global $wpdb;

		$result = $wpdb->update(
			'wp_mequoda_orders',
			array(
				'recurly_invoice_number' => $invoice_number,
				'payment' => $payment
			),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%s' )
		);

		return $result;
	}

	public function record_renewal_order( $original_order, $invoice_number, $payment, $date ){

		global $wpdb;

		$order_data = (array) $original_order;

		// remove the id of original order
		unset( $order_data['id'] );
		unset( $order_data['order_summary'] );

		// update invoice number with renewal invoice
		$order_data['recurly_invoice_number'] = $invoice_number;
		// set date to transaction date
		$order_data['order_time'] = $date;
		// update order type to "r" for renewal
		$order_data['order_type'] = 'r';
		// update payment amount to renewal payment
		$order_data['payment'] = $payment;
		// set the correlation id to original order
		$order_data['correlation_id'] = $original_order->id;

		$result = $wpdb->insert(
			'wp_mequoda_orders',
			$order_data
		);

		return $result;

	}

	/**
	 * Store the web hook in the DB
	 * (mainly for TSI QuickFill AutoRenew export CSV ;-)
	 *
	 * @param $post_xml
	 * @param $notification
	 * @param $order
	 *
	 * @return false|int
	 */
	public function record_recurly_webhook(  $post_xml, $notification, $order  ){

		global $wpdb;

		$first_payment = 'n';
		if( $order->payment == '' ){
			$first_payment = 'y';
		}

		$dt = new DateTime( $notification->transaction->date->__toString() );
		$dt->setTimeZone(new DateTimeZone('America/New_York'));
		$date = $dt->format('Ymd');

		$result = $wpdb->insert(
			'wp_mequoda_recurly_webhooks',
			array(
				'post_xml'        => $post_xml,
				'type'            => $notification->type,
				'invoice_number'  => $notification->transaction->invoice_number->__toString(),
				'subscription_id' => $notification->transaction->subscription_id->__toString(),
				'transaction_id'  => $notification->transaction->id->__toString(),
				'action'          => $notification->transaction->action->__toString(),
				'date'            => $date,
				'amount_in_cents' => $notification->transaction->amount_in_cents->__toString(),
				'status'          => $notification->transaction->status->__toString(),
				'account_code'    => $notification->account->account_code->__toString(),
				'email'           => $notification->account->email->__toString(),
				'first_name'      => $notification->account->first_name->__toString(),
				'last_name'       => $notification->account->last_name->__toString(),
				'order_id'        => $order->id,
				'user_id'         => $order->user_id,
				'product_id'      => $order->product_id,
				'offer_id'        => $order->offer_code,
				'price'           => $order->price,
				'term'            => $order->term,
				'first_payment'   => $first_payment
			),
			array(
				'%s', // post_xml
				'%s', // type
				'%s', // invoice_number
				'%s', // subscription_id
				'%s', // transaction_id
				'%s', // action
				'%s', // date
				'%s', // amount_in_cents
				'%s', // status
				'%s', // account_code
				'%s', // email
				'%s', // first_name
				'%s', // last_name
				'%d', // order_id
				'%d', // user_id
				'%s', // product_id
				'%s', // offer_id
				'%s', // price
				'%s', // term
				'%s'  // first_payment
			)
		);

		return $result;
	}

	/**
	 * Send the error to me
	 */
	private function _sendErrors( ){

		global $mqRecurly;

		$site = strtoupper( strtok( $mqRecurly->getSetting('plan_id_prefix'), '-' ) );

		$message = print_r( $this->_errors, 1 );

		wp_mail( 'bob@mequoda.com', 'Reculry Error - ' . $site, $message );

	}

}

// Instantiate our class
$mqRecurlyWebhook = new mqRecurlyWebhook();