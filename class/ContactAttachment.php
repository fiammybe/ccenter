<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactAttachment {

    public function move_attach_file($tmp, $file, $id=0) {

            global $icmsConfig;

            $path = ICMS_UPLOAD_PATH.cc_attach_path($id, $file);
            $dir = dirname($path);
            $base = dirname($dir);
            if (!is_dir($base)) {
        	if (!mkdir($base) && !is_dir($base)) die("UPLOADS permittion error");
        	$fp = fopen("$base/.htaccess", "w");
        	fwrite($fp, "deny from all\n");	// not access direct
        	fclose($fp);}

    }

    public function cc_attach_path($id, $file) {

            $dirname = basename(__DIR__);
            $dir = $id?sprintf("%05d", $id):"work".substr(session_id(), 0, 8);
            return "/$dirname/$dir".($file?"/$file":"");

    }

    public function cc_attach_image($id, $file, $urlonly=false, $add='') {

            if (empty($file)) return "";
            $rurl = "file.php?".($id?"id=$id&":"")."file=".urlencode($file).($add?"&$add":"");
            if ($urlonly) return ICMS_URL."/modules/".basename(__DIR__)."/$rurl";
            $path = ICMS_UPLOAD_PATH.cc_attach_path($id, $file);
            $xy = getimagesize($path);
            if ($xy) {
                if ($xy[0] > $xy[1] && $xy[0] > 300) {
                    $extra = " width='300'";
                }
            }
    }
}
