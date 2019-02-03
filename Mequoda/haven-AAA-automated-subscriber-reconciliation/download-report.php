<?php
/**
 * Created by PhpStorm.
 * User: balbert
 * Date: 6/22/18
 * Time: 2:14 PM
 */

$filename = $_REQUEST['report'];
$type = $_REQUEST['type'];

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once($root . '/wp-load.php');

$reports_path = str_replace(  '/products/', '/reconciliation_reports',DOWNLOAD_PATH);
$pathtofile = $reports_path . '/' . $type . '/' . $filename;

header('Content-Description: File Transfer');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=' . $filename);
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($pathtofile));
readfile($pathtofile);
exit;