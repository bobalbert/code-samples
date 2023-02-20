<?php
// Load the WordPress Environment
// define( 'WP_DEBUG', true ); /* uncomment for debug mode */
require('./wp-load.php');
//require_once ('./wp-admin/admin.php'); /* uncomment for is_admin() */
?>

<?php

global $wpdb;

$starttime = date("h:i:sa");
error_log("Starttime: {$starttime}" ."\n",3,'/var/log/korrio/tz_locale_error.log');
error_log("Starttime: {$starttime}" ."\n",3,'/var/log/korrio/tz_locale_success.log');

$theusers = $wpdb->get_results("SELECT ID
	FROM wp_users
	WHERE ID NOT IN
		(SELECT wp_users.ID FROM wp_users
			LEFT JOIN wp_bp_xprofile_data ON wp_users.ID = wp_bp_xprofile_data.user_id
			WHERE wp_bp_xprofile_data.field_id = '131' AND wp_bp_xprofile_data.value != '') ORDER BY ID DESC limit 50000;");


// need to add in the Canadan provinces
$tz_states = array (
    'America/Los_Angeles'=>array('CA', 'NV', 'OR', 'WA'),
    'America/New_York'=>array('CT', 'DE', 'FL', 'GA', 'ME', 'MD', 'MA', 'NH', 'NJ', 'NY', 'NC', 'OH', 'PA', 'RI', 'SC', 'VT', 'VA', 'DC', 'WV'),
    'America/Anchorage'=>array('AK'),
    'Pacific/Honolulu'=>array('HI'),
    'America/Boise'=>array('ID'),
    'America/Chicago'=>array('AL', 'AR', 'IL', 'IA', 'KS', 'LA', 'MN', 'MS', 'MO', 'NE', 'OK', 'SD', 'TN', 'TX', 'WI'),
    'America/Denver'=>array('CO', 'MT', 'NM', 'UT', 'WY'),
    'America/Detroit'=>array('MI'),
    'America/Indiana/Indianapolis'=>array('IN'),
    'America/Kentucky/Louisville'=>array('KY'),
    'America/North_Dakota/Center'=>array('ND'),
    'America/Phoenix'=>array('AZ'),
    'America/Vancouver'=>array('BC'),
    'America/Toronto'=>array('ON', 'QC'),
    'America/Edmonton'=>array('AB'),
    'America/Winnipeg'=>array('MB'),
    'America/Regina'=>array('SK')
);

foreach ($theusers as $user){

    //get json address
    $address = json_decode( get_user_meta($user->ID, 'raw_json_address', true) );

    if ($address->state_province) {

        //find timezone
        $timezone = false;
        foreach ( $tz_states as $tz => $states ) {
            if ( in_array($address->state_province, $states) ) {
                $timezone = $tz;
                break;
            }
        }

        if ( $timezone ) {
            //update timezone
            xprofile_set_field_data( 'tz_locale', $user->ID, $timezone );
            wp_cache_delete( $user->ID, 'usermeta_and_xprofile' );
            error_log("{$user->ID}, {$address->state_province}, {$timezone}" ."\n",3,'/var/log/korrio/tz_locale_success.log');
        }else{
            //no timezone found
            error_log("no timezone found, $user->ID, {$address->state_province}" ."\n",3,'/var/log/korrio/tz_locale_error.log');
        }

    }else{
        //no address for user
        error_log("no state found, $user->ID" ."\n",3,'/var/log/korrio/tz_locale_error.log');
    }

}

$endtime = date("h:i:sa");

error_log("Starttime: {$starttime}, Endtime: {$endtime}" ."\n",3,'/var/log/korrio/tz_locale_error.log');
error_log("Starttime: {$starttime}, Endtime: {$endtime}" ."\n",3,'/var/log/korrio/tz_locale_success.log');

echo "done!";

?>

