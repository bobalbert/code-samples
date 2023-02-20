<?php
// turn off all cache
define('DONOTCACHEDB', true);
define('DONOTCACHEPAGE', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

// LOAD WORDPRESS
$root = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
require_once($root . '/wp-load.php');

// SET OUR $ts VARIABLE
$ts = (defined('TEST_CRONS')) ? TEST_CRONS : false;

// DECIDE IF WE CAN RUN OUR CRON
if ( ( php_sapi_name() == 'cli' ) || ( $ts ) ) {

	global $wpdb;

	// get current month
	$current_month = 'print_' . date( 'Ym');

	// First delete all users with current month date
	$sql_delete_all = $wpdb->prepare( "DELETE FROM wp_usermeta WHERE meta_key = %s;", $current_month );
	$result_delete_all = $wpdb->get_results( $sql_delete_all );

	// mark all users with current month date
	$sql_mark_all = $wpdb->prepare( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
			SELECT ID, %s, 1 FROM wp_users;", $current_month );
	$result_mark_all = $wpdb->get_results( $sql_mark_all );

	// update those with a subscription to one or more newsletters
	$newsletters = array(
		'sub_aaa_daily',
		'sub_aaa_extra',
		'sub_aaa_guides',
		'sub_aaa_spotlight',
		'sub_aaa_weekly'
	);

	// update for each newsletter
	foreach( $newsletters as $newsletter ){
		$sql_has_newsletter = $wpdb->prepare( "UPDATE wp_usermeta m1
			JOIN wp_usermeta m2
			ON m1.user_id = m2.user_id AND m1.meta_key = %s AND m2.meta_key = %s 
			SET m1.meta_value = 0;",
			$current_month, $newsletter
		);
		$result_has_newsletter = $wpdb->get_results( $sql_has_newsletter );

	}
}