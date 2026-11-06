<?php
// Display contact form in block
// $Id$

global $icmsConfig;

$moddir = dirname(__FILE__, 2);
$lang = $icmsConfig['language'];
$main = "$moddir/language/$lang/main.php";
if (!file_exists($main)) $main = "$moddir/language/english/main.php";
include_once $main;
include_once "$moddir/functions.php";

function b_ccenter_form_show($options) {
    global $xoopsTpl;
    // the same rule as on the form page: only forms the visitor's groups may use
    $groups = is_object(icms::$user) ? icms::$user->getGroups() : array(ICMS_GROUP_ANONYMOUS);
    $forms = icms_getModuleHandler('form', 'ccenter')->getAccessible($groups, empty($options[0]) ? 0 : (int) $options[0]);
    if (!$forms) return array();
    $form = reset($forms)->toRow();
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
    foreach (icms_getModuleHandler('form', 'ccenter')->activeChoices() as $id => $title) {
	$ck = ($id==$oid)?" selected='selected'":"";
	$ln .= "<option value='$id'$ck>".htmlspecialchars($title)."</option>";
    }
    $ln .= "</select>\n";
    return $ln;
}