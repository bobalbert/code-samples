<?php
// turn off all cache
define('DONOTCACHEDB', true);
define('DONOTCACHEPAGE', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHEOBJECT', true);

define( 'SENDGRID_REQUEST_TIMEOUT', 30 );

// LOAD WORDPRESS
$root = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
require_once($root . '/wp-load.php');

// SET OUR $ts VARIABLE
$ts = (defined('TEST_CRONS')) ? TEST_CRONS : false;

// DECIDE IF WE CAN RUN OUR CRON
if ( ( php_sapi_name() == 'cli' ) || ( $ts ) ) {

	global $wpdb;

	// get the dates for the filename (current month), last month's and current month's dates
	$filedate = date('m-Y' );
	$current_month = 'print_' . date( 'Ym');
	$previous_month = 'print_' . date( 'Ym', strtotime('-1 month' ) );

	// user MQ settings Download_path value for saving reports. update for the new folder.
	$reports_path = str_replace(  '/products/', '/reconciliation_reports',DOWNLOAD_PATH);

	// get all the user ids of users that have last month's print_YYYYMM meta value true
	$print_previous_month = $wpdb->get_col(
		"
	SELECT user_id 
	FROM wp_usermeta
	WHERE meta_key = '{$previous_month}'
	AND meta_value = 1
	"
	);

	// get all the user ids of users that have current month's print_YYYYMM meta value true
	$print_current_month = $wpdb->get_col(
		"
	SELECT user_id 
	FROM wp_usermeta
	WHERE meta_key = '{$current_month}'
	AND meta_value = 1
	"
	);

	// get all the user ids of users that have last month's print_YYYYMM meta value false
	$subscriber_previous_month = $wpdb->get_col(
		"
	SELECT user_id 
	FROM wp_usermeta
	WHERE meta_key = '{$previous_month}'
	AND meta_value = 0
	"
	);

	// get all the user ids of users that have current month's print_YYYYMM meta value false
	$subscriber_current_month = $wpdb->get_col(
		"
	SELECT user_id 
	FROM wp_usermeta
	WHERE meta_key = '{$current_month}'
	AND meta_value = 0
	"
	);

	// ADD to Print
	// compare the lists to get the people.
	// Everyone that is in the current month but not in the previous month
	$diff_add = array_diff( $print_current_month, $print_previous_month );

	// REMOVE from Print
	// compare the lists to get the people.
	// Everyone that was in last months list, but is not in the current month
	$diff_remove = array_diff( $print_previous_month, $print_current_month );

	// compare the lists to get the people.
	// Everyone that is in the current month but not in the previous month
	// new existing print users that are now subscribed to a news letter
	$subscriber_remove = array_diff( $subscriber_current_month, $subscriber_previous_month );

	$diff_remove = array_merge( $diff_remove, $subscriber_remove );
	$diff_remove = array_unique( $diff_remove );

	// create a data array/list of the ADDs
	// AAA-598 for large data sets consumes too much memory/time.
	/*$add_data = array();
	foreach( $diff_add as $add_user_id ){
		$add_user = get_user_by('ID', $add_user_id );
		$add_data[] = array($add_user->user_email);
	}*/

	//get all the user ids of the adds for email query
	$add_ids = implode( ',', $diff_add );

	$add_emails = $wpdb->get_col(
		"
	SELECT user_email
	FROM wp_users
	WHERE ID IN ( {$add_ids} )
	"
	);

	// create a data array/list of the REMOVEs
	// AAA-598 for large data sets consumes too much memory/time.
	/*$remove_data = array();
	foreach( $diff_remove as $remove_user_id ){
		$remove_user = get_user_by('ID', $remove_user_id );
		$remove_data[] = array($remove_user->user_email);
	}*/

	//get all the user ids of the removes for email query
	$remove_ids = implode( ',', $diff_remove );

	$remove_emails = $wpdb->get_col(
		"
	SELECT user_email
	FROM wp_users
	WHERE ID IN ( {$remove_ids} )
	"
	);

	// create the filenames
	// examples: add-to-print-01-2018.csv | remove-from-print-01-2018.csv
	$add_filename = "add-to-print-" . $filedate . ".csv";
	$remove_filename = "remove-from-print-" . $filedate . ".csv";

	// set the file path to the sub folders for add and remove
	$add_filepath = $reports_path.'/add/'.$add_filename;
	$remove_filepath = $reports_path.'/remove/'.$remove_filename;

	/* create add to file */
	$fd_add = fopen( $add_filepath, 'w' );
	if($fd_add === FALSE) {
		die('Failed to open temporary file');
	}

	// add the data to the csv file and save in the /private/reconciliation_reports/add/ folder
	foreach ( $add_emails as $d ) {
		fputcsv($fd_add, array( $d ), ',');
	}
	rewind($fd_add);
	fclose($fd_add);


	/* create remove from file */
	$fd_remove = fopen( $remove_filepath, 'w' );
	if($fd_remove === FALSE) {
		die('Failed to open temporary file');
	}

	// add the data to the csv file and save in the /private/reconciliation_reports/remove/ folder
	foreach ( $remove_emails as $d ) {
		fputcsv($fd_remove, array( $d ), ',');
	}
	rewind($fd_remove);
	fclose($fd_remove);

	// Setup the mail
	// wp_mail( $to, $subject, $message, $headers = '', $attachments = array() )
	$from_name = 'Mequoda Support';
	$from_email = 'support@meqouda.com';
	$headers = "From: ".$from_name." <".$from_email.">\n";

	// Get the mailto address from plugin settings
	$settings = get_option( 'print-subscriber-reconciliation' );
	$sendto = $settings['sendtoemail'];

	//send email via wp_mail especially for MT sites.
	$result_add = wp_mail( $sendto, 'AAA Add to Print', 'Add to Print List - file attached', $headers, array( $add_filepath ) );
	//send email via wp_mail especially for MT sites.
	$result_remove = wp_mail( $sendto, 'AAA Remove from Print', 'Remove from Print List -  file attached', $headers, array( $remove_filepath ) );

}

exit;