<?php
// override some settings for these big files
ini_set('memory_limit','15048M');
ini_set( 'max_execution_time', '600' );

// DEV server
$servername = "thedbserverurl";
// DEVLOCAL
//$servername = "127.0.0.1";

// not the real data for code samples
$username = "thedbusername";
$password = "thedbpassword";
$dbname = "thedbname";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// get the local absolut file path
$server_path = __DIR__;

// logging
$when = date('Y-m-d H:i:s');
$where = $server_path . '/email-exchange/compare_tool.log';
//error_log("[{$when}] start: " . $where . "\n",3, $where );

// Check connection
if ($conn->connect_error) {
	$when = date('Y-m-d H:i:s');
	error_log("[{$when}]  Connection failed: " . $conn->connect_error . "\n",3, $where );
	die("Connection failed: " . $conn->connect_error);
}

// debug
// $conn->query("UPDATE settings SET value = '{$when}' WHERE name = 'cron';");

$sql = "SELECT * FROM settings WHERE name = 'compare';";
$result = $conn->query($sql);

$compare = false;

// see if we should compare files based on settings.
if ( $result->num_rows > 0 ) {
	while( $row = $result->fetch_assoc() ) {
		if( 'compare' == $row['name'] && 'true' == $row['value'] ){
			$compare = true;
		}
	}
}

if ( $compare ) {

	$when = date('Y-m-d H:i:s');
	error_log("[{$when}]  Start Processing!" . "\n",3, $where );

	// reset the settings so we don't reprocess
	$result = $conn->query( "UPDATE settings SET value = 'false' WHERE name = 'compare';");
	$conn->close();

	// look for files in the 3rd party list directory
	$dir = opendir( $server_path . '/email-exchange-other/' );
	while ( $file = readdir( $dir ) ) {

		if ( $file != '.' && $file != '..' && $file != '.DS_Store' && ! is_dir( $file ) ) {

			$csvFile = @fopen( $server_path . '/email-exchange-other/' . $file, 'rb' );

			while ( ( $data = fgetcsv( $csvFile ) ) !== false ) {
				$thirdparty_list[] = strtolower( $data[0] );
			}
			$when = date('Y-m-d H:i:s');
			error_log("[{$when}]  end 3rd Party list" . "\n",3, $where );
		}

	}

	// look for files in the WhatCounts list directory
	$wcdir = opendir( $server_path . '/email-exchange-wc/' );
	while ( $file = readdir( $wcdir ) ) {

		if ( $file != '.' && $file != '..' && $file != '.DS_Store' && ! is_dir( $file ) ) {

			$csvFile = @fopen( $server_path . '/email-exchange-wc/' . $file, 'rb' );

			while ( ( $wcdata = fgetcsv( $csvFile ) ) !== false ) {
				$wc_list[ $wcdata[0] ] = strtolower( $wcdata[1] );
			}
			$when = date('Y-m-d H:i:s');
			error_log("[{$when}] end WhatCounts list" . "\n",3, $where );
		}

	}

	// find the matches
	$suppression_list = array_intersect( $wc_list, $thirdparty_list );

	$filedate = date('Y-m-d_H-i-s');
	$filepath        = $server_path . '/suppression-list/suppressionlist_' . $filedate . '.csv';
	foreach ( $suppression_list as $email => $md5 ) {
		$data[] = [ $email ];
	}

	/* create add to file */
	$fd = fopen( $filepath, 'w' );
	if ( $fd === false ) {
		$when = date('Y-m-d H:i:s');
		error_log("[{$when}]  Failed to open temporary file" . "\n",3, $where );
		die( 'Failed to open temporary file' );
	}

	// add the data to the csv file and save in the /private/reconciliation_reports/add/ folder
	foreach ( $data as $d ) {
		fputcsv( $fd, $d, ',' );
	}
	rewind( $fd );
	fclose( $fd );

	$when = date('Y-m-d H:i:s');
	error_log("[{$when}]  suppression file written" . "\n",3, $where );

} else {
	$conn->close();
	// debugging
	// error_log("[{$when}] don't compare" . "\n",3, $server_path . '/email-exchange/compare_tool.log' );
}

