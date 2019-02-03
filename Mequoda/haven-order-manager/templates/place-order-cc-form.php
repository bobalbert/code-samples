
<script>
	jQuery(document).ready(function($) {

		$('#payment_type').change(function () {
			var payment_type =  $(this).val();

			if( payment_type == 'cc' ){
				$('.cc_fields').show();
				$('.ck_fields').hide();
				$('.comp_fields').hide();
			} else if( payment_type == 'ck' ) {
				$('.cc_fields').hide();
				$('.ck_fields').show();
				$('.comp_fields').hide();
			} else if( payment_type == 'comp') {
				$('.cc_fields').hide();
				$('.ck_fields').hide();
				$('.comp_fields').show();
			} else {
				$('.cc_fields').hide();
				$('.ck_fields').hide();
				$('.comp_fields').hide();
			}

		});

		jQuery('#offer_id').change(function(){
			var amt = jQuery(this).find(':selected').data('amt');
			var product_id = jQuery(this).find(':selected').parent().data('product');
			if (amt) {
				jQuery('#amt').val(amt);
				jQuery('#product_id').val(product_id);
			}
		});

		<?php
		if( $_GET['offer_id'] ){
			echo "var amt = jQuery(this).find(':selected').data('amt');
			var product_id = jQuery(this).find(':selected').parent().data('product');
			if (amt) {
				jQuery('#amt').val(amt);
				jQuery('#product_id').val(product_id);
			}";
		}

		?>

		<?php
		if( 'ck' == $payment_type ){
			echo "$('.cc_fields').hide();
				$('.ck_fields').show();
				$('.comp_fields').hide();";
		}else if( 'cc' == $payment_type ){
			echo "$('.cc_fields').show();
				$('.ck_fields').hide();
				$('.comp_fields').hide();";
		}else if( 'comp' == $payment_type ){
			echo "$('.cc_fields').hide();
				$('.ck_fields').hide();
				$('.comp_fields').show();";
		}

		?>

		//@todo bit of a hack. maybe should include more robust testing via jquery validate plugin
		$('#submit').on('click', function(e){
			var pt = $('#payment_type').val();
			var offer = $('#offer_id').val();

			if( pt == null || pt == '' ){
				e.preventDefault();
				alert('Please select payment type.');
			}

			if( offer == null || offer == '' ){
				e.preventDefault();
				alert('Please select an offer!');
			}

			if( 'cc' == pt ){

				var errors = '';

				if( '' == $( '#card_type' ).val() ){
					errors += 'Card Type Required.\n';
				}

				if( '' == $( '#card_number' ).val() ){
					errors += 'Card Number Required\n';
				}

				if( '' == $( '#exp_month' ).val() ){
					errors += 'Expire Month Required\n';
				}

				if( '' == $( '#exp_year' ).val() ){
					errors += 'Expire Year Required\n';
				}

				/*if( '' == $( '#cvv' ).val() ){
					errors += 'Security Code Required\n';
				}*/

				if( '' != errors ){
					e.preventDefault();
					alert( errors );
				}
			}

		});

	});

</script>

<style>
	.cc_fields, .ck_fields, .comp_fields{
		display:none;
	}
	.order-form-table h3{
		margin: 0px;
	}
</style>

<form method="post" action="/wp-admin/admin.php?page=haven_orders_place_order&user_id=<?php echo $user_id; ?>">

	<table class="form-table order-form-table">
		<tbody>
			<tr>
				<td>
					<a class="button-secondary" href="/wp-admin/user-edit.php?user_id=<?php echo $user_id; ?>">Back to User's Profile</a>
				</td>
				<td>
					<a class="button-secondary" href="/wp-admin/admin.php?page=haven_orders&user_id=<?php echo $user_id; ?>">View all orders.</a>
				</td>
			</tr>
		<!--<tr>
			<th scope="row">
				<label for="product_id">Product:</label>
			</th>
			<td>
				<select id="product_id2" name="product_id2">
					<option value="">Choose Product</option>
					<?php
					$product_ids = array();
					foreach ( $products as $product ) {
						$price = $attr = '';
						$product_id[$product->title] = $product->id;
						if ( ! empty( $product->price ) ) {
							$price = ' ($' . esc_html( $product->price ) . ')';
							$attr = ' price="' . esc_attr( $product->price ) . '" data-price="' . esc_attr( $product->price ) . '"';
						}

						//echo "<option value='" . esc_attr( $product->id ) . "' " . $attr . ">";
						//echo esc_html( $product->title ) . $price . "</option>";
					}
					?>
				</select>
			</td>
		</tr>-->
		<?php
		$product_name = apply_filters('mqOrderManager_product', 'Subscription');
		?>
		<tr>
			<th><label for="offer_id"><?php echo $product_name; ?>:</label></th>
			<td>
				<select id="offer_id" name="offer_id">
					<option value="">Choose <?php echo $product_name; ?></option>
					<?php
					foreach ( $offers as $pub_name => $pub ) {

						echo '<optgroup label="' . $pub_name . '" class="product-id-' . $product_id[$pub_name] . '" data-product="' . $product_id[$pub_name] . '">"';

						foreach( $pub as $offer ) {
							$amt = $attr = '';
							if ( ! empty( $offer->amt ) ) {
								$amt = ' ($' . esc_html( $offer->amt ) . ')';
								$attr .= ' data-amt="' . esc_attr( $offer->amt ) . '"';
							}
							if ( ! empty( $offer->freq ) ) {
								$attr .= ' data-freq="' . esc_attr( $offer->freq ) . '"';
							}
							if ( ! empty( $offer->period ) ) {
								$attr .= ' data-period="' . esc_attr( $offer->period ) . '"';
							}

							if ( $offer->active ) {
								echo "<option value='" . esc_attr( $offer->id ) . "'" . $attr . " " . selected( $offer_id,  $offer->id ) .">";
								echo esc_html( $offer->pay_headline ) . $amt . "</option>";
							}
						}
						echo "</optgroup>";
					}
					?>
				</select>
			</td>
		</tr>

		<tr>
			<th><label for="mqosc">OSID:</label></th>
			<td><input type="text" id="mqosc" name="mqosc" class="regular-text" value="<?php echo esc_attr( empty( $user->mqosc ) ? 'OFFLINE' : $user->mqosc ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="mqsc">ASID:</label></th>
			<td><input type="text" id="mqsc" name="mqsc" class="regular-text" value="<?php echo esc_attr( empty( $user->mqsc ) ? 'OFFLINE': $user->mqsc ); ?>" /></td>
		</tr>

		<tr>
			<th></th>
			<td><input type="checkbox" value="checked" name="send_order_confirmation" checked="checked" id="send_order_confirmation" />
				<label for="send_order_confirmation">Send Order Confirmation Email</label></td>
		</tr>

		<tr>
			<th><h3>Payment</h3></th>
		</tr>

		<tr>
			<th><label for="payment_type">Payment Type</label></th>
			<td>
				<select required aria-required="true" id="payment_type" name="payment_type">
					<option value="">Select Payment Type</option>
					<option value="ck" <?php selected( $payment_type,  'ck');?> >Check</option>
					<option value="cc" <?php selected( $payment_type,  'cc');?>>Credit Card</option>
					<option value="comp" <?php selected( $payment_type,  'comp');?>>Comp</option>
				</select>
			</td>
		</tr>

			<tr class='comp_fields'>
				<th><label for="comp_expire_date">Comp Expiration Date:</label></th>
				<td><input type="input" id="comp_expire_date" name="comp_expire_date" class="regular-text" value="<?php echo date('m/d/Y', strtotime( '+1 month') );?>" /></td>
			</tr>

		<tr class='cc_fields'>
			<th><label for="card_type">Card Type:</label></th>
			<td>
				<select style="margin-bottom: 0pt;" id="card_type" name="card_type" class="textbox">
					<option value="">Select</option>
					<?php
					foreach ( $this->_credit_card_types as $key => $value ) {
						echo "<option value='" . esc_attr( $key ) . "'>" . esc_html( $value ) . "</option>";
					}
					?>
				</select>
			</td>
		</tr>
		<tr class='ck_fields'>
			<th><label for="check_number">Check Number:</label></th>
			<td><input type="text" id="check_number" name="check_number" class="regular-text" value="" /></td>
		</tr>

		<tr class='cc_fields'>
			<th><label for="card_number">Card Number:</label></th>
			<td><input type="text" maxlength="16" id="card_number" name="card_number" class="textbox" /></td>
		</tr>
		<tr class='cc_fields'>
			<th><label for="exp_month">Expiration Date:</label></th>
			<td>
				<select name="exp_month" id="exp_month" style="min-width: 120px;">
					<option value="">Select Month</option>
					<?php
					foreach ( range(1, 12) as $n ) {
						$n = str_pad( $n, 2, "0", STR_PAD_LEFT );
						echo "<option value='" . $n . "'>" . $n . " - " . date('F', strtotime('2015-'.$n.'-10')) . "</option>";
					}
					?>
				</select>&nbsp;/
				<select name="exp_year" id="exp_year" style="min-width: 100px;">
					<option value="">Select Year</option>
					<?php
					for ( $x = 0; $x < 10; $x++ ) {
						$year = date( 'Y' ) + $x;
						echo "<option value='" . $year . "'>" . $year . "</option>";
					}
					?>
				</select>
			</td>
		</tr>
		<tr class='cc_fields'>
			<th><label for="cvv">Security Code:</label></th>
			<td><input type="text" size="4" maxlength="4" id="cvv" name="cvv" class="small-text"></td>
		</tr>

			<input type="hidden" id="ignore_cvv" name="ignore_cvv" value="1" />
		</tbody>
	</table>

	<input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
	<input type="hidden" id="amt"        name="amt"  />
	<input type="hidden" id="product_id" name="product_id" />
	<input type="hidden" name="address"     value="<?php echo esc_attr( $user->address ); ?>"/>
	<input type="hidden" name="address2"    value="<?php echo esc_attr( $user->address2 ); ?>"/>
	<input type="hidden" name="city"        value="<?php echo esc_attr( $user->city ); ?>"/>
	<input type="hidden" name="state"       value="<?php echo esc_attr( $user->state ); ?>"/>
	<input type="hidden" name="zip_code"    value="<?php echo esc_attr( $user->zip_code ); ?>"/>
	<input type="hidden" name="country"     value="<?php echo esc_attr( $user->country ); ?>"/>

	<input type="hidden" name="action" value="placeorder" />

	<?php submit_button( __( 'Place Order' ) ); ?>

</form>

<hr/>