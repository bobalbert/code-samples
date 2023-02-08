<?php

// DEV server
$servername = "thedbserverurl";
// DEVLOCAL
//$servername = "127.0.0.1";

// not the real data for code samples
$username = "thedbusername";
$password = "thedbpassword";
$dbname = "thedebname";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ( $conn->connect_error ) {
	die( "Connection failed: " . $conn->connect_error );
}

if( isset( $_POST['queue'] ) ){
    $conn->query( "UPDATE settings SET value = 'true' WHERE name = 'compare';" );
	$compare = true;
} else {
	$sql = "SELECT * FROM settings";
	$result = $conn->query($sql);

	$compare = false;

	if ( $result->num_rows > 0 ) {
		// output data of each row
		while( $row = $result->fetch_assoc() ) {
			if( 'compare' == $row['name'] && 'true' == $row['value'] ){
				$compare = true;
			}
		}
	}
}

$conn->close();

?>
<!DOCTYPE html>
<html>
<head>
	<title>Email Exchange Tool</title>
	<meta name="generator" content="BBEdit 14.6" />
</head>
<body>
    <div style="margin-left: 20px;">
	<h2>Email Exchange Tool</h2>
        <?php if( 'true' == $compare ){
            echo "<p>Process files is set to begin ~5 minutes.<br/>It can take serveral minutes to process the data given the size of the files.<br />Check the <strong>suppression-list</strong> FTP directory in ~15 minutes.</p>";
        } else {
            echo "<p>Before processing the files, make sure you have uploaded them to the correct folders.</p>";
	        echo "<p>Place the WhatCounts export file in the <strong>email-exchange-wc</strong><br />Place the 3rd Party export file in the <strong>email-exchange-other</folder></strong></p>";
            echo "<p>When ready, click the button below to queue script to run and proccess files.</p>";

            echo "<p><form action='{$_SERVER['REQUEST_URI']}' method='post'>";
            echo "<input type='hidden' name='queue' value='true' />";
            echo "<form><input type='submit' value='Queue to process?' /></form></p>";
        }
        ?>
    </div>
</body>
</html>
