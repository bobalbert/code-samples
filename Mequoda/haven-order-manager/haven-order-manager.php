<?php
/**
 * Plugin Name: Mequoda Haven Order Manager
 * Plugin URI: http://www.mequoda.com/
 * Description: Allows backend admin entered order management - place order, cancel order, renew order etc.
 * Version: 0.9
 * Author: Mequoda - Bob Albert
 * Author URI: http://www.mequoda.com
 */

class mqOrderManager {

	private $_settings;
	private $_errors = array();
	private $_message = '';

	private $_credit_card_types = array(
		'AMEX'			=> 'American Express',
		'MASTERCARD'	=> 'MasterCard',
		'VISA'			=> 'Visa',
		'DISCOVER'		=> 'Discover'
	);

	/**
	 * @var string
	 */
	private $_optionsName = 'mq-haven-order-manager';

	/**
	 * @var string
	 */
	private $_optionsGroup = 'mq-haven-order-manager-options';

	public function __construct() {
		$this->_getSettings();
		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array($this, 'admin_menu'), 10);
		add_action( 'admin_print_scripts', array( $this,'page_scripts' ) );

		$this->_credit_card_types = apply_filters( 'mq-cms-cc-types', $this->_credit_card_types );

	}

	function remove_place_order(){


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
			'supportemail'      => ''
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

	public function page_scripts() {
		$screen = get_current_screen();
		if ( $screen->id == 'admin_page_haven_orders' || $screen->id == 'admin_page_haven_orders_place_order' ){
			wp_enqueue_script( 'haven-order-manager', plugin_dir_url( __FILE__ ) . 'js/haven-order-manager.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ), '106' );
			wp_enqueue_style( 'jquery-ui-datepicker-style' , '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/smoothness/jquery-ui.css');
		}
	}


	public function admin_menu() {
		//add_menu_page (__('Haven Orders Manager'), __('Haven Orders'), 'manage_options', 'haven_orders', array($this, 'load_page'), 'dashicons-book-alt', 9);

		add_options_page(__('Orders Manager Settings'), __('Order Manager'), 'manage_options', 'haven_orders_settings', array($this, 'options'));

		add_submenu_page ('', __('Haven Order Manager'), 'User Orders', 'manage_options', 'haven_orders', array($this, 'show_orders'));
		add_submenu_page ('', __('Haven Order Manager'), __('Place Order'), 'manage_options', 'haven_orders_place_order', array($this, 'place_order'));
	}


	/**
	 * display orders
	 */
	public function show_orders() {
		global $wpdb;
		$plugin_data = get_plugin_data(__FILE__, 0, 0);

		$user_id = $_GET['user_id'];

		$soon = strtotime('+90days');

		if( !$user_id ){

			echo '<div class="notice notice-error"><p>no user id</p></div>';


		} else {
			//"SELECT * FROM {$wpdb->prefix}mequoda_orders WHERE ((user_id = %d) OR (donor_id = %d)) AND (recurly_result = 1) AND (recurly_result_message = 'success') AND NOT (payment_type = 'REFUND') ORDER BY order_time DESC", $user_id, $user_id
			$order_q = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mequoda_orders WHERE ( user_id = %d AND recurly_result = 1 ) AND (recurly_result_message = 'success') AND NOT (payment_type = 'REFUND') AND NOT (offer_code = 'event') AND correlation_id = '' ORDER BY order_time DESC", $user_id
			);

			$orders = $wpdb->get_results( $order_q );
			add_thickbox();

			$user = get_user_by( 'ID', $user_id );

?>
		<div class="wrap">
			<h1><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h1>
			<h2><?php _e('User Orders') ?></h2>
			<p>
				<?php
				echo "<h3>Account Information:</h3>";
				echo "<strong>Name:</strong> " . $user->first_name . ' ' . $user->last_name . '<br/>';
				echo "<strong>Email:</strong> " . $user->user_email;
				?>
			</p>
		</div>

		<table class="form-table">
			<thead>
			<tr>
				<td>
					<a class="button-secondary" href="/wp-admin/admin.php?page=haven_orders_place_order&user_id=<?php echo $user_id; ?>">Place an Order</a>
				</td>
				<td>
					<a class="button-secondary" href="/wp-admin/user-edit.php?user_id=<?php echo $user_id; ?>">Back to User's Profile</a>
				</td>
			</tr>
			<tr>
				<th>Date</th>
				<th class='left'>Product (click for order summary)</th>
				<th>Price</th>
				<th>Payment</th>
				<th>Offer</th>
				<th>Source</th>
				<th>Expires</th>
				<th>Auto-Renewal</th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php

				foreach ($orders as $key => $order) {

					$refund_sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mequoda_orders WHERE (correlation_id = %d) AND (payment_type = 'REFUND') AND (recurly_result = 1) AND (recurly_result_message = 'success') ORDER BY order_time DESC", esc_attr( $order->id ) );
					$refund     = $wpdb->get_results( $refund_sql, ARRAY_A );

					$renewals_sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mequoda_orders WHERE (correlation_id = %d) AND (payment_type != 'REFUND') AND (recurly_result = 1) AND (recurly_result_message = 'success') ORDER BY order_time DESC", esc_attr( $order->id ) );
					$renewals     = $wpdb->get_results( $renewals_sql, ARRAY_A );
				?>
				<tr <?php if($key % 2 == 0){ echo 'class="alternate"';}?> >
					<!-- Date -->
					<td>
						<?php echo date( 'Y-m-d', strtotime( $order->order_time ) ); ?>
					</td>

					<!-- Product and Summary -->
					<?php
					$name = esc_html($order->product_name);
					$name = (strlen($name) > 40) ? substr($name, 0, 40).'...' : $name;
					?>
					<td class='left'>
						<a href="#TB_inline?width=300&height=250&inlineId=order-summary-<?php echo esc_attr( $order->id ); ?>" class="thickbox" title="Order Summary"><b><?php echo $name; ?></b></a><br/>
						<?php if ($refund) { foreach ($refund as $r) { echo '<small>&nbsp;&nbsp;'.$r['order_summary'].' on '.date('M d, Y', strtotime($r['order_time'])).'<br/></small>'; } }



						echo '
									<div id="order-summary-' . esc_attr( $order->id ) . '" style="display:none;">
										<p>'. nl2br($order->order_summary) .'</p>
									</div>';



						if( $renewals ){

						?>
						<div style="margin-top: 10px;">
						<?php

						foreach( $renewals as $renewal ){
							echo '<small>Renewed on ' . $renewal['order_time'] . '<br/></small>';
						}
						?>

						</div>
					<?php
						}
					?>
					</td>

					<!-- Price -->
					<td>$<?php echo esc_html( $order->price ); ?></td>

					<!-- Payment and Payment Type-->
					<td>$<?php echo esc_html( $order->payment ); ?><br/><?php echo esc_html( $order->payment_type ); ?></td>

					<!-- Offer -->
					<td><a href="/wp-admin/admin.php?page=mqOfferManager&s=editOffer&offer=<?php echo esc_html( $order->offer_code ); ?>"><?php echo esc_html( $order->offer_code ); ?></a></td>

					<!-- Source -->
					<td><?php echo esc_html( $order->track ); ?></td>

					<!-- Expire Date -->
					<td>
						<?php

						if ( $order->cancelled != 'y' ) {
							$pub_id = get_pub_id( $order->product_id );
							// get the entittlments for the user
							$entitlements = load_entitlements( $user_id );

							if ( is_array( $entitlements ) ) {
								if( key_exists($pub_id, $entitlements ) ){
									foreach ( $entitlements[ $pub_id ] as $e ) {
										$expire_date = ( $e ) ? esc_html( date( 'Y-m-d', $e ) ) : 'Not found';
										$edit_date = date( 'm/d/Y', $e );
										echo "<b>" . $expire_date . '</b><br/>';
									}

									$print = false;
									if( $entitlements[ $pub_id ]['print'] ){
										$print = true;
									}
								}
							}


							if ( $print ) {
								echo '<div><a href="#" class="button button-primary removeprint" data-pubid="' . esc_attr( $pub_id ) . '" data-userid="' . esc_attr( $user_id ) . '">Remove Postal</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0px 10px 20px;background-position:0px 0;margin: 4px 2px 0;"></div></div>';
							} else {
								echo '<div><a href="#" class="button button-primary addprint" data-pubid="' . esc_attr( $pub_id ) . '" data-userid="' . esc_attr( $user_id ) . '">Add Postal</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0px 10px 20px;background-position:0px 0;margin: 4px 2px 0;"></div></div>';
							}

							if ( !$order->auto_renewal ) {
								echo '<p><a href="#TB_inline?width=300&height=250&inlineId=editexpiredate-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Edit Expire Date">Edit Expire Date</a></p>';

								echo '
										<div id="editexpiredate-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
											<p>Edit the expire date.</p>
											<p><label for="comp_expire_date">Expiration Date:</label></p>
											<input type="input" class="expire_date_edit" name="expire_date_edit" class="regular-text" value="' . $edit_date . '" style="margin-bottom:10px"/>
		                                    <a href="#" class="button button-primary editexpiredate" data-pubid="' . esc_attr( $pub_id ) . '" data-userid="' . esc_attr( $user_id ) . '">Update Expire Date</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
		50px;background-position:20px 0;"></div>
		
										</div>';
							} else {
								echo '<p><a href="#TB_inline?width=300&height=250&inlineId=editnextrenewaldate-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Change Renewal Date">Change Renewal Date</a></p>';

								echo '
										<div id="editnextrenewaldate-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
											<p>This subscription will automatically renew ' . $expire_date . '</p>
											<p><label for="comp_expire_date">Next Renewal Date:</label></p>
											<input type="input" class="renewal_date_edit" name="renewal_date_edit" class="regular-text" value="' . $expire_date . '" style="margin-bottom:10px"/>
		                                    <a href="#" class="button button-primary editnextrenewaldate" data-orderid="' . esc_attr( $order->id ) . '">Change Renewal Date</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
		50px;background-position:20px 0;"></div>
		
										</div>';
							}

						}


						?>
					</td>

						<!-- Auto Renew? -->
					<td>
						<?php
							if( $order->auto_renewal &&  $order->cancelled != 'y' ){
								if( $order->term == 1 ){
									echo "Renews<br/>Monthly";
								}else if( $order->term == 3 ){
									echo "Renews<br/>Quarterly";
								}else if( $order->term == 12 ){
									echo "Renews<br/>Yearly";
								}
							}
					?>
					</td>

					<!-- Actions -->
					<td>
						<?php

							// what is the difference?

							if ( $order->cancelled != 'y' ) {

								echo '<!-- begin --> <div id="cancelbuttons-' . esc_attr( $order->id ) . '">';

								if ( $order->auto_renewal ) {
									echo '<p><a href="#TB_inline?width=300&height=250&inlineId=cancel-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Cancel at Renewal">Cancel at Renewal</a></p>';

									echo '
									<div id="cancel-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
										<div><p>If you cancel this subscription, it will continue until <strong>' . $expire_date . '</strong>. On that date, the subscription will expire and will not be invoiced again. <!--This subscription can be reactivated before it expires.--></p></div>
	                                    <div><a href="#" class="button button-primary cancelatrenewal" data-orderid="' . esc_attr( $order->id ) . '">Cancel at Renewal</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
	50px;background-position:20px 0;"></div></div>
									</div>';

								}

								if( $e > time() && $order->payment_type != 'LEGACY' && $order->payment_type != 'COMP' ){

								echo '<p><a href="#TB_inline?width=300&height=250&inlineId=terminate-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Terminate Immediately">Terminate Immediately</a></p>';

								echo '
									<div id="terminate-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
										<p>Terminating will immediately end the subscription.
	You have the option to refund payment from the current billing cycle.</p>
										<p><strong>REFUND OPTIONS FOR SUBSCRIPTION FEES</strong><br/>
											<input type="radio" name="refund_type-' . esc_attr( $order->id ) . '" value="none" checked> No Refund<br>
										    <input type="radio" name="refund_type-' . esc_attr( $order->id ) . '" value="prorated"> Prorated Refund<br>
										    <input type="radio" name="refund_type-' . esc_attr( $order->id ) . '" value="full"> Full Refund</p>
	                                    <p><a href="#" class="button-primary terminateimmediately" data-orderid="' . esc_attr( $order->id ) . '">Terminate Subscription</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
	50px;background-position:20px 0;"></div></p>
									</div>';

								}else if( $e > time() && $order->payment_type == 'LEGACY' && $order->payment_type != 'COMP' ) {
									echo '<p><a href="#TB_inline?width=300&height=250&inlineId=cancellegacy-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Cancel Legacy">Cancel Legacy</a></p>';

									echo '
									<div id="cancellegacy-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
										<p>This order is an order created outside of Haven and imported as part of subscriber conversion.</p>
										<p>If you wish to cancel, this will ONLY update the entitlements in Haven. Refunds and any other updates need will need to happen in your legacy system manually.</p>
	                                    <div><a href="#" class="button-primary cancellegacy" data-orderid="' . esc_attr( $order->id ) . '">Cancel Legacy</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
	50px;background-position:20px 0;"></div></div>
									</div>';
								} else if( $e > time() && $order->payment_type == 'COMP' ) {
									echo '<p><a href="#TB_inline?width=300&height=250&inlineId=cancellegacy-modal-window-id-' . esc_attr( $order->id ) . '" class="thickbox button button-primary" title="Cancel Legacy">Cancel Comp</a></p>';

									echo '
									<div id="cancellegacy-modal-window-id-' . esc_attr( $order->id ) . '" style="display:none;">
										<p>This order is a Comp Order cancelling this will remove entitlements in Haven.</p>
	                                    <div><a href="#" class="button-primary cancellegacy" data-orderid="' . esc_attr( $order->id ) . '">Cancel Comp</a><div class="spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 
	50px;background-position:20px 0;"></div></div>
									</div>';
								}


								echo "</div><!-- end -->";

								// show renew button or not
								if( 'y' == $order->renewal_notice && $e < $soon ){
									echo '<p id="renewal-' . esc_attr( $order->id ) . '"><a href="/wp-admin/admin.php?page=haven_orders_place_order&user_id=' . $user_id .'&offer_id=' . $order->offer_code . '&paymenttype='.$order->payment_type .'" class="button button-primary"  value="Renew" />Renew</a></p>';
								}

							} else {

								echo 'Order Cancelled';

							}


						?>


				</tr>

			<?php } ?>
				<tr>
					<td>
						<a class="button-secondary" href="/wp-admin/admin.php?page=haven_orders_place_order&user_id=<?php echo $user_id; ?>">Place an Order</a>
					</td>
					<td>
						<a class="button-secondary" href="/wp-admin/user-edit.php?user_id=<?php echo $user_id; ?>">Back to User's Profile</a>
					</td>
				</tr>
			</tbody>
		</table>


<?php
		}
	}

	/**
	 * place order admin page.
	 */
	public function place_order(){
		$plugin_data = get_plugin_data(__FILE__, 0, 0);
		?>
		<div class="wrap">
			<h1><?php _e($plugin_data['Title']) ?> - Version <?php _e($plugin_data['Version']) ?></h1>
			<h2><?php _e('Place Order') ?></h2>
			<p>
				<?php
				$user_id = $_GET['user_id'];
				$user = get_userdata( $user_id );

				$offer_id = $_GET['offer_id'];

				$payment_type = strtolower( $_GET['paymenttype'] );

				echo "<h3>Account Information:</h3>";
				echo "<strong>Name:</strong> " . $user->first_name . ' ' . $user->last_name . '<br/>';
				echo "<strong>Email:</strong> " . $user->user_email;
				?>
			</p>
		</div>
<?php
		// process order form post
		if( 'placeorder' == $_POST['action'] ){

			global $mqOrderProcess;
			if (!is_a($mqOrderProcess, 'mqOrderProcess')) {
				$mqOrderProcess = new mqOrderProcess();
			}

			if( 'cc' == $_POST['payment_type'] ) {

				$args = array(
					'user_id'					=> $_POST['user_id'],
					'product_id'				=> $_POST['product_id'],
					'offer_id'					=> $_POST['offer_id'],
					'card_type'					=> $_POST['card_type'],
					'card_number'				=> $_POST['card_number'],
					'exp_month'					=> $_POST['exp_month'],
					'exp_year'					=> $_POST['exp_year'],
					'cvv'						=> $_POST['cvv'],
					'send_order_confirmation'	=> $_POST['send_order_confirmation'],
					'amt'                       => $_POST['amt']
				);

				if (! empty( $_POST['mqosc'])) {
					update_user_meta( $_POST['user_id'], 'mqosc', $_POST['mqosc'] );
				}

				$order = $mqOrderProcess->manual_order( $args );

				if( is_wp_error( $order ) ){

					echo '<div class="notice notice-error">';
					foreach( $order->errors as $error ){
						echo '<p>' . $error[0] . '</p>';
					}
					echo "<p><a href='/wp-admin/admin.php?page=haven_orders_place_order&user_id=". $args['user_id'] ."'>Place Order</a></p>";
					echo "</div>";

				} else {
					echo '<p class="notice-success">';
					echo 'The order was successfully placed.<br/>';
					echo "<a href='/wp-admin/admin.php?page=haven_orders&user_id=". $args['user_id'] ."'>View All Orders</a>";
					echo '</p>';
				}

			} else if( 'ck' == $_POST['payment_type'] ){

				$args = array(
					'user_id'					=> $_POST['user_id'],
					'product_id'				=> $_POST['product_id'],
					'offer_id'					=> $_POST['offer_id'],
					'check_number'				=> $_POST['check_number'],
					'amt'                       => $_POST['amt'],
					'card_type'                 => 'CK',
					'send_order_confirmation'	=> $_POST['send_order_confirmation'],
				);

				if (! empty( $_POST['mqosc'])) {
					update_user_meta( $_POST['user_id'], 'mqosc', $_POST['mqosc'] );
				}

				$order = $mqOrderProcess->manual_order( $args );

				if( is_wp_error( $order ) ){

					echo '<div class="notice notice-error">';
					foreach( $order->errors as $error ){
						echo '<p>' . $error[0] . '</p>';
					}
					echo "<p><a href='/wp-admin/admin.php?page=haven_orders_place_order&user_id=". $args['user_id'] ."'>Place Order</a></p>";
					echo "</div>";

				} else {
					echo '<p class="notice-success">';
					echo 'The order was successfully placed.<br/>';
					echo "<a href='/wp-admin/admin.php?page=haven_orders&user_id=". $args['user_id'] ."'>View All Orders</a>";
					echo '</p>';
				}

			} else if( 'comp' == $_POST['payment_type'] ){

				$args = array(
					'user_id'					=> $_POST['user_id'],
					'product_id'				=> $_POST['product_id'],
					'offer_id'					=> $_POST['offer_id'],
					'comp_subscription'         => true,
					'comp_expire_date'			=> $_POST['comp_expire_date'],
					'send_order_confirmation'	=> $_POST['send_order_confirmation'],
					'amt'                       => 0
				);

				$_POST['comp_subscription'] = true;

				if (! empty( $_POST['mqosc'])) {
					update_user_meta( $_POST['user_id'], 'mqosc', $_POST['mqosc'] );
				}

				$order = $mqOrderProcess->manual_order( $args );

				if( is_wp_error( $order ) ){

					echo '<div class="notice notice-error">';
					foreach( $order->errors as $error ){
						echo '<p>' . $error[0] . '</p>';
					}
					echo "<p><a href='/wp-admin/admin.php?page=haven_orders_place_order&user_id=". $args['user_id'] ."'>Place Order</a></p>";
					echo "</div>";

				} else {
					echo '<p class="notice-success">';
					echo 'The Comp order was successfully placed.<br/>';
					echo "<a href='/wp-admin/admin.php?page=haven_orders&user_id=". $args['user_id'] ."'>View All Orders</a>";
					echo '</p>';
				}

			}

			// show form
		} else {

			global $wpdb;

			$options = get_option('mqcms');
			if ( !isset( $options['product_post_type'] ) ) {
				echo 'There was an error';
				return;
			}
			// build params for querying products
			$cpt_array = explode( ',', esc_attr($options['product_post_type']) );
			for ( $i = 0; $i < count($cpt_array); $i++ ) {
				$cpt_array[$i] = trim($cpt_array[$i]);
			}
			$params = apply_filters('mq-cms-products-args', array(
				'post_type' => $cpt_array,
				'nopaging' => true,
				'orderby' => 'title',
				'order' => 'ASC'
			), $options);
			// get products
			$products_query = new WP_Query($params);
			
			if ($products_query->have_posts()) {
				while ($products_query->have_posts()) {
					$product_post = $products_query->next_post();
					$p = new stdClass();
					$p->id = apply_filters('mq-cms-product-id', $product_post->ID, $product_post);
					$p->title = apply_filters('mq-cms-product-title', $product_post->post_title, $product_post);
					if ($annual = get_post_meta($product_post->ID, 'is_annual_subscription', true)) {
						$p->price = apply_filters('mq-cms-product-price', get_post_meta($product_post->ID, 'price_annual', true), $product_post);
					} else {
						$p->price = apply_filters('mq-cms-product-price', get_post_meta($product_post->ID, 'price', true), $product_post);
					}
					$products[] = $p;
					$mqOffers = mqOfferManager::getInstance();
					$offers[$p->title] = $mqOffers->getOffers(array('orderBy' => 'headline', 'product_id' => $p->id, 'active' => true));
				}
				$products = apply_filters('mq-cms-post-products', $products);
			}
			if (empty($products)) {
				echo '<p>No products to order</p>';
				return;
			}
			
			include plugin_dir_path(__FILE__) . 'templates/place-order-cc-form.php';
		}
	}

	/**
	 * settings for plugin
	 */
	public function options() { ?>
		<div class="wrap">
			<h2><?php _e('Order Manager Settings') ?></h2>

			<form action="options.php" method="post" id="mq_recurly">
				<?php settings_fields( $this->_optionsGroup ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_supportemail">
								<?php _e('Support Email:', 'mq-haven-order-manager') ?>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[supportemail]" value="<?php echo esc_attr($this->_settings['supportemail']); ?>" id="<?php echo $this->_optionsName; ?>_supportemail" class="regular-text code" />
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

}
// Instantiate our class
$mqOrderManager = new mqOrderManager();

add_action( 'plugins_loaded', 'remove_place_order' );
function remove_place_order(){
	global $mequodaCMS;
	remove_action( 'show_user_profile', array($mequodaCMS, 'show_place_order'), 9 );
	remove_action( 'edit_user_profile', array($mequodaCMS, 'show_place_order'), 9 );
}

/**
 * handles turing off entitlements/cancels for imported orders
 */
add_action( 'wp_ajax_cancel_legacy', 'mom_cancel_legacy_ajax' );

function mom_cancel_legacy_ajax(){

	$order_id = $_POST['order_id'];

	global $wpdb;
	$sql = $wpdb->prepare( "select * from wp_mequoda_orders where id = %s", $order_id );
	$order = $wpdb->get_row( $sql );

	// update original order and cancel it
	$table = 'wp_mequoda_orders';
	$result = $wpdb->update(
		$table,
		array(
			'cancelled' => 'y',	// string
			'renewal_notice' => 'n'
		),
		array( 'ID' => $order->id ),
		array(
			'%s', //cancelled
			'%s' // renewal notice

		),
		array( '%d' )
	);

	do_action( 'mom_cancel_legacy', $order );

	if( $result ){
		$cancel_result = 'success';
	} else {
		$cancel_result = 'Error Cancelling Order';
	}


	die( $cancel_result );

}

/**
* handles turning off postal data
 */
add_action( 'wp_ajax_remove_postal', 'mom_remove_postal_ajax' );

function mom_remove_postal_ajax(){

	$pub_id = $_POST['pub_id'];
	$user_id = $_POST['user_id'];

	$result = delete_entitlement( $user_id, $pub_id, 'print' );

	if ( $result !== false ) {
		$cancel_result = 'success';
	} else {
		$cancel_result = 'Error removing Postal';
	}

	die ( $cancel_result );

}

/**
 * handles turing off postal data
 */
add_action( 'wp_ajax_add_postal', 'mom_add_postal_ajax' );

function mom_add_postal_ajax(){

	$pub_id = $_POST['pub_id'];
	$user_id = $_POST['user_id'];

	$result = false;

	$entitlements = load_entitlements( $user_id );

	if ( $entitlements && is_array( $entitlements ) ) {

		$expire_date = $entitlements[$pub_id]['web'];

		if ( $expire_date ) {

			$result = insert_entitlement( $user_id, $pub_id, 'print', $expire_date, $expire_date );

		}

	}

	if ( $result ) {
		$cancel_result = 'success';
	} else {
		$cancel_result = 'Error adding Postal';
	}

	die ( $cancel_result );

}

/**
 * handles editing expire dates
 */
add_action( 'wp_ajax_edit_expire_date', 'mom_edit_expire_date_ajax' );

function mom_edit_expire_date_ajax(){

	$pub_id = $_POST['pub_id'];
	$user_id = $_POST['user_id'];
	$expire_date = strtotime( $_POST['expire_date'] );

	$result = insert_entitlement($user_id, $pub_id, 'web', $expire_date, $expire_date);

	$entitlements = load_entitlements( $user_id );

	if ( array_key_exists( 'print', $entitlements[ $pub_id ] ) ) {
		$result_print = insert_entitlement($user_id, $pub_id, 'print', $expire_date, $expire_date);
		if ( ! $result || ! $result_print ) { $result = false; }
	}

	do_action( 'mequoda-expiration-date-edit', array( 'user_id' => $user_id, 'pub_id' => $pub_id, 'expire_date' => $expire_date ) );

	if ( $result ) {
		$cancel_result = 'success';
	} else {
		$cancel_result = 'Error editing expire date';
	}

	die( $cancel_result );

}

