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
    $uid = icms::$user->getVar('uid');
    $max = array_shift($options);
    $order = array_shift($options);
    foreach ($options as $v) {
	$s[] = "'$v'";
    }
    $cond =  " AND status IN (".implode(',', $s).")";
    $order = $order=='asc'?'asc':'desc';
    $res = icms::$xoopsDB->query("SELECT msgid, m.mtime, uid, status, title
  FROM ".icms::$xoopsDB->prefix('ccenter_message')." m,
    ".icms::$xoopsDB->prefix('ccenter_form')." WHERE (priuid=$uid OR priuid=0) $cond
   AND fidref=formid ORDER BY status,m.mtime $order", $max);
    if (!$res || icms::$xoopsDB->getRowsNum($res)==0) return null;
    $list = array();
    while ($data = icms::$xoopsDB->fetchArray($res)) {
	$data['mdate'] = formatTimestamp($data['mtime'], _BL_CCENTER_DATE_FMT);
	$data['uname'] = icms::$user->getUnameFromId($data['uid']);
	$data['statstr'] = $msg_status[$data['status']];
	$list[] = $data;
    }
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
