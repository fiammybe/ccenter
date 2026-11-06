<?php
// show attachment file
// $Id: file.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

if (!function_exists('mime_content_type')) {
    function mime_content_type($f) {
	return trim(exec('file -bi '.escapeshellarg($f)));
    }
}

$msgid = isset($_GET['id'])?intval($_GET['id']):0;
$file = mod_ccenter_Attachments::safeName($_GET['file'] ?? '');
if ($file === '') die("No File");

if ($msgid) {
    $data = icms_getModuleHandler('message', 'ccenter')->getRowWithForm($msgid);
    if (!$data) die("No File");
    if (!cc_check_perm($data)) {
	redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
	exit;
    }
}

$path = mod_ccenter_Attachments::fullPath($msgid, $file);
if (!is_file($path)) die("No File");
$type = mime_content_type($path);
// only plain images are shown in the browser; everything else (html, svg, scripts...) is downloaded
$inline = preg_match('#^image/(png|jpe?g|gif|webp)\b#', $type);
header("X-Content-Type-Options: nosniff");
header("Content-Type: " . ($inline ? $type : "application/octet-stream"));

if ($_SERVER["REQUEST_METHOD"]=="GET") {
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $file) . '"');
    readfile($path);
}
