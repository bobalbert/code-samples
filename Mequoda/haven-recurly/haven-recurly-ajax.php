<?php
/**
 * Created by PhpStorm.
 * User: balbert
 * Date: 7/12/16
 * Time: 4:33 PM
 */


add_action( 'wp_ajax_cancel_at_renewal', 'mqr_cancel_at_renewal_ajax' );
add_action( 'wp_ajax_terminate_immediately', 'mqr_terminate_immediately_ajax' );
add_action( 'wp_ajax_refund_invoice', 'mqr_refund_invoice_ajax' );

// extend the next renewal date for auto renewal subs
add_action( 'wp_ajax_edit_renewal_date', 'mqr_change_renewal_ajax' );


function mqr_cancel_at_renewal_ajax() {
	
	global $mqRecurly;
	
	$order_id = $_POST['order_id'];
	
	$result = $mqRecurly->cancelAtRenewal( $order_id );
	
	die( $result );
}

function mqr_terminate_immediately_ajax(){

	global $mqRecurly;

	$order_id = $_POST['order_id'];
	$refund_type = $_POST['refund_type'];

	$result = $mqRecurly->terminateImmediately( $order_id, $refund_type );
	
	die( $result );
}

function mqr_refund_invoice_ajax(){

	global $mqRecurly;
	$order_id = $_POST['order_id'];
	$refund_type = $_POST['refund_type'];
	$user_id = $_POST['user_id'];

	$result = $mqRecurly->refundInvoice( $order_id, $refund_type, $user_id );

	die( $result );
}

function mqr_change_renewal_ajax(){

	global $mqRecurly;

	$order_id = $_POST['order_id'];
	//$date = '2017-04-01T09:00:00-05:00';

	$renewal_date = date('Y-m-d', strtotime( $_POST['renewal_date'] ) );
	$renewal_date = $renewal_date . 'T09:00:00-05:00';

	$result = $mqRecurly->postponeSubscription( $order_id, $renewal_date );

	die( $result );
}