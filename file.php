<?php
/**
 * ccenter is a form module
 * 
 * File: file.php
 * 
 * index-page of ccenter module
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		0.94
 * @author		Nobuhiro Yasutomi
 * @package		ccenter
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */

include "header.php";

if (!function_exists('mime_content_type')) {
    function mime_content_type($f) {
	return trim(exec('file -bi '.escapeshellarg($f)));
    }
}

$msgid = isset($_GET['id'])?intval($_GET['id']):0;
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