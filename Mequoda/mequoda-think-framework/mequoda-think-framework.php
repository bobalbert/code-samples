<?php
/**
 * Plugin Name: Mequoda THINK Framework
 * Plugin URI: http://www.mequoda.com/
 * Description: THINK Subscriptions XML API integration.
 * Version: 0.1
 * Author: Bob Albert <bob@superstan.com>
 * Author URI: http://www.mequoda.com/
 */

// load the SOAP web service
require_once( WP_PLUGIN_DIR . '/mequoda-think-framework/mequoda-think-framework-ws.php' );

// load the helper functions
require_once( WP_PLUGIN_DIR . '/mequoda-think-framework/mequoda-think-framework-functions.php' );

class mqThinkFramework
{
	/**
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * Static property to hold our singleton instance
	 * @var mqThinkFramework
	 */
	static $instance = false;

	/**
	 * @var string
	 */
	private $_optionsName = 'meq-think-framework';

	/**
	 * @var string
	 */
	private $_optionsGroup = 'meq-think-framework-options';

	private $_typeTable = 'mequoda_think_source_codes';

	/**
	 * Countryside Address Types as defined in THINK
	 * @var array
	 */
	public $address_types = array(
		'Business',
		'Institution',
		'Library',
		'Other',
		'Residence'
	);

	/*
	 * MQ payment types to THINK CC mapping
	 * @var array
	 */
	public $card_type = array(
			'AMEX'			=> 'AX',
			'MASTERCARD'	=> 'MC',
			'VISA'			=> 'VS',
			'DISCOVER'		=> 'DS',
			'DISCOVERCARD'	=> 'DS',
			'DS'            => 'DS',
			'MC'            => 'MC',
			'AMERICANEXPRESS' => 'AX'
	);

	private $_cc_expire_date;

	private $_cc_number;

	private $_errors = array();

	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @return mqWhatCountsSync
	 */
	function __construct() {

		$this->_getSettings();

		// fake expire date for THINK. They just need something. data stored not in THINK
		$this->_cc_expire_date = date( 'Y-m-d', strtotime( '+1 years' ) );
		// fake card number for THINK. They just need something. data stored not in THINK
		$this->_cc_number = '4111111111111111';


		/**
		 * Add filters and actions
		 */
		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array($this,'adminMenu') );
		register_activation_hook( __FILE__, array( $this, 'activatePlugin' ) );

		/* add actions for after successful order, push data to THINK */

		// send subscription orders to THINK
		add_action( 'mequoda-ordered', array( $this, 'processOrder' ) );
		add_action( 'mequoda-ordered-manual', array( $this, 'processManualOrder' ) );

		//send renewal orders to THINK
		add_action( 'mequoda-ordered-renewal', array( $this, 'processRenewalOrder' ) );


		//send expire date changes to THINK
		add_action( 'meq-update-expire', array( $this, 'processExpireDateChange' ), 10, 2 );

		// just a refund
		add_action( 'mequoda-refund', array( $this, 'processRefund' ) );

		// cancel order immediately or at end. issue refund or not.
		add_action( 'think-cancel-at-end', array( $this, 'processCancelAtEnd' ) );
		add_action( 'think-cancel-immediately', array( $this, 'processCancelImmediately' ), 10, 2 );

		// update data email in THINK if changed in Haven
		add_action( 'profile_update', array( $this, 'editCustomerEmail' ), 10, 2 );

		add_action( 'think-add-auto-renew', array( $this, 'processAddAutoRenewal') );

		// if shopp purchase is for existing user, add shopp_order_success action
		if( is_user_logged_in() ){
			add_action( 'shopp_order_success', array( $this, 'processShoppOrder' ) );
		} else {
			// if shopp purchase for new user, add shopp_customer_registered action
			add_action( 'shopp_customer_registered', array( $this, 'processShoppOrder' ), 15 );
		}


	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * returns it.
	 *
	 * @return mqThinkFramework
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function activatePlugin() {

		global $wpdb;

		update_option( $this->_optionsName, $this->_settings);

		require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

		// Create THINK source codes mapping table if needed
		dbDelta( "CREATE TABLE `{$wpdb->prefix}{$this->_typeTable}` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`source_code_id` varchar(25) NOT NULL,
			`source_code` varchar(255) NOT NULL,
			`description` varchar(255),
			`order_class_id` int(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			KEY `order_class_id` (`order_class_id`)
			);"
		);


	}

	private function _getSettings() {
		if (empty($this->_settings)) {
			$this->_settings = get_option( $this->_optionsName );
		}
		if ( !is_array( $this->_settings ) ) {
			$this->_settings = array();
		}
		$defaults = array(
			'service_url'            => '',
			'wsdl_url'               => '',
			'user_id'                => '',
			'user_password'          => '',
			'dsn'                    => '',
			'doc_ref_id'             => '',
			'source_code_id'         => '905'
		);
		$this->_settings = wp_parse_args($this->_settings, $defaults);
	}

	public function getSetting( $settingName, $default = false ) {
		if (empty($this->_settings)) {
			$this->_getSettings();
		}
		if ( isset($this->_settings[$settingName]) ) {
			return $this->_settings[$settingName];
		} else {
			return $default;
		}
	}

	public function registerOptions() {
		/**
		 * @todo Remove once this supports only 2.7+
		 */
		if ( function_exists('register_setting') ) {
			register_setting( $this->_optionsGroup, $this->_optionsName );
		}
	}

	public function adminMenu() {
		add_options_page(__('THINK Framework Settings'), __('THINK Framework'), 'manage_options', 'mq-think-framework-settings', array($this, 'options'));
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
?>
		<style type="text/css">
			#mq_whatcounts_sync table tr th a {
				cursor:help;
			}
			.large-text{width:99%;}
			.regular-text{width:25em;}
		</style>
		<div class="wrap">
			<h2><?php _e('THINK Framework Settings') ?></h2>

			<form action="options.php" method="post" id="mq_think_framework">
<?php
		/**
		 * @todo Use only settings_fields() once this supports only 2.7+
		 */

		if ( function_exists('settings_fields') ) {
			settings_fields( $this->_optionsGroup );
		} else {
			wp_nonce_field('update-options');
?>
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="<?php echo $this->_optionsName; ?>" />
<?php
		}
?>
				<table class="form-table">

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_service_url">
								<?php _e('Service URL:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[service_url]" value="<?php echo esc_attr($this->_settings['service_url']); ?>" id="<?php echo $this->_optionsName; ?>_service_url" class="regular-text code" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_wsdl_url">
								<?php _e('WSDL URL:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[wsdl_url]" value="<?php echo esc_attr($this->_settings['wsdl_url']); ?>" id="<?php echo $this->_optionsName; ?>_wsdl_url" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_user_id">
								<?php _e('User ID:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[user_id]" value="<?php echo esc_attr($this->_settings['user_id']); ?>" id="<?php echo $this->_optionsName; ?>_user_id" class="regular-text code" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_user_password">
								<?php _e('User Password:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[user_password]" value="<?php echo esc_attr($this->_settings['user_password']); ?>" id="<?php echo $this->_optionsName; ?>_user_password" class="regular-text code" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_dsn">
								<?php _e('DSN:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[dsn]" value="<?php echo esc_attr($this->_settings['dsn']); ?>" id="<?php echo $this->_optionsName; ?>_dsn" class="regular-text code" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_doc_ref_id">
								<?php _e('Doc Ref ID:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[doc_ref_id]" value="<?php echo esc_attr($this->_settings['doc_ref_id']); ?>" id="<?php echo $this->_optionsName; ?>_doc_ref_id" class="regular-text code" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_error_email">
								<?php _e('Error Email:', 'think-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[error_email]" value="<?php echo esc_attr($this->_settings['error_email']); ?>" id="<?php echo $this->_optionsName; ?>_error_email" class="regular-text code" /><br />
							<small id="tdc_error_email">
								<?php _e('This is a comma-separated list of email addresses that will receive a message for each THINK API error.', 'think-framework'); ?>
							</small>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php _e('Web Service Logging Mode', 'think-framework') ?>
						</th>
						<td>
							<input type="radio" name="<?php echo $this->_optionsName; ?>[wslogging]" value="on" id="<?php echo $this->_optionsName; ?>_logging-on"<?php checked('on', $this->_settings['wslogging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-on"><?php _e('On', 'think-framework'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[wslogging]" value="off" id="<?php echo $this->_optionsName; ?>_logging-off"<?php checked('off', $this->_settings['wslogging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-off"><?php _e('Off', 'think-framework'); ?></label><br />
							<small id="tdc_debugging">
								<?php _e('If this is on, THINK events will NOT be processed and just logged in /logs/from-think.log.', 'think-framework'); ?>
							</small>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php _e('Debugging Mode', 'think-framework') ?>
						</th>
						<td>
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="on" id="<?php echo $this->_optionsName; ?>_debugging-on"<?php checked('on', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-on"><?php _e('On', 'think-framework'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="off" id="<?php echo $this->_optionsName; ?>_debugging-off"<?php checked('off', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-off"><?php _e('Off', 'think-framework'); ?></label><br />
							<small id="tdc_debugging">
								<?php _e('If this is on, debugging messages will be sent to the email addresses set below.', 'think-framework'); ?>
							</small>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_debugging_email">
								<?php _e('Debugging Email', 'tdc-framework') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[debugging_email]" value="<?php echo esc_attr($this->_settings['debugging_email']); ?>" id="<?php echo $this->_optionsName; ?>_debugging_email" class="regular-text" /><br />
							<small id="tdc_debugging_email">
								<?php _e('This is a comma-separated list of email addresses that will receive debug messages.', 'think-framework'); ?>
							</small>
						</td>
					</tr>

				</table>

				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update Settings &raquo;'); ?>" />
				</p>
			</form>
		</div>

		<div>
			<h3><?php _e('THINK Source Codes') ?></h3>
			<p>Use the following to update the THINK Source Code mapping data. This should be run anytime a new source code is added to THINK.</p>

		<?php

		if( 'sourcecodes' == $_POST['action'] ){

			global $mqThinkFramework;

			$result = $mqThinkFramework->getSourceCodeLookup();

			if( 'updated' == $result ){
				echo '<span style="color:green;font-weight:bold">Source codes updated!</span>';
			} else {
				echo '<span style="color:red;font-weight:bold">Something went wrong. Check with Mequoda Support.</span>';
			}

		}

		?>

			<form action="/wp-admin/options-general.php?page=mq-think-framework-settings" method="post" id="mq_think_source_codes">
				<input type="hidden" name="action" value="sourcecodes" />

				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update THINK Source Codes'); ?>" />
				</p>
			</form>
		</div>
<?php
	}


	/**
	 * process a Haven order for THINK
	 *
	 * called as part of do_action('mequoda_ordered') in mequoda-order-process.php
	 * action registered as part of __construct() method
	 *
	 * @param array $order_data - associative array with keys of user_id itemId
	 *      user_id - wp user_id of the purchaser
	 *      itemID - id of the order
	 *      like array('user_id' => 2341, 'itemId' => 227657)
	 *
	 * @return bool
	 */
	public function processOrder( $order_data ){

		$this->to_think_log( 'proccssOrder - begin', $order_data);

		$user_id  = $order_data['user_id'];
		$order_id = $order_data['itemId'];

		/* @todo maybe remove following and add the remove_action instead to na_mqShopp::track_order (na-mequoda-shopp.php)???
		*       remove_action( 'mequoda-ordered', array( $this, 'processOrder' ) ); */
		// If this is a ShoppOrder then short circuit and bail out.
		if( strpos( $order_data['itemId'], 'shopp' ) !== false  ){
			$this->to_think_log( 'proccssOrder - ShoppOrder bail', $order_data);
			return true;
		}

		// get the user's THINK customer id to check if existing THINK customer
		$think_customer_id = get_user_meta( $user_id, 'think_customer_id', true);

		// Source Code...
		$mqSourceTracking = mqSourceTracking::getInstance();
		$mqSourceTracking->logSource();
		$haven_source_code = $mqSourceTracking->getCurrentSourceCode();

		/* setup data for subscription order */

		// get the order data and offer's external_id (ie subscription_def_id)
		$order = $this->getOrderData( $order_id );

		// get THINK order_code_id for product
		$product_id = $order->product_id;
		$order_code_id = get_post_meta ( $product_id, 'think_order_code', true );

		// get the THINK subscription_def_id
		$subscription_def_ids = explode( ',', str_replace( ' ', '', $order->subscription_def_id ) );
		$subscription_def_id = $subscription_def_ids[0];

		// If more than id, the second one is a different order code
		$source_code_id = false;
		if ( count( $subscription_def_ids ) > 1 ) {
			$source_code_id = $subscription_def_ids[1];
		}

		if( empty( $order_code_id ) || '' == $order_code_id ){
			$this->_errors['order_code_id'] = 'Empty THINK order_code_id';
		}
		if( empty( $subscription_def_id ) || '' == $subscription_def_id ){
			$this->_errors['subscripiton_def_id'] = 'Empty THINK subscription_def_id';
		}

		// if international address, then update state with country code
		if( $order->country != 'CA' && $order->country != 'US' && $order->country != 'USA'){
			$country_state_code = mq_country_to_think_state( $order->country );
			$order->state = $country_state_code;
		}

		$address_check = $this->confirmAddress( $order );
		if( !$address_check ){
			$this->_errors['address_error'] = "No address in the order. Can't process.";
		}

		// is this an auto renewal order?
		$auto_renewal = $order->auto_renew;

		$args = array(
			'customer_id' => $think_customer_id,
			'order_code_id' => $order_code_id,
			'subscription_def_id' => $subscription_def_id,
			'order_data' => $order,
			'auto_renewal' => $auto_renewal,
			'haven_source_code' => $haven_source_code
		);

		if( $source_code_id ){
			$args['think_source_code'] = $source_code_id;
		}


		if( empty( $this->_errors ) && $address_check ){
			if ( $think_customer_id != '' ) {
				// existing THINK customer
				$this->to_think_log( 'proccssOrder - existing customer', $args);

				if ( '0.00' != $order->payment ) {
					$result = $this->addOrderPayment( $args );
				} else {
					$result = $this->addOrder( $args );
				}

			} else {
				// new THINK customer
				$this->to_think_log( 'proccssOrder - new customer', $args);

				if ( '0.00' != $order->payment  ) {
					$result = $this->addCustomerOrderPayment( $args );
				} else {
					$result = $this->addCustomerOrder( $args );
				}
			}
		} else {
			$this->_errors['order_id'] = $order_id;
			$this->_errors['order'] = $order;
			$this->_sendErrors();
		}

		$this->to_think_log( 'proccssOrder - end', 'done');

		return;
	}


	/**
	 * process a Haven Shopp order for THINK
	 *
	 * called as part of do_action('shopp_order_success') and ('shopp_customer_registered') in shopp plugin
	 * action registered as part of __construct() method
	 *
	 * @param obj $shopp_data - shopp purchase or customer object passed in action
	 *
	 * @return bool
	 */
	public function processShoppOrder( $shopp_data ){

		$this->to_think_log( 'proccssShoppOrder - begin', $shopp_data);

		// if this s a shop order, then remove the other processOrder Action
		remove_action( 'mequoda-ordered', array( $this, 'processOrder' ) );

		// get the data object type that was passed so we know how to process
		$shopp_class = get_class( $shopp_data );
		$this->to_think_log( 'proccssShoppOrder - begin type', $shopp_class );

		if( 'ShoppPurchase' == $shopp_class ){
		/* if ShoppPurchase object, then this is shopp_order_success action */

			// get the wp user id from shopp Customer Object
			$Customer = shopp_customer( $shopp_data->customer );
			$user_id = $Customer->wpuser;

			// Purchase is the action passed data
			//$purchase = ShoppPurchase( $shopp_data->id );
			$purchase = shopp_order( $shopp_data->id );

		} else if( 'ShoppCustomer' == $shopp_class ){

			// get the current purchase for user
			$purchase = ShoppPurchase();
			//$purchase = shopp_order( $shopp_data->id );

			// lookup user by email
			$user = get_user_by( 'email', $purchase->email );
			$user_id = $user->ID;

			$purchase->user_id = $user_id;

		} else {
		/* if no matching object, then something is wrong :-( */
			$this->to_think_log( 'proccssShoppOrder - something wrong, no matching class', $shopp_data);
			return false;
		}

		$this->to_think_log( 'proccssShoppOrder - purchase data', $purchase);

		// get the user's THINK customer id to check if existing THINK customer
		$think_customer_id = get_user_meta( $user_id, 'think_customer_id', true);
		$this->to_think_log( 'proccssShoppOrder - think_customer_id', $think_customer_id);

		// Source Code...
		$mqSourceTracking = mqSourceTracking::getInstance();
		$mqSourceTracking->logSource();
		$haven_source_code = $mqSourceTracking->getCurrentSourceCode();

		//set up the data for each item for THINK
		$think_items = array();
		foreach( $purchase->purchased as $purchased_item ){
			$shopp_product_id    = $purchased_item->product;
			$think_order_code_id = get_post_meta( $shopp_product_id, 'order_code_id', true );
			$think_product_id    = get_post_meta( $shopp_product_id, 'product_id', true );

			// if product is a t-shirt use the sku value for the THINK product_id.
			// @todo consider another value for this type of product - t-shirts, hats and aprons
			if( 't-shirt' == $think_product_id ){
				$think_product_id = $purchased_item->sku;
			}

			if( empty( $think_order_code_id ) || '' == $think_order_code_id ){
				$this->_errors['items'][] = array('think_order_code_id' => 'Empty THINK order_code_id', 'shopp_product_id' => $shopp_product_id );
			}
			if( empty( $think_product_id ) || '' == $think_product_id ){
				$this->_errors['items'][] = array('subscripiton_def_id' => 'Empty THINK subscription_def_id', 'shopp_product_id' => $shopp_product_id );
			}

			$unit_price = $purchased_item->total;
			$quantity   = $purchased_item->quantity;

			$think_items[] = array(
				'order_code_id' => $think_order_code_id,
				'product_id' => $think_product_id,
				'unit_price' => $unit_price,
				'quantity' => $quantity
			);
		}

		//@todo if missing order_code and/or product_id send ERROR?

		// setup args for THINK call
		$args = array(
			'customer_id' => $think_customer_id,
			'order_data' => $purchase,
			'think_items' => $think_items,
			'haven_source_code' => $haven_source_code
		);

		$this->to_think_log( 'proccssShoppOrder - THINK args', $args);

		if ( empty( $this->_errors ) ) {
			if ( $think_customer_id != '' ) {
				// existing THINK customer
				$this->to_think_log( 'proccssShoppOrder - existing customer', $args );

				$response = $this->addShoppOrderPayment( $args );

			} else {
				// new THINK customer
				$this->to_think_log( 'proccssShoppOrder - new customer', $args );

				$response = $this->addShoppCustomerOrderPayment( $args );
			}
		} else {

			$this->_errors['shopp_order'] = $purchase->id;
			$this->_sendErrors();

			$response = $this->_sendErrors();
		}

		$this->to_think_log( 'proccssShoppOrder - end', $response);

		return true;
	}

	/**
	 * process a Haven order for THINK
	 *
	 * called as part of do_action('mequoda_ordered-manual') in mequoda-order-process.php
	 * action registered as part of __construct() method
	 *
	 * @param array $order_data - associative array with keys of manual order data
	 *      user_id - wp user_id of the purchaser
	 *      order_id - id of the order
	 *      donor_id - if gift order the donor's user id
	 *      think_source_code - source code entered by agent in form
	 *      like array('user_id' => 2341, 'order_id' => 227657, ...)
	 *
	 * @return bool
	 */
	public function processManualOrder( $order_data ){
		$this->to_think_log( 'processManualOrder - begin', $order_data);

		$user_id           = $order_data['user_id'];
		$donor_id          = $order_data['donor_id'];
		$order_id          = $order_data['order_id'];
		$think_source_code = $order_data['think_source_code'];

		// get the user's THINK customer id to check if existing THINK customer
		$think_customer_id = get_user_meta( $user_id, 'think_customer_id', true);

		$gift_order = false;
		if( ! empty( $donor_id ) ){
			$donor_think_customer_id = get_user_meta( $donor_id, 'think_customer_id', true);
			$donor_data = array_map( function( $a ){ return $a[0]; }, get_user_meta( $donor_id ) );
			$gift_order = true;

			// if known THINK customer get their info
			if( $donor_think_customer_id != '' ){
				$get_cust_response = $this->getCustomer( $donor_think_customer_id );
				$donor_think_info = $get_cust_response['result'];
			} else {
				$donor_think_info = false;
			}
		}

		// Source Code...
		$mqSourceTracking = mqSourceTracking::getInstance();
		$mqSourceTracking->logSource();
		$haven_source_code = $mqSourceTracking->getCurrentSourceCode();

		/* setup data for subscription order */

		// get the order data and offer's external_id (ie subscription_def_id)
		$order = $this->getOrderData( $order_id );

		// get THINK order_code_id for product
		$product_id = $order->product_id;
		$order_code_id = get_post_meta ( $product_id, 'think_order_code', true );

		// get the THINK subscription_def_id
		$subscription_def_ids = explode( ',', str_replace( ' ', '', $order->subscription_def_id ) );
		$subscription_def_id = $subscription_def_ids[0];

		// if international address, then update state with country code
		if( $order->country != 'CA' && $order->country != 'US'  && $order->country != 'USA' ){
			$country_state_code = mq_country_to_think_state( $order->country );
			$order->state = $country_state_code;
		}

		/* Check we have the THINK data we need */
		if( empty( $order_code_id ) || '' == $order_code_id ){
			$this->_errors['order_code_id'] = 'Empty THINK order_code_id';
		}
		if( empty( $subscription_def_id ) || '' == $subscription_def_id ){
			$this->_errors['subscripiton_def_id'] = 'Empty THINK subscription_def_id';
		}
		$address_check = $this->confirmAddress( $order );
		if( !$address_check ){
			$this->_errors['address_error'] = "No address in the order. Can't process.";
		}
		/* end THINK data check */

		/* send errors if missing THINK data */
		if( !empty( $this->_errors ) ){
			$this->_errors['manual_order_id'] = $order_id;
			$this->_sendErrors();

			$this->to_think_log( 'processManualOrder - Manual Order Error', $this->_errors );
			// end can't process order
			return;
		}

		// is this an auto renewal order?
		$auto_renewal = $order_data['auto_renew'];

		$args = array(
			'customer_id'             => $think_customer_id,
			'order_code_id'           => $order_code_id,
			'subscription_def_id'     => $subscription_def_id,
			'order_data'              => $order,
			'auto_renewal'            => $auto_renewal,
			'think_source_code'       => $think_source_code,
			'haven_source_code'       => $haven_source_code,
			'donor_think_customer_id' => $donor_think_customer_id,
			'donor_think_info'        => $donor_think_info
		);

		if ( $gift_order ) {

			// all gift orders are not auto renewal regardless of what agent set in form
			$args['auto_renewal'] = '0';

				if ( $think_customer_id != '' ) {
					// existing THINK customer
					$this->to_think_log( 'processManualOrder GIFT - existing customer', $args );

					$gift_result = $this->addOrder( $args );

				} else {
					// new THINK customer
					$this->to_think_log( 'processManualOrder GIFT - new customer', $args );

					$gift_result = $this->addCustomerOrder( $args );
				}

				// get the Order HDR ID for payment
				$orderhdr_id   = $gift_result['result']->orderhdr->orderhdr_id;
				$order_item_seq = $gift_result['result']->orderhdr->order_item->order_item_seq;

				if( empty( $orderhdr_id ) || '' == $orderhdr_id ){
					$this->_errors['orderhdr_id '] = 'processManualOrder GIFT - No orderhdr_id in order.';
				}
				if( empty( $order_item_seq ) || '' == $order_item_seq ){
					$this->_errors['order_item_seq'] = 'processManualOrder GIFT - No order_item_seq in order.';
				}

				if( !empty( $this->_errors ) ){
					$this->_errors['order_id'] = $order->id;
					$this->_errors['order'] = $order;
					$this->_sendErrors();

					$this->to_think_log( 'processManualOrder GIFT - ERROR', $this->_errors );

					return;
				}

				$donor_order = $order;

				$donor_args = array(
					'customer_id'         => $donor_think_customer_id,
					'order_code_id'       => $order_code_id,
					'subscription_def_id' => $subscription_def_id,
					'order_data'          => $donor_order,
					'auto_renewal'        => '0',
					'think_source_code'   => $think_source_code,
					'haven_source_code'   => $haven_source_code,
					'orderhdr_id'         => $orderhdr_id,
					'order_item_seq'      => $order_item_seq,
					'donor_think_customer_id' => $donor_think_customer_id,
					'donor_think_info'        => $donor_think_info
				);


				if ( $donor_think_customer_id != '' ) {
					// existing THINK customer

					$this->to_think_log( 'processManualOrder GIFT Donor - existing customer', $donor_args );

					$donor_result = $this->addPayment( $donor_args );

				} else {
					// new THINK customer
					$this->to_think_log( 'processManualOrder GIFT Donor - new customer', $donor_args );

					$donor_order->first_name = $donor_data['first_name'];
					$donor_order->last_name  = $donor_data['last_name'];
					$donor_order->address    = $donor_data['address'];
					$donor_order->address2   = $donor_data['address2'];
					$donor_order->city       = $donor_data['city'];
					$donor_order->state      = $donor_data['state'];
					$donor_order->zip        = $donor_data['zip_code'];
					$donor_order->phone      = $donor_data['phone'];
					$donor_order->user_email = $donor_data['user_email'];

					$donor_args['order_data'] = $donor_order;

					/* check address for THINK data */
					$donor_address_check = $this->confirmAddress( $donor_order );
					if ( ! $donor_address_check ) {
						$this->_errors['donor_address_error'] = "Donor Error. No address for donor. Can't create/process payment.";
					}

					if ( empty( $this->_errors ) ) {

						$donor_result = $this->addCustomerPayment( $donor_args );

					} else {

						$this->_errors['manual_order_id'] = $order_id;
						$this->_sendErrors();

						$this->to_think_log( 'processManualOrder - Donor Error', $this->_errors );
						}
					}


		} else {

			if ( $think_customer_id != '' ) {
				// existing THINK customer
				$this->to_think_log( 'processManualOrder - existing customer', $args );

				$result = $this->addOrderPayment( $args );

			} else {
				// new THINK customer
				$this->to_think_log( 'processManualOrder - new customer', $args );

				$result = $this->addCustomerOrderPayment( $args );
			}
		}

		$this->to_think_log( 'processManualOrder - end', $args);

		return;
	}

	public function processRenewalOrder( $order_data ){

		/*
		 * $order_data = array(
				'user_id'			=> $order['user_id'],
				'itemId'			=> $order_id,
				'original_order_id'	=> $order['id'],
				'payment'			=> $args['sfg_transaction_amount'],
			);
		 */

		$this->to_think_log( 'processRenewalOrder - begin', $order_data);

		global $wpdb;

		$user_id           = $order_data['user_id'];
		$renewal_order_id  = $order_data['itemId'];
		$original_order_id = $order_data['original_order_id'];
		$payment           = $order_data['payment'];

		// get the user's THINK customer id to check if existing THINK customer
		$think_customer_id = get_user_meta( $user_id, 'think_customer_id', true);

		// Source Code...
		$mqSourceTracking = mqSourceTracking::getInstance();
		$mqSourceTracking->logSource();
		$haven_source_code = $mqSourceTracking->getCurrentSourceCode();

		/* setup data for subscription order */

		// get the order data and offer's external_id (ie subscription_def_id)
		$order = $this->getOrderData( $original_order_id );

		// reset the local $order data with the renewal payment
		$order->payment = $payment;
		// reset the local $order data for order id of with renewal order id.
		$order->id = $renewal_order_id;

		// get THINK order_code_id for product
		$product_id = $order->product_id;
		$order_code_id = get_post_meta ( $product_id, 'think_order_code', true );
		$think_order_data = json_decode( $order->think_data );

		// get the THINK subscript
		$subscrip_id = $order->think_subscrip_id;
		//$subscrip_id = $think_order_data->subscrip->subscrip_id;

		// get the THINK subscription_def_id
		$subscription_def_ids = explode( ',', str_replace( ' ', '', $order->subscription_def_id ) );
		$subscription_def_id = $subscription_def_ids[0];
		// check if there is a third ID which would denote a different sub def id for different term.
		// initial purchase uses sub def for 3 month trial but then renewal sets term to 1 month
		if( isset( $subscription_def_ids[2] ) ){
			$subscription_def_id = $subscription_def_ids[2];
		}

		// If more than id, the second one is a different order code
		$source_code_id = false;
		if ( count( $subscription_def_ids ) > 1 ) {
			$source_code_id = $subscription_def_ids[1];
		}

		// is this an auto renewal order? I think all Haven orders are. TBD
		$auto_renewal = $order->auto_renew;

		$args = array(
			'customer_id' => $think_customer_id,
			'order_code_id' => $order_code_id,
			'subscription_def_id' => $subscription_def_id,
			'order_data' => $order,
			'auto_renewal' => $auto_renewal,
			'haven_source_code' => $haven_source_code,
			'subscrip_id' => $subscrip_id
		);

		if( $source_code_id ){
			$args['think_source_code'] = $source_code_id;
		}

		// if international address, then update state with country code
		if( $order->country != 'CA' && $order->country != 'US' && $order->country != 'USA' ){
			$country_state_code = mq_country_to_think_state( $order->country );
			$order->state = $country_state_code;
		}

		$address_check = $this->confirmAddress( $order );

		if ( $address_check ) {
			if ( $think_customer_id != '' ) {
				// existing THINK customer

				$this->to_think_log( 'processRenewalOrder - existing customer', $args );

				$result = $this->addOrderPayment( $args );

			} else {
				// new THINK customer

				$this->to_think_log( 'processRenewalOrder - new customer', $args );

				$result = $this->addCustomerOrderPayment( $args );
			}
		}

		$this->to_think_log( 'processRenewalOrder - end', $args);

		return ;
	}

	public function processExpireDateChange( $order, $date ){

		$data = array( $order, $date );
		$this->to_think_log( 'processExpireDateChange - begin', $data );

		// convert date to THINK date format
		$new_date = date( "Y-m-d", strtotime( $date ) );

		// get the THINK data we need for request
		$think_order_data = json_decode( $order->think_data );
		$orderhdr_id      = $think_order_data->orderhdr->orderhdr_id;
		$order_item_seq   = $think_order_data->orderhdr->order_item->order_item_seq;

		// Check we have the THINK data we need, otherwise throw error
		if( empty( $think_order_data ) || '' == $think_order_data ){
			$this->_errors['think_order_data'] = 'Expire Date Change Error - No THINK data in order.';
		}
		if( empty( $orderhdr_id ) || '' == $orderhdr_id ){
			$this->_errors['orderhdr_id '] = 'Expire Date Change Error - No orderhdr_id in order.';
		}
		if( empty( $order_item_seq ) || '' == $order_item_seq ){
			$this->_errors['order_item_seq'] = 'Expire Date Change Error - No order_item_seq in order.';
		}

		// process the expire date change or throw error email.
		if( empty( $this->_errors ) ){
			/* Set your parameters for the request */
			$params = array(
				'submit' => 'yes',
				'item_identifier' => array(
					'orderhdr_id' => $orderhdr_id,
					'order_item_seq' => $order_item_seq
				),
				'item_data' => array(
					'expire_date' => $new_date
				)
			);

			// send request to THINK
			$response = $this->getThinkSubscription( $params, 'OrderItemEdit', $order->id );
		} else {

			$this->_errors['order_id'] = $order->id;
			$this->_errors['order'] = $order;
			$this->_sendErrors();

			$this->to_think_log( 'processExpireDateChange - ERROR', $this->_errors );

		}

		$this->to_think_log( 'processExpireDateChange', 'end' );

		return;
	}

	public function processCancelAtEnd( $order ){

		$this->to_think_log( 'processCancelAtEnd - begin', $order );

		// get the THINK data we need for request
		$think_order_data = json_decode( $order->think_data );
		$orderhdr_id      = $think_order_data->orderhdr->orderhdr_id;
		$order_item_seq   = $think_order_data->orderhdr->order_item->order_item_seq;

		// Check we have the THINK data we need, otherwise throw error
		if( empty( $think_order_data ) || '' == $think_order_data ){
			$this->_errors['think_order_data'] = 'Cancel At End Error - No THINK data in order.';
		}
		if( empty( $orderhdr_id ) || '' == $orderhdr_id ){
			$this->_errors['orderhdr_id '] = 'Cancel At End Error - No orderhdr_id in order.';
		}
		if( empty( $order_item_seq ) || '' == $order_item_seq ){
			$this->_errors['order_item_seq'] = 'Cancel At End Error - No order_item_seq in order.';
		}

		// process the expire date change or throw error email.
		if( empty( $this->_errors ) ){
			/* Set your parameters for the request */
			$params = array(
				'submit' => 'yes',
				"item_identifier" => array(
					'orderhdr_id'    => $orderhdr_id,
					'order_item_seq' => $order_item_seq,
				),
				'item_data' => array(
					// commenting out for now so customers still get renewal notices from THINK. //'renewal_status' => '1',
					'auxiliary_data' => array( 'name' => 'zzaux_autorenewflag', 'text_val' => '0' ),
				)
			);

			// send request to THINK
			$response = $this->getThinkSubscription( $params, 'OrderItemEdit', $order->id );

		} else {

			$this->_errors['order_id'] = $order->id;
			$this->_errors['order'] = $order;
			$this->_sendErrors();

			$this->to_think_log( 'processCancelAtEnd - ERROR', $this->_errors );

		}

		$this->to_think_log( 'processCancelAtEnd', 'end' );

		return;
	}

	public function processCancelImmediately( $order, $refund_amount ){

		$this->to_think_log( 'processCancelImmediately - begin', $order );

		// get the THINK data we need for request
		$think_order_data = json_decode( $order->think_data );
		$orderhdr_id      = $think_order_data->orderhdr->orderhdr_id;
		$order_item_seq   = $think_order_data->orderhdr->order_item->order_item_seq;

		if( $order->donor_id ){
			$think_customer_id = get_user_meta( $order->donor_id, 'think_customer_id', true);
		} else {
			$think_customer_id = $think_order_data->customer->customer_id;
		}

		// Check we have the THINK data we need, otherwise throw error
		if( empty( $think_order_data ) || '' == $think_order_data ){
			$this->_errors['think_order_data'] = 'Cancel immediately Error - No THINK data in order.';
		}
		if( empty( $orderhdr_id ) || '' == $orderhdr_id ){
			$this->_errors['orderhdr_id '] = 'Cancel immediately Error - No orderhdr_id in order.';
		}
		if( empty( $order_item_seq ) || '' == $order_item_seq ){
			$this->_errors['order_item_seq'] = 'Cancel immediately Error - No order_item_seq in order.';
		}

		// process the expire date change or throw error email.
		if( empty( $this->_errors ) ){

			/* Set your parameters for the request */
			$params = array(
				'submit' => 'yes',
				'cancel_data' => array(
					"item_identifier" => array(
						'orderhdr_id'    => $orderhdr_id,
						'order_item_seq' => $order_item_seq,
					),
					'cancel_reason' => 'Haven'
				)
			);

			if( $refund_amount ){
				$params['cancel_data']['amount'] = $refund_amount;

				$params['payment_refund_data'] = array(
					'refund_to_deposit' => 'no',
					'customer_identifier' => array(
						'customer_id' => $think_customer_id
					),
					'amount' => $refund_amount,
					'currency' => 'USD',
					'payment_type' => 'CK',
					'transaction_reason' => 'HAVEN'
				);
			}

			// send request to THINK
			$response = $this->getThinkSubscription( $params, 'OrderCancel', $order->id );

			// get the payment "id" so we can update it.
			$payment_seq_id = $this->getPaymentSeqId( $think_customer_id );

			// update the status of the refund payment to cleared so no check issued.
			if ( empty( $this->_errors ) && $payment_seq_id ) {

				// set payment edit params
				$payment_params = array(
					'payment_edit_data' => array(
						'refund_to_deposit'    => "no",
						'payment_identifier'   => array(
							'customer_identifier' => array(
								'customer_id' => $think_customer_id
							),
							'payment_seq'         => $payment_seq_id
						),
						'payment_clear_status' => '3'
					)
				);

				$payment_response = $this->getThinkSubscription( $payment_params, 'PaymentEdit', $order->id );

			} else {
				// problem getting the payment id, more than one found or non found
				$this->_errors['order_id'] = $order->id;
				$this->_errors['order']    = $order;
				$this->_sendErrors();

				$this->to_think_log( 'processCancelImmediately - ERROR', $this->_errors );
			}


		} else {

			$this->_errors['order_id'] = $order->id;
			$this->_errors['order'] = $order;
			$this->_sendErrors();

			$this->to_think_log( 'processCancelImmediately - ERROR', $this->_errors );

		}

		$this->to_think_log( 'processCancelImmediately', 'end' );

		return;
	}

	function processAddAutoRenewal( $order ){

		$this->to_think_log( 'processAddAutoRenewal - begin', $order );

		// get the THINK data we need for request
		$think_order_data = json_decode( $order->think_data );
		$orderhdr_id      = $think_order_data->orderhdr->orderhdr_id;
		$order_item_seq   = $think_order_data->orderhdr->order_item->order_item_seq;

		// Check we have the THINK data we need, otherwise throw error
		if( empty( $think_order_data ) || '' == $think_order_data ){
			$this->_errors['think_order_data'] = 'Cancel At End Error - No THINK data in order.';
		}
		if( empty( $orderhdr_id ) || '' == $orderhdr_id ){
			$this->_errors['orderhdr_id '] = 'Add Auto Renewal Error - No orderhdr_id in order.';
		}
		if( empty( $order_item_seq ) || '' == $order_item_seq ){
			$this->_errors['order_item_seq'] = 'Add Auto Renewal Error - No order_item_seq in order.';
		}

		// process the expire date change or throw error email.
		if( empty( $this->_errors ) ){
			/* Set your parameters for the request */
			$params = array(
				'submit' => 'yes',
				"item_identifier" => array(
					'orderhdr_id'    => $orderhdr_id,
					'order_item_seq' => $order_item_seq,
				),
				'item_data' => array(
					// commenting out for now so customers still get renewal notices from THINK. //'renewal_status' => '1',
					'auxiliary_data' => array( 'name' => 'zzaux_autorenewflag', 'text_val' => '1' ),
				)
			);

			// send request to THINK
			$response = $this->getThinkSubscription( $params, 'OrderItemEdit', $order->id );

		} else {

			$this->_errors['order_id'] = $order->id;
			$this->_errors['order'] = $order;
			$this->_sendErrors();

			$this->to_think_log( 'processAddAutoRenewal - ERROR', $this->_errors );

		}

		$this->to_think_log( 'processAddAutoRenewal', 'end' );

		return;
	}

	/**
	 * Business function to call/send requests to THINK api
	 *
	 * @param $args - method specific data
	 * @param $apiFunction - api method to call
	 *
	 * @return mixed
	 */
	public function getThinkSubscription( $args, $apiFunction, $order_id = 0 ) {

		$this->to_think_log( 'THINKAPI call start', $apiFunction );

		$service_url   = $this->_settings['service_url']; // https://app4.thinksubscription.com:443/soap.slap
		//$wsdl          = $this->_settings['wsdl_url']; // https://app4.thinksubscription.com/slwebsvc.wsdl
		//temp local version since custom
		$wsdl = dirname(__FILE__) . '/resources/SLWebSvc_mq.wsdl';

		$dsn           = $this->_settings['dsn']; // CM24208_Test
		$doc_ref_id    = $this->_settings['doc_ref_id']; // 4
		$user_id       = $this->_settings['user_id']; // web1
		$user_password = $this->_settings['user_password']; // csy0292

		try {
			$client = new SoapClient( $wsdl, array( 'location' => $service_url, 'trace' => 1 ) );

			/* Base settings/params required for every request */
			$required_params = array(
				"dsn"             => $dsn,
				"doc_ref_id"      => $doc_ref_id,
				"suppress_events" => 'yes',
				"user_login_data" => array( 'login'    => $user_id,
				                            'password' => $user_password
				)
			);

			// add in the data for the specific api method being called
			$params = array_merge( $required_params, $args );

			//error_log( 'THINKAPI call data: ' . print_r( $params, 1 ),3, $this->to_think_log );
			$this->to_think_log( 'THINKAPI call data', $params );

			// call api method
			$result['result']  = $client->$apiFunction( $params );

			$result['order_id'] = $order_id;

			// get the request xml actually sent
			$result['request'] = $client->__getLastRequest();

			//error_log( 'THINKAPI call success: ' . print_r( $result, 1 ),3, $this->to_think_log );
			$this->to_think_log( 'THINKAPI call success', $result );

			return $result;

		} catch (SoapFault $e) {
			//$this->_debug_mail( 'TDC API problem', "Error: " . $e->getMessage() );
			//return  new WP_Error('TDC API problem', __("Unable to retrieve Nutrition Action Healthletter subscriber details."));

			$error['order_id'] = $order_id;

			// log the request was sent for debug
			//$error['request']  = htmlentities( str_ireplace( '><', ">\n<", $client->__getLastRequest() ) );
			$error['request']  = $client->__getLastRequest();

			// log the full xml response for debug
			//$error['response'] = htmlentities( str_ireplace( '><', ">\n<", $client->__getLastResponse() ) );
			$error['response'] = $client->__getLastResponse();
			// SoapClient basic fault message, also in above in full response item
			$error['error']    = $e->getMessage();

			//error_log( 'THINKAPI call error: ' . print_r( $error, 1 ),3, $this->to_think_log );
			$this->to_think_log( 'THINKAPI call error', $error );

			$message =  print_r( $error, 1 );

			if ( ! empty( $this->_settings['error_email'] ) ){
				wp_mail( $this->_settings['error_email'], 'THINKAPI call error', $message );
			} else {
				wp_mail( 'bob@mequoda.com', 'THINKAPI call error', $message );
				//wp_mail( 'bob@mequoda.com,stacey@mequoda.com,dana@mequoda.com', 'THINKAPI call error', $message );
			}
			
			return $error;

		}
	}



	/**
	 * get data for "customer" record in THINK
	 *
	 *
	 * API doc      - customer_info_select
	 * WSDL Method  - CustomerInfoSelect
	 *
	 * @param $user_id
	 */
	public function getCustomer( $customer_id ){

		/* Set your parameters for the request */
		$params = array( 'customer_identifier'  => array( 'customer_id' => $customer_id ) );

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'CustomerInfoSelect' );
		//$response = $this->getThinkSubscription( $params, 'CustomerInformation' );

		return $response;
	}

	/**
	 * Add new "customer" record along with their Order and Payment
	 *
	 * API doc      - customer_add_order_add_payment_add_request
	 * WSDL Method  - CustomerAddOrderAddPaymentAdd
	 *
	 * @param $user_id
	 */
	public function addCustomerOrderPayment( $data ){

		$order_code_id       = $data['order_code_id'];
		$subscription_def_id = $data['subscription_def_id'];
		$order_data          = $data['order_data'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );
		$payment_type     = $this->card_type[ $order_data->payment_type ];
		$payment          = $order_data->payment;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// Temp until CC processor is setup
		/*if( '0.00' == $payment ){
			$payment = $order_data->price;
		}*/
		// END Temp until CC processor is setup

		// generic source code in THINK for all Haven orders
		$source_code_id = $this->_settings['source_code_id'];

		// if manual order use the source code entered in form
		if( $data['think_source_code'] ){
			$source_code_id = $data['think_source_code'];
		}

		/* Set your parameters for the request */
		$params = array(
				"customer_data" => array(
						'fname' => $order_data->first_name,
						'lname' => $order_data->last_name,
						'customer_address_data' => array(
								'address_type' => 'Residence',
								'address1' => $order_data->address,
								'address2' => $order_data->address2,
								'city' => $order_data->city,
								'state' => $order_data->state,
								'zip' => $order_data->zip_code
						),
						'phone' => $order_data->phone,
						'email' => $order_data->user_email
				),
				'order_data' => array(
						'item_data' => array(
								'order_code_id' => $order_code_id,
								'source_code_id' => $source_code_id,
								'subscription_def_id' => $subscription_def_id,
								'auxiliary_data' => array(
									array( 'name' => 'zzaux_autorenewflag', 'text_val' => $data['auto_renewal'] ),
									array( 'name' => 'zzaux_havensourcecode','text_val' => $data['haven_source_code'] )
								),
								'currency' => 'USD',
								'item_amt_break_data' => array( 'order_item_break_type' => 'item', 'local_amount' => $payment )
						)
				),
				'payment_add_data' => array(
						'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
						'payment_type' => $payment_type,
						'card_number' => $cc_number,
						'card_expire_date' => $card_expire_date,
						'amount' => $payment,
						'payment_clear_status' => 3,
						'clear_date' => $clear_date
				)
		);

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'CustomerAddOrderAddPaymentAdd', $order_data->id );

		/* Process the response: **/
		// update user's think_customer_id
		update_user_meta( $order_data->user_id, 'think_customer_id', $response['result']->customer->customer_id );

		// update order record with THINK info
		$update_result = $this->updateThinkOrderData( $order_data->id, $response );

		$result = true;

		return $result;

	}

	/**
	 * Add new "customer" record along with their Order
	 *
	 * API doc      - customer_add_order_add_request
	 * WSDL Method  - CustomerAddOrderAdd
	 *
	 */
	public function addCustomerOrder( $data ){

		$order_code_id       = $data['order_code_id'];
		$subscription_def_id = $data['subscription_def_id'];
		$order_data          = $data['order_data'];
		$donor_think_info    = $data['donor_think_info'];
		$payment             = $order_data->payment;

		// generic source code in THINK for all Haven orders
		$source_code_id = $this->_settings['source_code_id'];

		// if manual order use the source code entered in form
		if( $data['think_source_code'] ){
			$source_code_id = $data['think_source_code'];
		}

		/* Set your parameters for the request */
		$params = array(
			"customer_data" => array(
				'fname' => $order_data->first_name,
				'lname' => $order_data->last_name,
				'customer_address_data' => array(
					'address_type' => 'Residence',
					'address1' => $order_data->address,
					'address2' => $order_data->address2,
					'city' => $order_data->city,
					'state' => $order_data->state,
					'zip' => $order_data->zip_code
				),
				'phone' => $order_data->phone,
				'email' => $order_data->user_email
			),
			'order_data' => array(
				'item_data' => array(
					'order_code_id' => $order_code_id,
					'source_code_id' => $source_code_id,
					'subscription_def_id' => $subscription_def_id,
					'auxiliary_data' => array(
						array( 'name' => 'zzaux_autorenewflag', 'text_val' => $data['auto_renewal'] ),
						array( 'name' => 'zzaux_havensourcecode','text_val' => $data['haven_source_code'] )
					),
					'currency' => 'USD',
					'item_amt_break_data' => array( 'order_item_break_type' => 'item', 'local_amount' => $payment )
				)
			)
		);

		if( $donor_think_info ){

			$params['order_data']['item_data']['bill_to_customer_id'] = $donor_think_info->customer->default_bill_to_customer_id;
			$params['order_data']['item_data']['bill_to_customer_address_seq'] = $donor_think_info->customer->def_bill_to_cust_addr_seq;

			$params['order_data']['item_data']['renew_to_customer_id'] = $donor_think_info->customer->default_renew_to_customer_id;
			$params['order_data']['item_data']['renew_to_customer_address_seq'] = $donor_think_info->customer->def_renew_to_cust_addr_seq;
		}

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'CustomerAddOrderAdd', $order_data->id );

		/* Process the response: **/
		// update user's think_customer_id
		update_user_meta( $order_data->user_id, 'think_customer_id', $response['result']->customer->customer_id );

		// update order record with THINK info
		$update_result = $this->updateThinkOrderData( $order_data->id, $response );

		return $response;

	}

	/**
	 * Add new "customer" record along with their Payment
	 *
	 * API doc      - customer_add_payment_add_request
	 * WSDL Method  - CustomerAddPaymentAdd
	 *
	 */
	public function addCustomerPayment( $data ){

		$orderhdr_id   = $data['orderhdr_id'];
		$order_item_seq = $data['order_item_seq'];
		$order_data     = $data['order_data'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );
		$payment_type     = $this->card_type[ $order_data->payment_type ];
		$payment          = $order_data->payment;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// Temp until CC processor is setup
		/*if( '0.00' == $payment ){
			$payment = $order_data->price;
		}*/
		// END Temp until CC processor is setup

		/* Set your parameters for the request */
		$cust_add_params = array(
			"customer_data" => array(
				'fname' => $order_data->first_name,
				'lname' => $order_data->last_name,
				'customer_address_data' => array(
					'address_type' => 'Residence',
					'address1'     => $order_data->address,
					'address2'     => $order_data->address2,
					'city'         => $order_data->city,
					'state'        => $order_data->state,
					'zip'          => $order_data->zip_code
				),
				'phone' => $order_data->phone,
				'email' => $order_data->user_email
			)
		);

		// send request to THINK
		$cust_add_response = $this->getThinkSubscription( $cust_add_params, 'CustomerAdd', $order_data->id );

		$customer_id = $cust_add_response['result']->customer->customer_id;

		// update user's think_customer_id
		update_user_meta( $order_data->donor_id, 'think_customer_id', $customer_id );

		/* Set your parameters for the request */
		$pay_add_params = array(
			'submit' => 'yes',
			'customer_identifier' => array( 'customer_id' => $customer_id ),
			'payment_add_data' => array(
				'sum_amt_due'             => 'yes',
				'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
				'customer_identifier'     => array( 'customer_id' => $customer_id ),
				'payment_type'            => $payment_type,
				'card_number'             => $cc_number,
				'card_expire_date'        => $card_expire_date,
				'amount'                  => $payment,
				'payment_clear_status'    => 3,
				'clear_date'              => $clear_date
			),
			'item_payment' => array(
				'item_identifier' => array(
					'orderhdr_id' => $orderhdr_id,
					'order_item_seq' => $order_item_seq
				),
				'amount' => $payment
			)
		);

		$add_pay_result = $this->getThinkSubscription( $pay_add_params, 'PaymentAdd', $order_data->id );

		$order_edit_params = array(
			'submit' => 'yes',
			'item_identifier' => array(
				'orderhdr_id' => $orderhdr_id,
				'order_item_seq' => $order_item_seq
			),
			'item_data' => array(
				'bill_to_customer_id' => $cust_add_response['result']->customer->customer_id,
				'bill_to_customer_address_seq' => $cust_add_response['result']->customer->customer_address->customer_address_seq,
				'renew_to_customer_id' => $cust_add_response['result']->customer->customer_id,
				'renew_to_customer_address_seq' => $cust_add_response['result']->customer->customer_address->customer_address_seq
			)
		);

		// send request to THINK
		$order_edit_response = $this->getThinkSubscription( $order_edit_params, 'OrderItemEdit', $order_data->id );

	}

	/**
	 * Add new Order and Payment to existing customer
	 *
	 * API doc      - order_add_payment_add_request
	 * WSDL Method  - OrderAddPaymentAdd
	 *
	 * @param $user_id
	 */
	public function addOrderPayment( $data ){

		$this->to_think_log( 'addOrderPayment begin data', $data );

		$customer_id         = $data['customer_id'];
		$order_code_id       = $data['order_code_id'];
		$subscription_def_id = $data['subscription_def_id'];
		$order_data          = $data['order_data'];
		$subscrip_id         = $data['subscrip_id'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );
		$payment_type     = $this->card_type[ $order_data->payment_type ];
		$payment          = $order_data->payment;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// Temp until CC processor is setup
		/*if( '0.00' == $payment ){
			$payment = $order_data->price;
		}*/
		// END Temp until CC processor is setup

		// generic source code in THINK for all Haven orders
		$source_code_id = $this->_settings['source_code_id'];

		// if manual order use the source code entered in form
		if( $data['think_source_code'] ){
			$source_code_id = $data['think_source_code'];
		}

		// figure out the Customer address seq id to use
		$customer_address_seq = $this->getDuplicateAddress( $order_data, $customer_id );

		// if we don't find an address add the submitted one and use it
		if( !$customer_address_seq ){
			//add the new address
			$customer_address_seq = $this->addCustomerAddress( $order_data, $customer_id );
		}

		/* Set your parameters for the request */
		$params = array(
				'submit' => 'yes',
				"customer_address_identifier" => array(
						'customer_address_seq' => $customer_address_seq,
						'customer_identifier' => array( 'customer_id' => $customer_id )
				),
				'order_data' => array(
						'item_data' => array(
								'order_code_id' => $order_code_id,
								'source_code_id' => $source_code_id,
								'subscription_def_id' => $subscription_def_id,
								'auxiliary_data' => array(
										array( 'name' => 'zzaux_autorenewflag', 'text_val' => $data['auto_renewal'] ),
										array( 'name' => 'zzaux_havensourcecode','text_val' => $data['haven_source_code'] )
								),
								'currency' => 'USD',
								'item_amt_break_data' => array( 'order_item_break_type' => 'item', 'local_amount' => $payment )
						)
				),
				'payment_add_data' => array(
						'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
						'payment_type' => $payment_type,
						'card_number' => $cc_number,
						'card_expire_date' => $card_expire_date,
						'amount' => $payment,
						'payment_clear_status' => 3,
						'clear_date' => $clear_date
				)
		);

		// is this a renewal? add the param
		if( !empty( $subscrip_id ) || $subscrip_id != '' ){
			$params['order_data']['item_data']['subscrip_id'] = $subscrip_id;
		}

		$this->to_think_log( 'addOrderPayment - params', $params );

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'OrderAddPaymentAdd', $order_data->id );


		if( empty( $response['error'] ) ) {
			// update order with THINK info
			$update_result = $this->updateThinkOrderData( $order_data->id, $response );
		}

		return $update_result;

	}

	/**
	 * Add new "customer" record along with their Shopp Order and Payment
	 *
	 * API doc      - customer_add_order_add_payment_add_request
	 * WSDL Method  - CustomerAddOrderAddPaymentAdd
	 *
	 * @param $user_id
	 */
	public function addShoppCustomerOrderPayment( $data ){

		$this->to_think_log( 'addShoppCustomerOrderPayment - begin data', $data );

		$order_items = $data['think_items'];
		$order_data  = $data['order_data'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );

		$payment_type     = $this->card_type[ strtoupper( str_replace(' ', '', $order_data->cardtype) ) ];
		$payment          = $order_data->total;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// generic source code in THINK for all Haven orders
		$shopp_source_code_id   = '916';
		$shipping_order_code_id = '58';
		$tax_order_code_id      = '59';
		$shipping_product_id    = '443';
		$tax_product_id         = '444';

		// setup the item order data elements
		foreach( $order_items as $item ){
			$item_data[] = array(
				'order_code_id'       => $item['order_code_id'],
				'source_code_id'      => $shopp_source_code_id,
				'product_id'          => $item['product_id'],
				'auxiliary_data'      => array(
					'name'     => 'zzaux_havensourcecode',
					'text_val' => $data['haven_source_code']
				),
				'item_amt_break_data' => array(
					'order_item_break_type' => 'item',
					'local_amount'          => $item['unit_price']
				),
			);
		}

		// add shipping
		$item_data[] = array(
			'order_code_id'  => $shipping_order_code_id,
			'source_code_id' => $shopp_source_code_id,
			'product_id'     => $shipping_product_id,
			'item_amt_break_data' => array(
				'order_item_break_type' => 'item',
				'local_amount' => $order_data->freight
			)
		);

		// add tax is needed
		if ( $order_data->tax != 0 ) {
			$item_data[] = array(
				'order_code_id'   => $tax_order_code_id,
				'source_code_id'  => $shopp_source_code_id,
				'product_id'      => $tax_product_id,
				'item_amt_break_data' => array(
					'order_item_break_type' => 'item',
					'local_amount'          => $order_data->tax
				)
			);
		}

		/* Set your parameters for the request */
		$params = array(
			'submit'        => 'yes',
			"customer_data" => array(
				'fname' => $order_data->firstname,
				'lname' => $order_data->lastname,
				'customer_address_data' => array(
					'address_type' => 'Residence',
					'address1'     => $order_data->address,
					'address2'     => $order_data->xaddress,
					'city'         => $order_data->city,
					'state'        => $order_data->state,
					'zip'          => $order_data->postcode,
				),
				'phone' => $order_data->phone,
				'email' => $order_data->email
			),
			'order_data' => array( 'item_data' => $item_data ),

			'payment_add_data' => array(
				'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
				'payment_type'            => $payment_type,
				'card_number'             => $cc_number,
				'card_expire_date'        => $card_expire_date,
				'amount'                  => $payment,
				'payment_clear_status'    => 3,
				'clear_date'              => $clear_date
			)
		);

		$this->to_think_log( 'addShoppCustomerOrderPayment - params', $params );

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'CustomerAddOrderAddPaymentAdd', $order_data->id );

		if( empty( $response['error'] ) ) {
			// update order with THINK info
			$update_result = "it worked!";
		} else {
			// what do I do if I get an error??? Email it??
		}

		// update user's think_customer_id
		update_user_meta( $order_data->user_id, 'think_customer_id', $response['result']->customer->customer_id );

		return $update_result;

	}

	/**
	 * Add new Shopp Order and Payment to existing customer
	 *
	 * API doc      - order_add_payment_add_request
	 * WSDL Method  - OrderAddPaymentAdd
	 *
	 * @param $user_id
	 */
	public function addShoppOrderPayment( $data ){

		$this->to_think_log( 'addShoppOrderPayment - begin data', $data );

		$customer_id = $data['customer_id'];
		$order_items = $data['think_items'];
		$order_data  = $data['order_data'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );

		$payment_type     = $this->card_type[ strtoupper( str_replace(' ', '', $order_data->cardtype) ) ];
		$payment          = $order_data->total;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// END Temp until CC processor is setup

		// generic source code in THINK for all Haven orders
		$shopp_source_code_id   = '916';
		$shipping_order_code_id = '58';
		$tax_order_code_id      = '59';
		$shipping_product_id    = '443';
		$tax_product_id         = '444';

		$address = new stdClass();
		$address->first_name = $order_data->firstname;
		$address->last_name  = $order_data->lastname;
		$address->address    = $order_data->address;
		$address->address2   = $order_data->xaddress;
		$address->city       = $order_data->city;
		$address->state      = $order_data->state;
		$address->zip_code   = $order_data->postcode;
		$address->email      = $order_data->email;

		// figure out the Customer address seq id to use
		$customer_address_seq = $this->getDuplicateAddress( $address, $customer_id );

		// if we don't find an address add the submitted one and use it
		if( !$customer_address_seq ){
			//add the new address
			$customer_address_seq = $this->addCustomerAddress( $address, $customer_id );
		}

		foreach( $order_items as $item ){
			$item_data[] = array(
				'order_code_id'       => $item['order_code_id'],
				'source_code_id'      => $shopp_source_code_id,
				'product_id'          => $item['product_id'],
				'order_qty'           => $item['quantity'],
				'auxiliary_data'      => array(
					'name'     => 'zzaux_havensourcecode',
					'text_val' => $data['haven_source_code']
				),
				'item_amt_break_data' => array(
					'order_item_break_type' => 'item',
					'local_amount'          => $item['unit_price']
				)
			);
		}

		// add shipping
		$item_data[] = array(
			'order_code_id'       => $shipping_order_code_id,
			'source_code_id'      => $shopp_source_code_id,
			'product_id'          => $shipping_product_id,
			'item_amt_break_data' => array(
				'order_item_break_type' => 'item',
				'local_amount'          => $order_data->freight
			)
		);

		// add tax
		if ( $order_data->tax != 0 ) {
			$item_data[] = array(
				'order_code_id'       => $tax_order_code_id,
				'source_code_id'      => $shopp_source_code_id,
				'product_id'          => $tax_product_id,
				'item_amt_break_data' => array(
					'order_item_break_type' => 'item',
					'local_amount'          => $order_data->tax
				)
			);
		}

		/* Set your parameters for the request */
		$params = array(
			'submit' => 'yes',
			"customer_address_identifier" => array(
				'customer_address_seq' => $customer_address_seq,
				'customer_identifier' => array(
					'customer_id' => $customer_id
				)
			),
			'order_data' => array('item_data' => $item_data),

			'payment_add_data' => array(
				'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
				'payment_type'            => $payment_type,
				'card_number'             => $cc_number,
				'card_expire_date'        => $card_expire_date,
				'amount'                  => $payment,
				'payment_clear_status'    => 3,
				'clear_date'              => $clear_date
			)
		);

		$this->to_think_log( 'addShoppOrderPayment - params', $params );

		// send request to THINK
		$response = $this->getThinkSubscription( $params, 'OrderAddPaymentAdd', $order_data->id );

		if( empty( $response['error'] ) ) {
			// update order with THINK info
			$update_result = "it worked!";
		} else {
			// what do I do if I get an error??? Email it??
		}

		return $update_result;

	}

	/**
	 * Add new Order to existing customer
	 *
	 * API doc      - order_add_request
	 * WSDL Method  - OrderAdd
	 *
	 * @param $user_id
	 */
	public function addOrder( $data ){

		$this->to_think_log( 'addOrder - begin data', $data );

		$customer_id         = $data['customer_id'];
		$order_code_id       = $data['order_code_id'];
		$subscription_def_id = $data['subscription_def_id'];
		$order_data          = $data['order_data'];
		$donor_think_info    = $data['donor_think_info'];
		$payment             = $order_data->payment;

		// generic source code in THINK for all Haven orders
		$source_code_id = $this->_settings['source_code_id'];

		// if manual order use the source code entered in form
		if( $data['think_source_code'] ){
			$source_code_id = $data['think_source_code'];
		}

		// figure out the Customer address seq id to use
		$customer_address_seq = $this->getDuplicateAddress( $order_data, $customer_id );

		// if we don't find an address add the submitted one and use it
		if( !$customer_address_seq ){
			//add the new address
			$customer_address_seq = $this->addCustomerAddress( $order_data, $customer_id );
		}

		/* Set your parameters for the request */
		$params = array(
				'submit'               => 'yes',
				"customer_address_identifier" => array(
						'customer_address_seq' => $customer_address_seq,
						'customer_identifier' => array( 'customer_id' => $customer_id )
				),
				'order_data' => array(
					'item_data' => array(
						'order_code_id' => $order_code_id,
						'source_code_id' => $source_code_id,
						'subscription_def_id' => $subscription_def_id,
						'auxiliary_data' => array(
							array( 'name' => 'zzaux_autorenewflag', 'text_val' => $data['auto_renewal'] ),
							array( 'name' => 'zzaux_havensourcecode','text_val' => $data['haven_source_code'] )
						),
						'currency' => 'USD',
						'item_amt_break_data' => array( 'order_item_break_type' => 'item', 'local_amount' => $payment )
					)
				)
		);

		if( $donor_think_info ){

			$params['order_data']['item_data']['bill_to_customer_id'] = $donor_think_info->customer->default_bill_to_customer_id;
			$params['order_data']['item_data']['bill_to_customer_address_seq'] = $donor_think_info->customer->def_bill_to_cust_addr_seq;

			$params['order_data']['item_data']['renew_to_customer_id'] = $donor_think_info->customer->default_renew_to_customer_id;
			$params['order_data']['item_data']['renew_to_customer_address_seq'] = $donor_think_info->customer->def_renew_to_cust_addr_seq;
		}

		$response = $this->getThinkSubscription( $params, 'OrderAdd', $order_data->id );

		if( empty( $response['error'] ) ) {
			// update order with THINK info
			$update_result = $this->updateThinkOrderData( $order_data->id, $response );
		}

		return $response;

	}

	/**
	 * Add payment to existing Order
	 *
	 * API doc      - payment_add_request
	 * WSDL Method  - PaymentAdd
	 *
	 * @param $user_id
	 */
	public function addPayment( $data ){

		$customer_id    = $data['customer_id'];
		$orderhdr_id   = $data['orderhdr_id'];
		$order_item_seq = $data['order_item_seq'];
		$order_data     = $data['order_data'];
		$donor_think_info = $data['donor_think_info'];

		// payment info. generic except for payment amount. Real CC info stored NOT in THINK ;-)
		$clear_date       = date( 'Y-m-d');//date( "c" );
		$payment_type     = $this->card_type[ $order_data->payment_type ];
		$payment          = $order_data->payment;
		$card_expire_date = $this->_cc_expire_date;
		$cc_number        = $this->_cc_number;

		// Temp until CC processor is setup
		/*if( '0.00' == $payment ){
			$payment = $order_data->price;
		}*/
		// END Temp until CC processor is setup

		/* Set your parameters for the request */
		$params = array(
			'submit' => 'yes',
			'customer_identifier' => array( 'customer_id' => $customer_id ),
			'payment_add_data' => array(
				'sum_amt_due'             => 'yes',
				'hosted_secure_token_pmt' => 'yes', //don't store CC numbers
				'customer_identifier'     => array( 'customer_id' => $customer_id ),
				'payment_type'            => $payment_type,
				'card_number'             => $cc_number,
				'card_expire_date'        => $card_expire_date,
				'amount'                  => $payment,
				'payment_clear_status'    => 3,
				'clear_date'              => $clear_date
			),
			'item_payment' => array(
				'item_identifier' => array(
					'orderhdr_id' => $orderhdr_id,
					'order_item_seq' => $order_item_seq
				),
				'amount' => $payment
			)
		);


		$result = $this->getThinkSubscription( $params, 'PaymentAdd', $order_data->id );

		return $result;

	}

	public function getPaymentSeqId( $think_customer_id ){

		$params = array( 'customer_identifier'  => array( 'customer_id' => $think_customer_id ) );

		$response = $this->getThinkSubscription( $params, 'PaymentListSelect' );

		$payments = $response['result']->payment;

		$refunds = array();
		foreach( $payments as $payment ){

			if(
				$payment->payment_type == 'CK' &&
				$payment->transaction_type == 2 &&
				$payment->payment_clear_status == 6 &&
				$payment->transaction_reason == 'HAVEN'
			){
				$refunds[] = $payment->payment_seq;
			}

		}

		if( count( $refunds ) == 1 ){
			$payment_seq_id = $refunds[0];
		} else if( count( $refunds ) > 1 ) {
			$this->_errors['refund_status_error'] = array('refund_status_error' => 'found more than one matching refund', 'customer_id' => $think_customer_id, 'payments' => $payments );
			$payment_seq_id = 0;
		} else {
			$this->_errors['refund_status_error'] = array('refund_status_error' => 'NO matching refund in THINK', 'customer_id' => $think_customer_id, 'payments' => $payments );
			$payment_seq_id = 0;
		}

		return $payment_seq_id;
	}
	/**
	 * edit existing customer's address
	 *
	 * API doc      - customer_address_edit_request
	 * WSDL Method  - CustomerAddressEdit
	 *
	 * @param $user_id
	 */
	public function editCustomerAddress( $user_id ){

		$customer_id = 1622455;

		/* Set your parameters for the request */
		$params = array(
				'submit'               => 'yes',
				'check_missing_fields' => 'no',
				"customer_address_identifier" => array(
						'customer_address_seq' => '1',
						'customer_identifier' => array(
								'customer_id' => $customer_id
							)
				),
				'customer_address_data' => array(
						'address_type' => 'Residence',
						'city' => 'Seattle'
				)
		);

		$result = $this->getThinkSubscription( $params, 'CustomerAddressEdit');

		return $result;
	}

	/**
	 * edit existing customer's email
	 *
	 * API doc      - customer_edit_request
	 * WSDL Method  - CustomerEdit
	 *
	 * @param $user_id - user_id that was updated
	 * @param $old_user_data - obj - old data before update.
	 */
	public function editCustomerEmail( $user_id, $old_user_data  ){

		$user = get_user_by( 'id', $user_id );

		$data = array( 'user' => $user, 'old_user_data' => $old_user_data );
		$this->to_think_log( 'editCustomerEmail begin ', $data );

		// if the email changed, then update.
		if( $user->user_email != $old_user_data->email ){

			$think_customer_id = get_user_meta( $user_id, 'think_customer_id', true);

			// only do this for existing Think customers
			if ( $think_customer_id != '' ) {
				/* Set your parameters for the request */
				$params = array(
					'submit'               => 'yes',
					'check_missing_fields' => 'no',
					'customer_identifier'  => array( 'customer_id' => $think_customer_id ),
					'customer_data'        => array( 'email' => $user->user_email )
				);

				$this->to_think_log( 'editCustomerEmail params ', $params );

				$result = $this->getThinkSubscription( $params, 'CustomerEdit');
			} else {
				$this->to_think_log( 'editCustomerEmail', 'no think customer id' );
			}

		} else {
			$this->to_think_log( 'editCustomerEmail', 'email did not change' );
		}

		return $result;
	}

	/**
	 * Lookup customer's address to get the address_seq
	 *
	 * API doc      - duplicate_address_list_request
	 * WSDL Method  - DuplicateAddressList
	 *
	 * @param $customer_id
	 * @param $address
	 */
	public function getDuplicateAddress( $address, $customer_id ){

		$this->to_think_log( 'getDuplicateAddress begin - customerid: ' . $customer_id, $address );

		/* Set your parameters for the request */
		$params = array(
			'customer_address_data' => array(
				'fname'          => $address->first_name,
				'lname'          => $address->last_name,
				'address1'       => $address->address,
				'address2'       => $address->address2,
				'city'           => $address->city,
				'state'          => $address->state,
				'zip'            => $address->zip_code,
				//'email'          => $address->email, // NO leads to false positives.
				'address_status' => 'OPEN', //??? needed??
				'address_type'   => 'Residence'
			)
		);

		$response = $this->getThinkSubscription( $params, 'DuplicateAddressList');

		//process result. hopefully we only get one ;-)
		if( !count( (array) $response['result'] ) ){
			// empty no match, return false
			$customer_address_seq = false;

		} else if( is_array( $response['result']->customer_address ) ){
			// figure out best one to use??? for now just take the first one :-O

			// find all for the current user
			$found_addresses = array();
			foreach( $response['result']->customer_address as $customer_address ){
				if( $customer_id == $customer_address->customer_id ){
					$found_addresses[] = $customer_address;
				}
			}

			if( count( $found_addresses ) == 0 ){
				// no matches for current user, then new address for user
				$customer_address_seq = false;
			} else if( count( $found_addresses ) == 1 ) {
				// if only one, then use that address seq
				$customer_address_seq = $found_addresses[0]->customer_address_seq;
			} else {
				//do something more??? if more than one do we go further to figure out on match codes?
				$customer_address_seq = $found_addresses[0]->customer_address_seq;
			}

		} else {
			// single address, return the customer_address_seq to use
			$customer_address_seq = $response['result']->customer_address->customer_address_seq;
		}

		return $customer_address_seq;
	}

	/**
	 * Add a new address to the user's account in THINK
	 *
	 * API doc      - customer_address_add_request
	 * WSDL Method  - CustomerAddressAdd
	 *
	 * @return mixed
	 */
	public function addCustomerAddress( $address, $customer_id ){


		/* Set your parameters for the request */
		$params = array(
			'customer_identifier'  => array( 'customer_id' => $customer_id ),
			'customer_data' => array(
				'customer_address_data' => array(
					'address1'                  => $address->address,
					'address2'                  => $address->address2,
					'city'                      => $address->city,
					'state'                     => $address->state,
					'zip'                       => $address->zip_code,
					'address_type'              => 'Residence',
					'address_status'            => 'OPEN' //??? needed??
				)
			)
		);

		$response = $this->getThinkSubscription( $params, 'CustomerAddressAdd');

		//return the new seq id for the added address
		$customer_address_seq = $response['result']->customer->customer_address->customer_address_seq;

		return $customer_address_seq;
	}

	/**
	 *
	 * source_code_lookup_select_request
	 * @param $address
	 * @param $customer_id
	 *
	 * @return mixed
	 */
	public function getSourceCodeLookup(  ){

		global $wpdb;

		$think_order_classes = array('byp' => '8', 'csn' => '7', 'dgj' => '5', 'sh' => '6' );

		// clear the table for new data
		$clear_table_sql = "TRUNCATE TABLE {$wpdb->prefix}{$this->_typeTable}";
		$clear_result    = $wpdb->query( $clear_table_sql );

		if ( false === $clear_result ) {
			$result = 'error';
		} else {

			foreach ( $think_order_classes as $think_order_class ) {

				// set the order class
				$params = array(
					'oc_id' => $think_order_class
				);

				// get all the source codes by order class
				$response = $this->getThinkSubscription( $params, 'SourceCodeLookupSelect' );

				// add results to DB
				foreach ( $response['result']->source_code as $source_code ) {

					$wpdb->insert(
						$wpdb->prefix . $this->_typeTable,
						array(
							'source_code_id' => $source_code->source_code_id,
							'source_code'    => strtoupper( $source_code->source_code ),
							'description'    => $source_code->description,
							'order_class_id' => $think_order_class
						),
						array( '%d', '%s', '%s', '%d' )
					);
				}

			}

			$result = 'updated';
		}

		return $result;
	}

	public function getSourceCodesMapping( $think_order_class ){
		global $wpdb;

		$sql = "SELECT source_code, source_code_id FROM {$wpdb->prefix}{$this->_typeTable} WHERE order_class_id = {$think_order_class}";

		$results = $wpdb->get_results($sql, ARRAY_A);

		foreach( $results as $result ){
			$source_codes[$result['source_code']] = $result['source_code_id'];
		}

		return $source_codes;
	}

	/**
	 * update the order record with THINK data
	 * @param $data
	 *
	 * @return false|int
	 */
	public function updateThinkOrderData( $order_id, $response ){

		global $wpdb;

		$order_table = $wpdb->prefix . "mequoda_orders";

		if( isset( $response['result']->customer->email_authorization ) ){
		    unset( $response['result']->customer->email_authorization );
        }

        if( isset( $response['result']->subscrip->sub_out ) ){
	        unset( $response['result']->subscrip->sub_out );
        }

		$think_orderhdr_id = $response['result']->orderhdr->orderhdr_id;
		$think_subscrip_id = $response['result']->subscrip->subscrip_id;
		$think_data        = json_encode( $response['result'] );

		$result = $wpdb->update(
			$order_table,
			array(
				'think_orderhdr_id' => $think_orderhdr_id,
				'think_data'        => $think_data,
				'think_subscrip_id' => $think_subscrip_id
			),
			array( 'id' => $order_id )
		);

		return $result;

	}

	/**
	 * make sure the order has an address. there are cases where we don't have one.
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	public function confirmAddress( $order ){

		$this->to_think_log( 'confirm address', $order );
		$type = gettype( $order );
		$this->to_think_log( 'confirm address - order var type', $type );

		if(
			empty( $order->address ) ||
			empty( $order->city ) ||
			empty( $order->state ) ||
			empty( $order->zip_code )
		){
			$this->to_think_log( 'confirm address', 'false' );
			// no address can't process request
			return false;

		} else {
			$this->to_think_log( 'confirm address', 'true' );
			// address good, we can process request
			return true;
		}

	}

	public function getOrderData( $order_id ){

		global $wpdb;

		$sql = "SELECT o.*, of.external_id as subscription_def_id, of.auto_renew
			FROM {$wpdb->prefix}mequoda_orders o
			JOIN {$wpdb->prefix}mequoda_offers of ON o.offer_code = of.id
			WHERE o.id = %d";
		$order_q = $wpdb->prepare( $sql, $order_id );
		$order = $wpdb->get_row( $order_q );

		return $order;
	}

	private function _debug_mail( $subject, $message ) {
		// Used for debugging.
		if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) )
			wp_mail( $this->_settings['debugging_email'], $subject, $message );
	}

	/**
	 * Retrieve a set of errors that have occured as a multi-dimensional array.
	 *
	 * @return array Errors, each with an 'error' and 'code'
	 */
	public function getErrors() {
		if ( empty($this->_errors) )
			$this->_getErrors();
		return $this->_errors;
	}

	private function _getErrors() {
		$this->_errors = get_option( $this->_optionsName . '-errors', array() );
	}

	private function _addError($error) {
		if ( empty($this->_errors) )
			$this->_getErrors();

		$e = new stdClass( );
		$e->error = $error;

		if ( ! empty( $this->_settings['error_email'] ) )
			wp_mail( $this->_settings['error_email'], 'TDC Framework Error', $error );

		$this->_errors[] = $e;
		$this->_setErrors();
	}

	private function _emptyErrors() {
		$this->_errors = array();
		$this->_setErrors();
	}

	private function _setErrors() {
		update_option( $this->_optionsName . '-errors', $this->_errors );
	}

	private function _sendErrors(){

		$message = print_r( $this->_errors, 1 );

		if ( ! empty( $this->_settings['error_email'] ) ){
			wp_mail( $this->_settings['error_email'], 'THINK Error', $message );
		} else {
			wp_mail( 'bob@mequoda.com', 'THINK Error', $message );
		}

		$this->to_think_log( 'send think error', $message );
	}

	private function to_think_log( $title, $data ){

		//$where  = WP_PLUGIN_DIR . '/mequoda-think-framework/logs/to-think.log';
		$where = '/home/countrysidenetwork.com/logs/to-think.log';
		$when = date('Y-m-d H:i:s');
		if( is_array( $data ) || is_object( $data ) ){
			error_log("[{$when}] {$title}: " . print_r( $data, 1 ) . "\n",3, $where );
		} else {
			error_log("[{$when}] {$title}: {$data}" . "\n",3, $where );
		}
	}

}

function load_think_framework(){
	global $mqThinkFramework;
	// Instantiate our class
	$mqThinkFramework = mqThinkFramework::getInstance();
}

add_action('init', 'load_think_framework');