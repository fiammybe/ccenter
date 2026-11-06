<?php
// contact to member
// $Id: index.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

$errors = array();
$op = "form";

$id= isset($_GET['form']) ? (int) $_GET['form'] : 0;

$groups = is_object(icms::$user) ? icms::$user->getGroups() : array(ICMS_GROUP_ANONYMOUS);
$form_handler = icms_getModuleHandler('form', 'ccenter');
$accessible = $form_handler->getAccessible($groups, $id);

if (count($accessible) != 1) {
    include ICMS_ROOT_PATH."/header.php";
    $xoopsOption['template_main'] = "ccenter_index.html";
    $forms = array();
    foreach ($accessible as $form_obj) {
	$form = $form_obj->toRow();
	if ($form['priuid']<0) continue; // need uid setting
	$form['title'] = htmlspecialchars($form['title']);
	$forms[] = $form;
    }
    $xoopsTpl->assign('forms', $forms);
    include ICMS_ROOT_PATH."/footer.php";
    exit;
}

if (isset($_POST['op']) && !isset($_POST['edit'])) $op = $_POST['op'];
$form_obj = reset($accessible);
$form = $form_obj->toRow();
get_attr_value($form['optvars']); // set default values
$items = get_form_attribute($form['defs']);
if ($form['priuid']< 0) {	// assign group member
    $priuid = isset($_GET['uid'])?intval($_GET['uid']):0;
    $priuser = null;
    if ($priuid) {
	$member_handler = icms::handler('icms_member');
	$priuser = $member_handler->getUser($priuid);
	if (!is_object($priuser) || !in_array(-$form['priuid'], $priuser->groups())) $priuid=0;
    }
    if (empty($priuid)) {
	$back = isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:ICMS_URL;
	redirect_header($back, 3, _NOPERM);
	exit;
    } else {
	$form['priuser'] = array('uid'=>$priuser->getVar('uid'),
				 'uname'=>$priuser->getVar('uname'),
				 'name'=>$priuser->getVar('name'));
    }
}

$errors = array();
if ($op!="form") {
    $errors = assign_post_values($items);
    if (!cc_check_token()) {
	$errors[] = cc_token_error();	// what was typed is shown again
    }
    if (count($errors)) {
	$op = 'form';
	assign_form_widgets($items);
    } elseif ($op == 'store') {
	$errors = mod_ccenter_Submission::store($items, $form);
    } elseif ($op == 'confirm') {
	assign_form_widgets($items, true);
    }
} else {
    assign_form_widgets($items);
}

$cust = $form['custom'];
$form['items'] =& $items;
$action = "index.php?form=".$form['formid'];
if (!empty($form['priuser'])) $action .= '&amp;uid='.$form['priuser']['uid'];
$form['action'] = $action;

$title = htmlspecialchars($form['title']);
//$breadcrumbs->set($title, "index.php?form=$id");


include ICMS_ROOT_PATH."/header.php";
$xoopsTpl->assign('errors', $errors);
$xoopsTpl->assign('xoops_pagetitle', $title);
//$breadcrumbs->assign();
$xoopsOption['template_main'] = render_form($form, $op);
if ($cust!=_CC_TPL_FULL) include ICMS_ROOT_PATH."/footer.php";
else echo $xoopsTpl->fetch('db:'.$xoopsOption['template_main']);
