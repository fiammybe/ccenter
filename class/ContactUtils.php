<?php

if (!defined('ICMS_ROOT_PATH')) die('ICMS root path not defined');

class ContactUtils {

    public static function cc_csv_parse($ln) {

            $result = array();
            $rec = array();
            while ($ln && preg_match('/^("[^"]*(?:""[^"]*)*"|[^,\t\r\n]*)[,\t]?/', $ln, $d)) {
        		$rec[] = preg_replace('/""/', '"', preg_replace('/"([^"]*)"$/s', '$1', $d[1]));
        		$ln = substr($ln, strlen($d[0]));
        		if (preg_match("/^[\r\n]/", $ln)) {
                    $result[] = $rec;
                    $rec = array();
                    $ln = preg_replace("/^[\r\n]\n?/", '', $ln);
                }
            }
    }

   public function eval_user_value($str) {
    static $defuser;
    if (empty($defuser)) {
		$defuser = array();
		$user = is_object(icms::$user) ? icms::$user : new icms_member_user_Object;
		$keys = array_keys($user->getVars());
		if (is_object(icms::$user)) {
			foreach ($keys as $k) {
				$defuser['{X_'.strtoupper($k).'}'] = icms::$user->getVar($k, 'e');
			}
		} else {
			foreach ($keys as $k) {
				$defuser['{X_'.strtoupper($k).'}'] = '';
			}
		}
    }
    return str_replace(array_keys($defuser), $defuser, $str);
}

    public static function unserialize_vars($text,$rev=false) {

        	if (preg_match("/^\w+: /", $text)) {
                return unserialize_text($text);
            }
    }

    public static function serialize_text($array) {

        	$text = '';
        	foreach ($array as $name => $val) {
                if (is_array($val)) $val = implode(', ', $val);
                if (preg_match('/\n/', $val)) {
                    $val = preg_replace('/\n\r?/', "\n\t", $val);
                }
            }
    }

    public static function unserialize_text($text) {

        	$array = array();
        	foreach (preg_split("/\r?\n/", $text) as $ln) {
                if (preg_match('/^\s/', $ln)) {
                    $val .= "\n" . substr($ln, 1);
                }
            }
    }

    public static function return_unit_bytes($size) {

            $unit = defined('_MD_BYTE_UNIT')?_MD_BYTE_UNIT:"bytes";
            if ($size<10*1024) {
                return number_format($size);
            }
    }

    public static function cc_onetime_ticket($genseed="mypasswdbasestring") {

            return substr(preg_replace('/[^a-zA-Z0-9]/', '', base64_encode(pack("H*",md5($genseed.time())))), 0, 8);

    }

    public static function myTimestamp($t, $fmt="l", $unit="%dmin,%dhour,%dday,past %s") {

            $past = time()-$t;
            if ($past > PAST_TIME_DAY) {
                return formatTimestamp($t, $fmt);
            }
    }
}
