<?php
/**
 * Created by PhpStorm.
 * User: balbert
 * Date: 1/13/16
 * Time: 2:28 PM
 */

/* various helper functions for THINK framework and Web Service */

function mq_get_order_by_orderhdr( $orderhdr_id, $subscrip_id ){

	global $wpdb;

	$order_q = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mequoda_orders WHERE think_orderhdr_id=%d AND think_subscrip_id=%d", $orderhdr_id, $subscrip_id );
	$order = $wpdb->get_row( $order_q );

	return $order;

}

function mq_get_products_for_think( $think_order_code, $think_subscription_def_id ){

	global $wpdb;

	$sql = $wpdb->prepare( "SELECT * FROM wp_mequoda_offers off WHERE external_id = %s", $think_subscription_def_id );

	$result = $wpdb->get_row( $sql );

	return $result;


}

function mq_get_user_from_think_customer_id( $think_customer_id ){

	global $wpdb;

	$sql = $wpdb->prepare( "SELECT user_id FROM wp_usermeta WHERE meta_key = 'think_customer_id' AND meta_value = %s ", $think_customer_id );

	$user_id = $wpdb->get_col( $sql );

	return $user_id;

}

function mq_country_to_think_state( $country ){

	$haven_to_think = array(
		"AF" => "AFG",
		"AX" => "ALA",
		"AL" => "ALB",
		"DZ" => "DZA",
		"AS" => "AS",
		"AD" => "AND",
		"AO" => "AGO",
		"AI" => "AIA",
		"AQ" => "ATA",
		"AG" => "ATG",
		"AR" => "ARG",
		"AM" => "ARM",
		"AW" => "ABW",
		"AU" => "AUS",
		"AT" => "AUT",
		"AZ" => "AZE",
		"BS" => "BHS",
		"BH" => "BHR",
		"BD" => "BGD",
		"BB" => "BRB",
		"BY" => "BLR",
		"BE" => "BEL",
		"BZ" => "BLZ",
		"BJ" => "BEN",
		"BM" => "BMU",
		"BT" => "BTN",
		"BO" => "BOL",
		"BA" => "BIH",
		"BW" => "BWA",
		"BV" => "BVT",
		"BR" => "BRA",
		"IO" => "IOT",
		"BN" => "BRN",
		"BG" => "BGR",
		"BF" => "BFA",
		"BI" => "BDI",
		"KH" => "KHM",
		"CM" => "CMR",
		"CV" => "CPV",
		"KY" => "CYM",
		"CF" => "CAF",
		"TD" => "TCD",
		"CL" => "CHL",
		"CN" => "CHN",
		"CX" => "CXR",
		"CC" => "CCK",
		"CO" => "COL",
		"KM" => "COM",
		"CD" => "COD",
		"CG" => "COG",
		"CK" => "COK",
		"CR" => "CRI",
		"CI" => "CIV",
		"HR" => "HRV",
		"CU" => "CUB",
		"CY" => "CYP",
		"CZ" => "CZE",
		"DK" => "DNK",
		"DJ" => "DJI",
		"DM" => "DMA",
		"DO" => "DOM",
		"EC" => "ECU",
		"EG" => "EGY",
		"SV" => "SLV",
		"GQ" => "GNQ",
		"ER" => "ERI",
		"EE" => "EST",
		"ET" => "ETH",
		"FK" => "FLK",
		"FO" => "FRO",
		"FM" => "FM",
		"FJ" => "FJI",
		"FI" => "FIN",
		"FR" => "FRA",
		"GF" => "GUF",
		"PF" => "PYF",
		"TF" => "ATF",
		"GA" => "GAB",
		"GM" => "GMB",
		"GE" => "GEO",
		"DE" => "DEU",
		"GH" => "GHA",
		"GI" => "GIB",
		"GR" => "GRC",
		"GL" => "GRL",
		"GD" => "GRD",
		"GP" => "GLP",
		"GU" => "GU",
		"GT" => "GTM",
		"GN" => "GIN",
		"GW" => "GNB",
		"GY" => "GUY",
		"HT" => "HTI",
		"HM" => "HMD",
		"HN" => "HND",
		"HK" => "HKG",
		"HU" => "HUN",
		"IS" => "ISL",
		"IN" => "IND",
		"ID" => "IDN",
		"IR" => "IRN",
		"IQ" => "IRQ",
		"IE" => "IRL",
		"IL" => "ISR",
		"IT" => "ITA",
		"JM" => "JAM",
		"JP" => "JPN",
		"JO" => "JOR",
		"KZ" => "KAZ",
		"KE" => "KEN",
		"KI" => "KIR",
		"KP" => "PRK",
		"KR" => "KOR",
		"KW" => "KWT",
		"KG" => "KGZ",
		"LA" => "LAO",
		"LV" => "LVA",
		"LB" => "LBN",
		"LS" => "LSO",
		"LR" => "LBR",
		"LY" => "LBY",
		"LI" => "LIE",
		"LT" => "LTU",
		"LU" => "LUX",
		"MO" => "MAC",
		"MK" => "MKD",
		"MG" => "MDG",
		"MW" => "MWI",
		"MY" => "MYS",
		"MV" => "MDV",
		"ML" => "MLI",
		"MT" => "MLT",
		"MH" => "MH",
		"MQ" => "MTQ",
		"MR" => "MRT",
		"MU" => "MUS",
		"YT" => "MYT",
		"MX" => "MEX",
		"MD" => "MDA",
		"MC" => "MCO",
		"MN" => "MNG",
		"ME" => "MNE",
		"MS" => "MSR",
		"MA" => "MAR",
		"MZ" => "MOZ",
		"MM" => "MMR",
		"NA" => "NAM",
		"NR" => "NRU",
		"NP" => "NPL",
		"NL" => "NLD",
		"AN" => "ANT",
		"NC" => "NCL",
		"NZ" => "NZL",
		"NI" => "NIC",
		"NE" => "NER",
		"NG" => "NGA",
		"NU" => "NIU",
		"NF" => "NFK",
		"MP" => "MP",
		"NO" => "NOR",
		"OM" => "OMN",
		"PK" => "PAK",
		"PW" => "PW",
		"PS" => "PSE",
		"PA" => "PAN",
		"PG" => "PNG",
		"PY" => "PRY",
		"PE" => "PER",
		"PH" => "PHL",
		"PN" => "PCN",
		"PL" => "POL",
		"PT" => "PRT",
		"PR" => "PR",
		"QA" => "QAT",
		"RE" => "REU",
		"RO" => "ROU",
		"RU" => "RUS",
		"RW" => "RWA",
		"KN" => "KNA",
		"LC" => "LCA",
		"VC" => "VCT",
		"SM" => "SMR",
		"ST" => "STP",
		"SA" => "SAU",
		"SN" => "SEN",
		"RS" => "SRB",
		"SC" => "SYC",
		"SL" => "SLE",
		"SG" => "SGP",
		"SK" => "SVK",
		"SI" => "SVN",
		"SB" => "SLB",
		"SO" => "SOM",
		"ZA" => "ZAF",
		"GS" => "SGS",
		"ES" => "ESP",
		"LK" => "LKA",
		"SH" => "SHN",
		"PM" => "SPM",
		"SD" => "SDN",
		"SR" => "SUR",
		"SJ" => "SJM",
		"SZ" => "SWZ",
		"SE" => "SWE",
		"CH" => "CHE",
		"SY" => "SYR",
		"TW" => "TWN",
		"TJ" => "TJK",
		"TZ" => "TZA",
		"TH" => "THA",
		"TL" => "TLS",
		"TG" => "TGO",
		"TK" => "TKL",
		"TO" => "TON",
		"TT" => "TTO",
		"TN" => "TUN",
		"TR" => "TUR",
		"TM" => "TKM",
		"TC" => "TCA",
		"TV" => "TUV",
		"UG" => "UGA",
		"UA" => "UKR",
		"AE" => "ARE",
		"GB" => "GBR",
		"UM" => "UMI",
		"UY" => "URY",
		"VI" => "VI",
		"UZ" => "UZB",
		"VU" => "VUT",
		"VA" => "VAT",
		"VE" => "VEN",
		"VN" => "VNM",
		"VG" => "VGB",
		"WF" => "WLF",
		"EH" => "ESH",
		"WS" => "WSM",
		"YE" => "YEM",
		"ZM" => "ZMB",
		"ZW" => "ZWE"
	);

	$country = strtoupper( $country );
	$think_country_code = $haven_to_think[$country];

	return $think_country_code;
}



