<?php
// Person receipt blocks
// $Id$

global $icmsConfig;

$moddir = dirname(__FILE__, 2);
$lang = $icmsConfig['language'];
$main = "$moddir/language/$lang/main.php";
if (!file_exists($main)) $main = "$moddir/language/english/main.php";
include_once $main;
include_once "$moddir/functions.php";

function b_ccenter_receipt_show($options) {
    global $msg_status;
    if (!is_object(icms::$user)) return null;
    $uid = (int) icms::$user->getVar('uid');
    $max = array_shift($options);
    $order = array_shift($options) == 'asc' ? 'asc' : 'desc';
    $list = array();
    $labels = mod_ccenter_Status::labels();
    foreach (icms_getModuleHandler('message', 'ccenter')->receiptFor($uid, $options, $order, (int) $max) as $data) {
	$data['mdate'] = formatTimestamp($data['mtime'], _BL_CCENTER_DATE_FMT);
	$data['uname'] = icms::$user->getUnameFromId($data['uid']);
	$data['statstr'] = $labels[$data['status']] ?? '';
	$list[] = $data;
    }
    if (!$list) return null;
    $mydir = basename(dirname(__FILE__, 2));
    return array('list'=>$list, 'dirname'=>$mydir);
}

function b_ccenter_receipt_edit($options) {
    global $icmsConfig, $msg_status;
    $max = array_shift($options);
    $order = array_shift($options);
    $ln = "<div><b>"._BL_CCENTER_OPT_LINES."</b> <input name='options[0]' value='$max' size='4'/></div>\n";
    $ln .= "<div><b>"._BL_CCENTER_OPT_SORT."</b> <select name='options[1]'>\n";
    foreach (array('asc'=>_BL_CCENTER_SORT_ASC, 'desc'=>_BL_CCENTER_SORT_DESC) as $k=>$v) {
	$ck = ($k==$order)?" selected='selected'":"";
	$ln .= "<option value='$k'$ck>$v</option>";
    }
    $ln .= "</select></div>\n";
    $ln .= "<div><b>"._BL_CCENTER_OPT_STATS."</b>";
    foreach ($msg_status as $k=>$v) {
	$ck = in_array($k, $options)?" checked='checked'":"";
	$ln .= " <span class='cc_bopt'><input type='checkbox' name='options[]' value='$k'$ck/> $v<span>\n";
    }
    $ln .= "</div>\n";
    return $ln;
}