<?php
/**
 * Plugin Name: Mequoda Haven Recurly
 * Plugin URI: http://www.mequoda.com
 * Description: Recurly's subscription management integration
 * Version: 0.1.14
 * Author: Mequoda - Bob Albert
 * Author URI: http://www.mequoda.com
 */

/*
 * Recurly Developer Hub:
 * https://dev.recurly.com
 */

/*
 * Recurly PHP Client library:
 * https://dev.recurly.com/page/php
 * https://github.com/recurly/recurly-client-php
 */


/* CHANGE LOG
 *	2018-11-30 - 0.1.14 - Anthony Laurence (CAB-1381)
 *		- changed method to 'transaction_first' in refundInvoice
 *  2017-01-13 - 0.1.13 - Anthony Laurence
 *      - added refundInvoice() to refund transaction purchases
 *  2017-01-13 - 0.1.12 - Bob Albert
 *      - added error message for Recurly Server Connection errors
 *  2016-12-29 - 0.1.11 - Anthony Laurence
 *      - added $billing_info->first_name, $billing_info->last_name to createTransaction() as these are required for Recurly transactions
 *  2016-12-14 - 0.1.10 - Bob Albert
 *      - fix refund amount when terminate without refund chosen
 *  2016-10-24 - 0.1.9 - Bob Albert
 *      - support for coupon codes for paid trials with reduce price first term
 *  2016-09-07 - 0.1.8 - Bob Albert
 *      - move post processing for cancel and terminate to actions
 *  2016-09-01 - 0.1.7 - Bob Albert
 *      - update cancels to account for renewal notice data in orders and WC
 *  2016-08-04 - 0.1.6 - Bob Albert
 *      - updates for Cancel at Renewal and Terminate with or without Refund
 *  2016-07-26 - 0.1.5 - Bob Albert
 *      - updates for Renewals -> in haven-recurly-webhooks.php
 *      - Properly handle auto renewal notifications (successful payments) from Recurly via web hook
 *  2016-07-23 - 0.1.4 - Bob Albert
 *      - updates for createTransaction for Event Purchase
 *  2016-07-18 - 0.1.3 - Bob Albert
 *      - added createTransaction( $params ) method
 *      - initial addition to support "Event" purchases and the Haven Events plugin.
 *      - should be used for one off transactions
 *  2016-07-15 - Bob Albert
 *      - add initial methods for Cancel and Refunds
 *  2016-04-20 to 2016-07-15 - Bob Albert
 *      - initial creation of plugin
 *      - initial setup for TSI as first customer
 *
 */


// load Recurly helper class
require_once( plugin_dir_path( __FILE__ ) . '/lib/recurly.php');

require_once( plugin_dir_path( __FILE__ ) . 'haven-recurly-webhooks.php' );

require_once( plugin_dir_path( __FILE__ ) . 'haven-recurly-ajax.php' );

/**
 * Class mqRecurly
 */
class mqRecurly
{
	static $instance = false;
	/**
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * @var string
	 */
	private $_optionsName = 'meq-recurly';

	/**
	 * @var string
	 */
	private $_optionsGroup = 'meq-recurly-options';

	/**
	 * The name of the table that holds web hook data
	 *
	 * @var string
	 */
	private $_recurlyWebhooksTable = 'mequoda_recurly_webhooks';

	private function __construct() {

		$this->_getSettings();

		register_activation_hook( __FILE__, array($this, 'activate') );

		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array($this,'adminMenu') );

		add_action( 'mequoda-offer-saved', array( $this, 'offerSaved' ) );
		add_action( 'mequoda-offer-deleted', array( $this, 'deletePlan' ) );
		add_action( 'mequoda-offer-copied', array( $this, 'offerCopied' ) );

		/* https://<your-subdomain>.recurly.com */
		Recurly_Client::$subdomain = $this->_settings['subdomain'];
		/* your private API key */
		Recurly_Client::$apiKey = $this->_settings['api_key'];

	}

	public function activate() {
		global $wpdb;

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

		/*
		 *      'post_xml'        => $post_xml,
				'type'            => $notification->type,
				'invoice_number'  => $notification->transaction->invoice_number->__toString(),
				'subscription_id' => $notification->transaction->subscription_id->__toString(),
				'transaction_id'  => $notification->transaction->id->__toString(),
				'action'          => $notification->transaction->action->__toString(),
				'date'            => $notification->transaction->date->__toString(),
				'amount_in_cents' => $notification->transaction->amount_in_cents->__toString(),
				'status'          => $notification->transaction->status->__toString(),
				'account_code'    => $notification->account->account_code->__toString(),
				'email'           => $notification->account->email->__toString(),
				'first_name'      => $notification->account->first_name->__toString(),
				'last_name'       => $notification->account->last_name->__toString(),
				'order_id'        => $order->id,
				'user_id'         => $order->user_id,
				'product_id'      => $order->product_id,
				'offer_id'        => $order->offer_id,
				'price'           => $order->price,
				'term'            => $order->term,
				'first_payment'   => $first_payment
		 */

		dbDelta(  "CREATE TABLE `{$wpdb->prefix}{$this->_recurlyWebhooksTable}` (
			id bigint(20) unsigned NOT NULL auto_increment,
			type varchar(100) NULL,
			invoice_number varchar(100) NULL,
			subscription_id varchar(100) NULL,
			transaction_id varchar(100) NULL,
			action varchar(20) NULL,
			date varchar(30) NULL,
			amount_in_cents varchar(20) NULL,
			status varchar(20) NULL,
			account_code varchar(200) NULL,
			email varchar(60) NULL,
			first_name varchar(20) NULL,
			last_name varchar(30) NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id varchar(20) NULL,
			offer_id varchar(20) NULL,
			price  varchar(20) NULL,
			term varchar(4) NULL,
			first_payment enum('n','y') DEFAULT 'n',
			post_xml text NULL,
			PRIMARY KEY  (id), 
			KEY (invoice_number),
			KEY (subscription_id)
			);"
		);

	}

	public static function getInstance() {
		if (!self::$instance) { self::$instance = new self; }
		return self::$instance;
	}

	private function _getSettings() {
		if (empty($this->_settings)) {
			$this->_settings = get_option( $this->_optionsName );
		}
		if ( !is_array( $this->_settings ) ) {
			$this->_settings = array();
		}
		$defaults = array(
			'subdomain'      => '',
			'api_key'        => '',
			'public_key'     => '',
			'plan_id_prefix' => '',
			'currency'       => 'USD',
			'tsi'            => 'no'
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
		if ( function_exists('register_setting') ) {
			register_setting( $this->_optionsGroup, $this->_optionsName );
		}
	}

	public function adminMenu() {
		add_options_page(__('Recurly Settings'), __('Recurly'), 'manage_options', 'mq-recurly-settings', array($this, 'options'));
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
		?>
		<style type="text/css">
			.large-text{width:99%;}
			.regular-text{width:25em;}
		</style>
		<div class="wrap">
			<h2><?php _e('Recurly Settings') ?></h2>

			<form action="options.php" method="post" id="mq_recurly">
				<?php settings_fields( $this->_optionsGroup ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_subdomain">
								<?php _e('Subdomain:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[subdomain]" value="<?php echo esc_attr($this->_settings['subdomain']); ?>" id="<?php echo $this->_optionsName; ?>_subdomain" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_api_key">
								<?php _e('API Key:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[api_key]" value="<?php echo esc_attr($this->_settings['api_key']); ?>" id="<?php echo $this->_optionsName; ?>_api_key" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_public_key">
								<?php _e('Public Key:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[public_key]" value="<?php echo esc_attr($this->_settings['public_key']); ?>" id="<?php echo $this->_optionsName; ?>_public_key" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_plan_id_prefix">
								<?php _e('Recurly Plan ID prefix:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[plan_id_prefix]" value="<?php echo esc_attr($this->_settings['plan_id_prefix']); ?>" id="<?php echo $this->_optionsName; ?>_plan_id_prefix" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_currency">
								<?php _e('Currency:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[currency]" value="<?php echo esc_attr($this->_settings['currency']); ?>" id="<?php echo $this->_optionsName; ?>_currency" class="regular-text code" />
						</td>
					</tr>

					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_tsi">
								<?php _e('TSI Site?:', 'mq-recurly') ?>
							</label>
						</th>
						<td>
							<!-- Is this a TSI site? -->
							<input type="radio" name="<?php echo $this->_optionsName; ?>[tsi]" value="yes" id="<?php echo $this->_optionsName; ?>_tsi"<?php checked('yes', $this->_settings['tsi']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_tsi"><?php _e('Yes'); ?></label><br />
						</td>
					</tr>

				</table>

				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update Settings &raquo;'); ?>" />
				</p>
			</form>
		</div>

		<?php
	}

	public function getActiveAccounts() {

		$accounts = Recurly_AccountList::getActive();

		return $accounts;

	}

	public function getUserAccount( $user_id ){

		try {
			$account = Recurly_Account::get( $user_id );
			//print "Account: $account\n";
		} catch (Recurly_NotFoundError $e) {
			return new WP_Error( 'account_not_found', __( 'Account Not Found: ' ) . $e );
		}

		return $account;
	}

	public function createUserAccount( $user_id ){

		$user_data = get_user_by( 'id', $user_id );

		try {
			$account             = new Recurly_Account( $user_id );
			$account->email      = $user_data->user_email;
			$account->first_name = $user_data->first_name;
			$account->last_name  = $user_data->last_name;
			$account->create();

			//print "Account: $account\n";
			return $account;

		} catch (Recurly_ValidationError $e) {
			return new WP_Error( 'invalid_account', __( 'Invalid Account: ' ) . $e );
		}

	}

	/**
	 * https://dev.recurly.com/docs/create-subscription
	 *
	 * @param $params - list needed data to setup the subscription
	 *
	 * @return Recurly_Subscription|WP_Error
	 */
	public function createSubscription( $params ){

		try {

			/**
			 * Subscription info
			 */
			$subscription            = new Recurly_Subscription();
			$subscription->plan_code = $this->_settings['plan_id_prefix'] . $params->offer_id;
			$subscription->currency  = $this->_settings['currency'];

			// apply coupon if passed.
			if( $params->coupon_code ){
				$subscription->coupon_code = $params->coupon_code;
			}

			$include_billing_info = true;
			/* if paying by check */
			if( 'check' == $params->payment_type ){
				$subscription->collection_method = 'manual';
				$subscription->total_billing_cycles = 1;
				$include_billing_info = false;
			}

			/**
			 * Acount info
			 */
			$account = new Recurly_Account();

			$account->account_code = $params->recurly_account_code;
			$account->email        = $params->user->user_email;
			$account->first_name   = $params->user->first_name;
			$account->last_name    = $params->user->last_name;

			$account->address->address1 = $params->address->address1;
			$account->address->address2 = $params->address->address2;
			$account->address->city     = $params->address->city;
			$account->address->state    = $params->address->state;
			$account->address->country  = $params->address->country;
			$account->address->zip      = $params->address->zip;
			$account->address->phone    = $params->address->phone;

			if ( $include_billing_info ) {
				/**
				 * Billing information
				 */
				$billing_info = new Recurly_BillingInfo();

				$billing_info->number             = $params->billing_info->number;
				$billing_info->month              = $params->billing_info->month;
				$billing_info->year               = $params->billing_info->year;
				$billing_info->verification_value = $params->billing_info->verification_value;

				$billing_info->address1 = $params->address->address1;
				$billing_info->address2 = $params->address->address2;
				$billing_info->city     = $params->address->city;
				$billing_info->state    = $params->address->state;
				$billing_info->country  = $params->address->country;
				$billing_info->zip      = $params->address->zip;

				// set billing info to the account
				$account->billing_info = $billing_info;
			}

			// add account info to the subscription
			$subscription->account = $account;

			/*
			$this->to_recurly_log( 'Recurly API call data', $log_subscription_data );*/

			// create the subscription in Recurly
			$subscription->create();

			$invoice = $subscription->invoice->get();

			$api_result = array( 'subscription' => $subscription, 'invoice' => $invoice );

			$this->to_recurly_log( 'Recurly API call result', $api_result );

			return $api_result;

		} catch (Recurly_NotFoundError $e) {
			return new WP_Error( 'recurly_notfound', 'Record could not be found' );
		} catch (Recurly_ValidationError $e) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly API ERROR', $messages );
			return new WP_Error( 'recurly_validationerror', implode("<br/>", $messages) );
		} catch (Recurly_ServerError $e) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly Server Error', $messages );
			wp_mail( 'bob@mequoda.com', 'Recurly_ServerError Cabot', $messages );
			return new WP_Error( 'recurly_servererror', 'Problem communicating with payment processor. Please try again later.' );
		} catch (Exception $e) {
			return new WP_Error( 'exception', get_class($e) . ': ' . $e->getMessage() );
		}

	}

	/**
	 * https://dev.recurly.com/docs/create-transaction
	 *
	 * @param $params - data needed to setup the transaction
	 *
	 * @return Recurly_Transaction|WP_Error
	 */
	public function createTransaction( $params ){

		// @todo - add logging
		try {
			$transaction                  = new Recurly_Transaction();
			$transaction->amount_in_cents = $params->amount_in_cents;
			$transaction->description     = $params->description;
			$transaction->currency        = $this->_settings['currency'];;

			$account               = new Recurly_Account();
			$account->account_code = $params->recurly_account_code;
			$account->email        = $params->user->user_email;
			$account->first_name   = $params->user->first_name;
			$account->last_name    = $params->user->last_name;

			$billing_info                       = new Recurly_BillingInfo();
			$billing_info->number               = $params->billing_info->number;
			$billing_info->month                = $params->billing_info->month;
			$billing_info->year                 = $params->billing_info->year;
			$billing_info->verification_value   = $params->billing_info->verification_value;
			$billing_info->first_name           = $params->billing_info->first_name;
			$billing_info->last_name            = $params->billing_info->last_name;

			$billing_info->address1 = $params->address->address1;
			$billing_info->address2 = $params->address->address2;
			$billing_info->city     = $params->address->city;
			$billing_info->state    = $params->address->state;
			$billing_info->country  = $params->address->country;
			$billing_info->zip      = $params->address->zip;

			$account->billing_info = $billing_info;
			$transaction->account  = $account;

			$transaction->create();

			$invoice = $transaction->invoice->get();

			$api_result = array( 'transaction' => $transaction, 'invoice' => $invoice );

			$this->to_recurly_log( 'Recurly API call result', $api_result );

			return $api_result;


		} catch ( Recurly_TransactionError $e ) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly API ERROR', $messages );
			return new WP_Error( 'recurly_transaction_error', implode("<br/>", $messages) );
		} catch (Recurly_ValidationError $e) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly API ERROR', $messages );
			return new WP_Error( 'recurly_validation_error', implode("<br/>", $messages) );
		} catch (Recurly_ServerError $e) {
			/*print 'Problem communicating with Recurly';*/
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly Server Error', $messages );
			wp_mail( 'bob@mequoda.com', 'Recurly_ServerError Cabot', $messages );
			return new WP_Error( 'recurly_servererror', 'Problem communicating with payment processor. Please try again later.' );
		} catch (Exception $e) {
			return new WP_Error( 'exception', get_class($e) . ': ' . $e->getMessage() );
		}

	}


	/**
	 * Plans
	 */

	/**
	 * action called after offer is saved or updated in Offer Manager
	 *
	 * @param $params array - offerId and offer data array
	 */
	public function offerSaved( $params ){

		$offerId = $params['offer_id'];
		$offer   = $params['offer'];

		// get all the current plans
		$plans = $this->listPlans();

		foreach ($plans as $plan) {
			$all_plans[] = $plan->plan_code;
		}

		if( in_array( $this->_settings['plan_id_prefix'] . $offerId, $all_plans ) ){
			// update plan in Recurly
			$recurly_success = $this->updatePlan( $offer );
		} else {
			// create plan in Recurly
			$offer['id'] = $offerId;
			$recurly_success = $this->createPlan( $offer );
		}

	}

	public function offerCopied( $offer_id ){

		// get offer data
		$mqOfferManager = mqOfferManager::getInstance();
		$offer = $mqOfferManager->getOffer( $offer_id );

		$offer_array = json_decode( json_encode( $offer ), true );
		$recurly_success = $this->createPlan( $offer_array );

	}

	public function listPlans(){

		$plans = Recurly_PlanList::get();

		return $plans;
	}



	public function lookupPlanDetails( $offer ){

		$plan_id = 'tsi-plan-' . $offer->id;

		try {
			$plan = Recurly_Plan::get( $plan_id );
			return $plan;

		} catch (Recurly_NotFoundError $e) {
			return new WP_Error( 'plan_not_found', __( 'Plan not found: ' ) . $e );
		}

	}

	/**
	 * Create a Plan (Haven Offer) in Reclurly
	 * https://dev.recurly.com/docs/create-plan
	 *
	 * @param $offer array - offer data from Mequoda Offer Manager
	 *
	 * @return Recurly_Plan|WP_Error
	 */

	public function createPlan( $offer ){
		/*
		 * $offer
		Array
		(
		    [id] => 13
		    [publication] =>
		    [product_id] => 97
		    [product_type] => Digital
		    [title] => Test Offer
		    [description] => test offer description
		    [headline] => test offer headline
		    [pay_headline] => test offer headline
		    [order_summary_product_name] => test order summary
		    [activation_date] =>
		    [expiration_date] =>
		    [external_id] =>
		    [default_offer] => 1
		    [renewal_offer] => 1
		    [sequence] => 1
		    [amt] => 12.00
		    [currency] => CAD
		    [auto_renew] => 1
		    [freq] => 1
		    [period] => Year
		    [totalbillingcycles] =>
		    [initamt] => 0.00
		    [region_limit] => us_ca_only
		    [trialamt] => 0.00
		    [trialfreq] => 1
		    [trialperiod] => Month
		    [totaltrialcycles] => 0
		    [active] => 1
		    [us_only] => 0
		    [us_exclude] => 0
		)
		 */

		$product_title = get_the_title( $offer['product_id'] );

		try {

			$plan = new Recurly_Plan();
			$plan->plan_code   = $this->_settings['plan_id_prefix'] . $offer['id'];
			$plan->name        = $product_title . ' - ' . $offer['title'];
			$plan->description = $offer['description'];

			// convert amt to cents
			//$amount_in_cents = number_format( $offer['amt'], 2 ) * 100;
			$amount_in_cents = number_format((float)$offer['amt']*100., 0, '.', '');
			$plan->unit_amount_in_cents->addCurrency( $offer['currency'], $amount_in_cents );

			$offer['period'] = strtolower( $offer['period'] );

			switch ( $offer['period'] ) {
				case 'year':
					$plan_interval_length = 12 * $offer['freq'];
					$plan_interval_unit   = 'months';
					break;
				case 'week':
					$plan_interval_length = 7 * $offer['freq'];
					$plan_interval_unit   = 'days';
					break;
				default:
					$plan_interval_length = $offer['freq'];
					$plan_interval_unit   = $offer['period'] . 's';
					break;
			}
			$plan->plan_interval_length = $plan_interval_length;
			$plan->plan_interval_unit   = $plan_interval_unit;

			// @todo - figure out trial translation
			// from offer manager:
			// total trial cycles (If you want to allow for a certain number of discounted cycles, this is where you set that up. Set to 0 for no trial period.)
			if( $offer['totaltrialcycles'] ){

				$offer['trialperiod'] = strtolower( $offer['trialperiod'] );

				switch ( $offer['trialperiod'] ) {
					case 'year':
						$trial_interval_length = 12 * $offer['trialfreq'];
						$trial_interval_unit   = 'months';
						break;
					case 'week':
						$trial_interval_length = 7 * $offer['trialfreq'];
						$trial_interval_unit   = 'days';
						break;
					default:
						$trial_interval_length = $offer['trialfreq'];
						$trial_interval_unit   = $offer['trialperiod'] . 's';
						break;
				}

				$plan->trial_interval_unit   = $trial_interval_unit;
				$plan->trial_interval_length = $trial_interval_length;
			}

			// from offer manager - (Number of cycles to auto-renew for. Leave blank to renew indefinitely.)
			if( !empty( $offer['totalbillingcycles'] ) ){
				$plan->total_billing_cycles = $offer['totalbillingcycles'];
			}

			// if auto renew total_billing_cycles = null
			// if no auto renew then total_billing_cycles = 1 and done ;-)
			if( !$offer['auto_renew'] ){
				$plan->total_billing_cycles = 1;
				$plan->auto_renew = 0;
			} else {
				$plan->auto_renew = 1;
			}

			// apply the coupon instead of setup fee for paid offers
			/*if( $offer['trialamt'] != '0.00' ){
				$trial_amount = number_format( $offer['trialamt'], 2 ) * 100;
				$plan->setup_fee_in_cents->addCurrency( $offer['currency'], $trial_amount );
			}*/


			$plan->tax_exempt = false;

			$plan->create();

			return $plan;

		} catch (Recurly_ValidationError $e) {
			return new WP_Error( 'invalid_data', $e );
		}

	}

	public function updatePlan( $offer ){

		$product_title = get_the_title( $offer['product_id'] );
		$this->to_recurly_log( 'Recurly offer updated', $offer );

		try {

			$plan = new Recurly_Plan();
			$plan->plan_code   = $this->_settings['plan_id_prefix'] . $offer['id'];
			$plan->name        = $product_title . ' - ' . $offer['title'];
			$plan->description = $offer['description'];

			// convert amt to cents
			//$amount_in_cents = number_format( $offer['amt'], 2 ) * 100;
			$amount_in_cents = number_format((float)$offer['amt']*100., 0, '.', '');
			$plan->unit_amount_in_cents->addCurrency( $offer['currency'], $amount_in_cents );

			$offer['period'] = strtolower( $offer['period'] );

			switch ( $offer['period'] ) {
				case 'year':
					$plan_interval_length = 12 * $offer['freq'];
					$plan_interval_unit   = 'months';
					break;
				case 'week':
					$plan_interval_length = 7 * $offer['freq'];
					$plan_interval_unit   = 'days';
					break;
				default:
					$plan_interval_length = $offer['freq'];
					$plan_interval_unit   = $offer['period'] . 's';
					break;
			}
			$plan->plan_interval_length = $plan_interval_length;
			$plan->plan_interval_unit   = $plan_interval_unit;

			// if auto renew total_billing_cycles = null
			// if no auto renew then total_billing_cycles = 1 and done ;-)
			if( !$offer['auto_renew'] ){
				$plan->total_billing_cycles = 1;
				$plan->auto_renew = 0;
			} else {
				$plan->auto_renew = 1;
			}

			// @todo - figure out trial translation
			// from offer manager:
			// total trial cycles (If you want to allow for a certain number of discounted cycles, this is where you set that up. Set to 0 for no trial period.)
			if( $offer['totaltrialcycles'] ){

				$offer['trialperiod'] = strtolower( $offer['trialperiod'] );

				switch ( $offer['trialperiod'] ) {
					case 'year':
						$trial_interval_length = 12 * $offer['trialfreq'];
						$trial_interval_unit   = 'months';
						break;
					case 'week':
						$trial_interval_length = 7 * $offer['trialfreq'];
						$trial_interval_unit   = 'days';
						break;
					default:
						$trial_interval_length = $offer['trialfreq'];
						$trial_interval_unit   = $offer['trialperiod'] . 's';
						break;
				}


				$plan->trial_interval_unit   = $trial_interval_unit;
				$plan->trial_interval_length = $trial_interval_length;
			} else {
				$plan->trial_interval_length = 0;
			}

			// from offer manager - (Number of cycles to auto-renew for. Leave blank to renew indefinitely.)
			if( !empty( $offer['totalbillingcycles'] ) ){
				$plan->total_billing_cycles = $offer['totalbillingcycles'];
			}

			// apply the coupon instead of setup fee for paid offers
			/*if( $offer['trialamt'] != '0.00' ){
				$trial_amount = number_format( $offer['trialamt'], 2 ) * 100;
				$plan->setup_fee_in_cents->addCurrency( $offer['currency'], $trial_amount );
			}*/


			$plan->tax_exempt = false;
			$plan->update();

			$this->to_recurly_log( 'Recurly offer updated', $plan );

			return $plan;

		} catch (Recurly_ValidationError $e) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly update offer', $messages );
			return new WP_Error( 'invalid_data', $e );
		} catch (Recurly_NotFoundError $e) {
			$messages = explode(',', $e->getMessage());
			$this->to_recurly_log( 'Recurly update offer', $messages );
			return new WP_Error( 'plan_not_found', $e );

		}

	}

	public function deletePlan( $offer_id ){

		$plan_id = $this->_settings['plan_id_prefix'] . $offer_id;

		try {
			$plan = Recurly_Plan::get( $plan_id );
			$plan->delete();

			return $plan;

		} catch (Recurly_NotFoundError $e) {
			return new WP_Error( 'plan_not_found', $e );
		}

	}
	
	public function cancelAtRenewal( $order_id ){
		
		$order_data =  $this->getOrder( $order_id );

		$subscription_id  = $order_data->recurly_uuid;

		try {
			$subscription = Recurly_Subscription::get( $subscription_id );
			$subscription->cancel();

			// update current order and remove auto_renewal
			global $wpdb;

			$table = 'wp_mequoda_orders';

			$result = $wpdb->update(
				$table,
				array( 'auto_renewal' => '0', 'renewal_notice' => 'n' ),
				array( 'id' => $order_data->id)
			);

			do_action( 'recurly_cancel_at_renewal', $order_data );
			
			return 'success'; // array? success/failure plus order id?

		} catch (Recurly_NotFoundError $e) {
			
			$message = $e->getMessage();
			return "Subscription Not Found: $message";
			
		} catch (Recurly_Error $e) {

			$message = $e->getMessage();
			return "Subscription already canceled: $message";
		}


	}

	public function terminateImmediately( $order_id, $refund_type = 'none' ){

		global $current_user;

		$order_data =  $this->getOrder( $order_id );
		$subscription_id = $order_data->recurly_uuid;


		try {
			$subscription = Recurly_Subscription::get( $subscription_id  );


			switch ( $refund_type ){
				case 'full':
					// Terminate the subscription immediately and issue a full refund of the last renewal
					$subscription->terminateAndRefund();
					break;
				case 'prorated':
					// Terminate the subscription immediately and issue a prorated/partial refund of the last renewal
					$subscription->terminateAndPartialRefund();
					break;
				case 'none':
				default:
					// Terminate the subscription immediately without a refund
					$subscription->terminateWithoutRefund();
					break;
			}

			// get recently invoices for the account
			try {
				$time = strtotime("-5 minutes");
				$invoices = Recurly_InvoiceList::getForAccount( $order_data->recurly_account, array('begin_time' => date(DATE_ATOM, $time) ) );
				foreach ($invoices as $key => $invoice) {
					if( $key == '0'){

						if ( 'none' == $refund_type ) {
							$refund_amount = 0.00;
						} else {
							$refund_amount = abs( $invoice->total_in_cents / 100 );
						}
						$refund_invoice_number = $invoice->invoice_number;
					}
				}
			} catch (Recurly_NotFoundError $e) {
				$message = $e->getMessage();
				return "Account not found: $message";
			}

			$refund_order_data = (array) $order_data;

			unset( $refund_order_data['id'] );
			unset( $refund_order_data['price'] );
			unset( $refund_order_data['payment'] );

			$refund_order_data['payment_type'] = "REFUND";
			$refund_order_data['refund'] = $refund_amount;
			$refund_order_data['recurly_invoice_number'] = $refund_invoice_number;
			$refund_order_data['correlation_id'] = $order_data->id;

			$csr_name = $current_user->first_name.' '.$current_user->last_name;
			$refund_order_data['order_summary'] = 'Subscription Terminated - Refund for $' . number_format( $refund_amount, 2)  . ' placed by ' . $csr_name;;

			// update orders table.
			$this->cancelHavenOrder( $order_data, $refund_order_data );

			$data = array('original_order' => $order_data, 'refund_order' => $refund_order_data);

			do_action( 'recurly_terminate_immediately', $data );

			return 'success';

		} catch (Recurly_NotFoundError $e) {

			$message = $e->getMessage();
			return "Subscription Not Found: $message";

		} catch (Recurly_Error $e) {

			$message = $e->getMessage();
			return "Subscription already terminated: $message";
		}


		return $order_id;
	}

	public function refundInvoice( $order_id, $refund_type = 'none', $user_id = NULL ) {

		if ( $user_id === null ) {
			return;
		}

		$current_user = get_user_by( 'ID', $user_id );
		$order_data   = $this->getOrder( $order_id );
		$invoice_id   = $order_data->recurly_invoice_number;

            try {

	            if ($order_data->payment_type === 'CHECK') {
	                //if Check payment

		            $refund_amt            = $order_data->payment;
		            $refund_invoice_number = 'CHECK-REFUND';
                } else {
	                //if CC payment

		            $invoice     = Recurly_Invoice::get( $invoice_id ); // get some invoice
		            $line_items  = $invoice->line_items;      // get line items
		            $adjustments = array_map(
			            function ( $line_item ) {
				            return $line_item->toRefundAttributes();
			            },
			            $invoice->line_items
					);
					
				//	wp_mail('webdesignbyanthony@gmail.com', 'cab recurly', print_r( $adjustments, true ) );
				//	exit;

		            $refund_invoice        = $invoice->refund( $adjustments, 'transaction_first' );
		            $refund_amt            = abs( $refund_invoice->subtotal_in_cents / 100 );
		            $refund_invoice_number = $invoice->invoice_number;
                }

                $refund_order_data     = (array) $order_data;

                unset( $refund_order_data['id'] );
                unset( $refund_order_data['price'] );
                unset( $refund_order_data['payment'] );

                $refund_order_data['payment_type']           = "REFUND";
                $refund_order_data['refund']                 = $refund_amt;
                $refund_order_data['recurly_invoice_number'] = $refund_invoice_number;
                $refund_order_data['correlation_id']         = $order_data->id;

                $csr_name                           = $current_user->first_name . ' ' . $current_user->last_name;
                $refund_order_data['order_summary'] = 'Purchase Cancelled - Refund for $' . number_format( $refund_amt, 2 ) . ' placed by ' . $csr_name;;

                // update orders table.
                $this->cancelHavenOrder( $order_data, $refund_order_data );

                $data = array( 'original_order' => $order_data, 'refund_order' => $refund_order_data );

                do_action( 'refund_transaction', $data );

                return "success";

            } catch ( Recurly_ValidationError $e ) {

                $message = $e->getMessage();

                return "Validation Error: $message";

            } catch ( Recurly_NotFoundError $e ) {

                $message = $e->getMessage();

                return "Invoice not found: $message";
            }

	}

	public function postponeSubscription( $order_id, $renewal_date ){

		$order_data =  $this->getOrder( $order_id );
		$subscription_id = $order_data->recurly_uuid;

		try {
			$subscription = Recurly_Subscription::get( $subscription_id  );


			try {
				$subscription->postpone( $renewal_date );
			} catch (Recurly_NotFoundError $e) {
				$message = $e->getMessage();
				return "Account not found: $message";
			}

			do_action( 'recurly_change_renewal_date', array( 'order_data' => $order_data, 'renewal_date' => $renewal_date ) );

			return 'success';

		} catch (Recurly_NotFoundError $e) {
			$message = $e->getMessage();
			return "Subscription Not Found: $message";
		}

		return $order_id;

	}


	public function getOrder ( $order_id ){

		global $wpdb;

		$sql = $wpdb->prepare( "select * from wp_mequoda_orders where id = %s", $order_id );

		$order = $wpdb->get_row( $sql );

		return $order;

	}

	/**
	 * mark current order as cancel. update it with cancelled order id
	 *
	 * @param $order_id
	 * @param $cancel_order_id
	 *
	 * @return false|int
	 */
	public function cancelHavenOrder ( $original_order, $refund_order_data ){

		global $wpdb;

		$refund_result = $wpdb->insert(
			'wp_mequoda_orders',
			$refund_order_data
		);


		// update original order and cancel it
		$table = 'wp_mequoda_orders';
		$result = $wpdb->update(
			$table,
			array(
				'cancelled' => 'y',	// string
				'renewal_notice' => 'n'
			),
			array( 'ID' => $original_order->id ),
			array(
				'%s', //cancelled
				'%s' // renewal notice

			),
			array( '%d' )
		);

		return $result;

	}

	/**
	 * Logger to from-recurly.log. outputs WS actions on event calls from Recurly Webhooks
	 *
	 * @param $title - message title
	 * @param $data - data to output. array/object or text
	 */
	public function to_recurly_log( $title, $data ){

		//$where = WP_PLUGIN_DIR . '/haven-recurly/logs/to-recurly.log';
		$where = '/home/cabotwealth.com/logs/to-recurly.log';

		$when = date('Y-m-d H:i:s');
		if( is_array( $data ) || is_object( $data ) ){
			error_log("[{$when}] {$title}: " . print_r( $data, 1 ) . "\n",3, $where );
		} else {
			error_log("[{$when}] {$title}: {$data}" . "\n",3, $where );
		}

	}
}


global $mqRecurly;
// Instantiate our class
$mqRecurly = mqRecurly::getInstance();
