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

/*
 * Previous version using google maps api.
 * following is just a test but since i have too many to lookup i quickly exceed the daily limit.
 * Thus abandoned for above.

 $sql = $wpdb->prepare( "SELECT user_id, meta_value
        FROM `wp_usermeta`
        WHERE `user_id` IN (628442,628458,628514,628561,628662,629128,629213,629248,629472,629605)
        AND meta_key = 'raw_json_address'", '' );

$raw_addresses = $wpdb->get_results($sql);

foreach ( $raw_addresses as $json_address ){

    $address = json_decode( $json_address->meta_value);

    echo "<p>";
    echo "<br/>user_id: " . $json_address->user_id;
    echo "<br/>zip_postalcode: " . $address->zip_postalcode;

    $requestAddress = "http://maps.googleapis.com/maps/api/geocode/json?address={$address->zip_postalcode}&sensor=false";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestAddress);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $xml_str = curl_exec($ch);

    echo "<br/>";
    $results = json_decode( $xml_str );

    $latlgn = $results->results[0]->geometry->location->lat .','.$results->results[0]->geometry->location->lng;
    echo "lat,lgn: " . $latlgn;

    echo "<br/>";
    $requestLatLgn = "https://maps.googleapis.com/maps/api/timezone/json?location={$latlgn}&timestamp=1406569985";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestLatLgn);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $xml_str = curl_exec($ch);

    $resultLatLng =  json_decode($xml_str);

    echo 'timezone: ' . $resultLatLng->timeZoneId;

    xprofile_set_field_data( 'tz_locale', $json_address->user_id, $resultLatLng->timeZoneId );
    wp_cache_delete($json_address->user_id, 'usermeta_and_xprofile');

    echo "</p>";

    usleep(100000);
}
*/
?>

