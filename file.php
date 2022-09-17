<?php
// show attachment file
// $Id$

include "../../mainfile.php";
include "functions.php";

if (!function_exists('mime_content_type')) {
    function mime_content_type($f) {
	return trim(exec('file -bi '.escapeshellarg($f)));
    }
}

$msgid = isset($_GET['id'])? (int)$_GET['id'] :0;
$file = $_GET['file'];

if ($msgid) {
    $res = icms::$xoopsDB->query("SELECT msgid,uid,touid,onepass FROM ".CCMES." WHERE msgid=$msgid");
    if (!$res || icms::$xoopsDB->getRowsNum($res)==0) die("No File");
    $data = icms::$xoopsDB->fetchArray($res);
    if (!cc_check_perm($data)) {
	redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
	exit;
    }
}

$path = ICMS_UPLOAD_PATH.cc_attach_path($msgid, $file);
$type = mime_content_type($path);
$stat = stat($file);
//header("Last-Modified: ".formatTimestamp($stat['mtime'], "r"));
header("Content-Type: $type");
//header("Content-Length: ".$stat['size']);

if ($_SERVER["REQUEST_METHOD"]=="GET") {
    header('Content-Disposition: inline;filename="'.$file.'"');
    print file_get_contents($path);
}