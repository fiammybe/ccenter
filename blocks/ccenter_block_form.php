<?php
// Display contact form in block
// $Id: ccenter_block_form.php,v 1.5 2009-07-04 05:24:38 nobu Exp $

global $icmsConfig;

$moddir = dirname(dirname(__FILE__));
$lang = $icmsConfig['language'];
$main = "$moddir/language/$lang/main.php";
if (!file_exists($main)) $main = "$moddir/language/english/main.php";
include_once $main;
include_once "$moddir/include/functions.php";

function b_ccenter_form_show($options) {
    global $icmsTpl;
    $cond = "active";
    if (is_object(icms::$user)) {
	$conds = array();
	foreach (icms::$user->getGroups() as $gid) {
	    $conds[] = "grpperm LIKE '%|$gid|%'";
	}
	if ($conds) $cond .= " AND (".join(' OR ', $conds).")";
    } else {
	$cond .= " AND grpperm LIKE '%|".ICMS_GROUP_ANONYMOUS."|%'";
    }
    if (!empty($options[0])) $cond .= ' AND formid='.(int) $options[0];
    $res = icms::$xoopsDB->query("SELECT * FROM ".FORMS." WHERE $cond ORDER BY weight,formid");
    if (!$res || icms::$xoopsDB->getRowsNum($res)==0) return array();
    $form = icms::$xoopsDB->fetchArray($res);
    $items = get_form_attribute($form['defs']);
    assign_form_widgets($items);
    $form['items'] =& $items;
    $form['action'] = ICMS_URL . '/modules/ccenter/index.php?form='.$form['formid'];
    $template = render_form($form, 'form');
    return array('content'=>$xoopsTpl->fetch('db:'.$template));
}

function b_ccenter_form_edit($options) {
    global $icmsConfig, $msg_status;
    $oid = (int) $options[0];
    $ln = "<div><b>"._BL_CCENTER_FORMS_ID."</b> ".
	"<select name='options[0]'>\n<option value='0'>".
	_BL_CCENTER_FORMS_FIRST."</option>\n";
    $res = icms::$xoopsDB->query("SELECT formid,title FROM ".FORMS." WHERE active ORDER BY weight,formid");
    while (list($id, $title)=icms::$xoopsDB->fetchRow($res)) {
	$ck = ($id==$oid)?" selected='selected'":"";
	$ln .= "<option value='$id'$ck>".htmlspecialchars($title)."</option>";
    }
    $ln .= "</select>\n";
    return $ln;
}