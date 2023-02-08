<?php
/*
Cron to process users/emails. runs ~1am EST every morning
 */

define('DONOTCACHEDB', true);
define('DONOTCACHEPAGE', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

// LOAD WORDPRESS
$root = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
require_once($root . '/wp-load.php');

// SET OUR $ts VARIABLE
$ts = ( defined('TEST_CRONS' ) ) ? TEST_CRONS : false;
$ts = true; // debug

// DECIDE IF WE CAN RUN OUR CRON
if ( ( php_sapi_name() == 'cli' ) || ( $ts ) ) {

	global $wpdb;

	$user_create_date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
	// one time larger clean up
	// $user_create_date->modify( '-90 day' );
	$user_create_date->modify( '-1 day' );
	$user_registered = $user_create_date->format( 'Y-m-d H:i:s' );

	// first get all users that were created in last 24 hours
	$user_id_select = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM wp_users WHERE user_registered >= %s;", $user_registered ) );

	// get settings for sending emails
	$settings = get_option( 'bad-domain-unsubscriber' );
	$to = $settings['sendtoemail'];
	$subject = 'Bad Domains Unsusbcribe';

	// check if we have users to process
	if( count( $user_id_select ) > 0 ){

		// create the u.ID IN() statement
		$user_ids_in = 'u.ID IN(' . implode( ',', $user_id_select ) . ')';

		// then get all the bad domains
		$domains_select = $wpdb->get_col( "SELECT * FROM wp_mequoda_bad_domain_unsubscriber;" );

		// create all the `user_email` LIKE  statement for the domains
		$bad_domains = '';
		$domain_count = count( $domains_select ) - 1;
		foreach( $domains_select as $key => $domain ){
			$bad_domains .= "(`user_email` LIKE '%@{$domain}')";

			if( $domain_count != $key ){
				$bad_domains .=" OR ";
			}
		}

		// main query
		$sql = "SELECT DISTINCT(u.ID),u.user_email, e.expires, FROM_UNIXTIME(e.expires) as expire_date FROM `wp_users` u
left join wp_mequoda_entitlements e ON e.user_id = u.ID
WHERE {$user_ids_in} AND ( {$bad_domains} );";

		$unsubscribes = $wpdb->get_results( $sql );

		// only process if we found anyone
		if( count( $unsubscribes ) > 0 ) {

			$mqWhatCountsFramework = mqWhatCountsFramework::getInstance();
			$wc_lists = $mqWhatCountsFramework->getSetting( 'lists' );
			$wc_fields = $mqWhatCountsFramework->getSetting( 'fields' );

			$wc_unsubscribe = array_merge( $wc_lists, $wc_fields );

			foreach ($wc_unsubscribe as $key => $value) {
				$subscribed_lists[$value['name']] = str_ireplace('Receive ', '', $value['label']);
			}

			$unsub_lists = array_keys($subscribed_lists);

			$results = array();

			foreach( $unsubscribes as $unsub ){
				try {
					$unsub_result = $mqWhatCountsFramework->unsubscribe( $unsub_lists, $unsub->ID );

					if( is_array( $unsub_result ) ) {
						$results[] = array( 'success', $unsub, $unsub_result );
					} else {
						$results[] = array( 'error', $unsub, $unsub_result );
					}

				} catch (Exception $e) {
					$results[] = array( 'error', $unsub, $e->getMessage() );
				}

			}

			$message = "Result,User Id,Email,Expires,Expire Date,Error". "\n";

			foreach( $results as $result ){

				$userdata = $result[1];
				if( 'success' == $result[0] ){
					$message .= "unsubscribed,{$userdata->ID},{$userdata->user_email},{$userdata->expires},{$userdata->expire_date}" . "\n";
				} else {
					$error = $result[2];
					$message .= "error,{$userdata->ID},{$userdata->expires},{$userdata->expire_date},{$error}" . "\n";
				}

			}

			wp_mail( $to, $subject, $message );

		} else {
			// no users matched any bad domains
			$message = "No users found to unsubscribe";
			wp_mail( $to, $subject, $message );
		}

	} else {
		// no newly registered users
		$message = "No newly registered users found";
		wp_mail( $to, $subject, $message );
	}

}