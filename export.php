<?php
// Export data in CSV format
// $Id: export.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
}

$id = isset($_GET['form']) ? (int) $_GET['form'] : 0;

$form_obj = $id ? icms_getModuleHandler('form', 'ccenter')->find($id) : null;
if (!$form_obj || !mod_ccenter_AccessPolicy::forCurrentUser()->handlesForm($form_obj->toRow())) {
    $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "index.php";
    redirect_header($back, 3, _NOPERM);
    exit;
}
$form = $form_obj->toRow();

$cond = "fidref=$id AND status<>".icms::$xoopsDB->quoteString(_STATUS_DEL);
$range = isset($_GET['range']) ? icms_core_DataFilter::stripSlashesGPC($_GET['range']) : "";
switch ($range) {
case 'm0':
    // start this month
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $stime = userTimeToServerTime(mktime(0, 0, 0, $ym[1], 1, $ym[0]));
    $cond .= " AND ctime>$stime";
    break;
case 'm1':
    // last month
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $stime = userTimeToServerTime(mktime(0, 0, 0, $ym[1]-1, 1, $ym[0]));
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $ltime = userTimeToServerTime(mktime(0, 0, 0, $ym[1], 1, $ym[0])-1);
    $cond .= " AND ctime BETWEEN $stime AND $ltime";
    break;
case 'y0':
    // start this year
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $stime = userTimeToServerTime(mktime(0, 0, 0, 1, 1, $ym[0]));
    $cond .= " AND ctime>$stime";
    break;
case 'y1':
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $stime = userTimeToServerTime(mktime(0, 0, 0, 1, 1, $ym[0]-1));
    $ym = explode('-', formatTimestamp(time(), 'Y-m'));
    $ltime = userTimeToServerTime(mktime(0, 0, 0, 1, 1, $ym[0])-1);
    $cond .= " AND ctime BETWEEN $stime AND $ltime";
    break;
default:
    $range = "all";
    break;
}

$res = icms::$xoopsDB->query('SELECT * FROM '.CCMES." WHERE $cond ORDER BY msgid");

$items = get_form_attribute($form['defs']);
$labels = array('ID', _MD_POSTDATE, _CC_STATUS, _MD_CONTACT_FROM, _MD_CONTACT_TO);
$columns = array();	// key and type of every column of the form, in the order of the definition
foreach ($items as $key => $item) {
    if (empty($item['label'])) continue;	// skip comment
    $columns[] = array('key' => $key, 'type' => $item['type']);
    $labels[] = $item['label'];
}

$contents = strip_tags(csv_str($labels))."\n";
while ($data = icms::$xoopsDB->fetchArray($res)) {
    $stored = mod_ccenter_MessageBody::fromStored($data['body'])->toLegacyArray();
    $values = array();
    foreach ($columns as $column) {
	$value = isset($stored[$column['key']]) ? $stored[$column['key']] : '';
	// an address that is not stored in the body is kept in the message itself
	if ($value === '' && $column['type'] == 'mail') $value = $data['email'];
	$values[] = $value;
    }
    $fixval = array($data['msgid'], formatTimestamp($data['mtime']),
		    $msg_status[$data['status']],
		    icms::$user->getUnameFromId($data['uid']),
		    icms::$user->getUnameFromId($data['touid']));
    $contents .= csv_str($fixval).",".csv_str($values)."\n";
}

if (function_exists("mb_convert_encoding")) {
    $charset = get_attr_value(null, 'export_charset');
    if (!$charset) $charset = _MD_EXPORT_CHARSET;
    $contents = mb_convert_encoding($contents, $charset, _CHARSET);
} else {
    $charset = _CHARSET;
}

$tm=formatTimestamp(time(), 'Ymd');
$file = "ccenter_form$id-$range-$tm.csv";

header('Content-Disposition:attachment;filename="'.$file.'"');
header("Content-type: text/csv; charset=$charset");
header("Cache-Control: public");
header("Pragma: public");
echo $contents;

exit;

function csv_str($data) {
    $vals = array();
    foreach ($data as $v) {
	$vals[] = q($v);
    }
    return join(',',$vals);
}

function q($str) {
    if (is_array($str)) {
	$str = join("\n",$str);
    }
    if (preg_match('/^-?\d*$/', $str)) return $str;
    return '"'.preg_replace('/\"/', '""', $str).'"';
}
