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
	$errors = store_message($items, $form);
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

function store_message($items, $form) {
	global $icmsConfig;

    $uid = is_object(icms::$user)?icms::$user->getVar('uid'):0;
    $store = (int) $form['store'];
    if ($store==_DB_STORE_NONE) {
	$showaddr = true;	// no store to need show address
    } else {
	$showaddr = get_attr_value(null, 'notify_with_email');
    }
    $from = $email = "";
    $attach = array();
    $fields = array();	// what is stored in the message body
    $rtext = '';
    foreach ($items as $item) {
	if (empty($item['name'])) continue;
	$name = $item['name'];
	$val = $item['value'];
	$raw = $val;		// as submitted
	$opts = &$item['options'];
	$hidden = false;	// the first email address is not stored when it is not to be shown
	switch ($item['type']) {
		case 'mail':
		    if (empty($email)) { // save first email for contact
			$email = $val;
			if ($showaddr) {
			    $from = $email;
			} else {
			    $hidden = true;
			}
		    }
		    break;
		case 'file':
		    if ($val) {
			$raw = array('file' => $val);
			$attach[] = $val;
		    }
		    break;
		case 'radio':
		case 'select':
		    if (isset($opts[$val])) $val = strip_tags($opts[$val]);
		    break;
		case 'checkbox':
		    foreach ($val as $k=>$v) {
			$val[$k] = isset($opts[$v])?strip_tags($opts[$v]):$v;
		    }
			$val = join(', ', $val);
			break;
	}
	if ($hidden) continue;
	$label = strip_tags($item['label']);
	$fields[] = array(
	    'key' => $name,
	    'label' => preg_replace('/\s*\*$/', '', $label),	// without the "required" mark
	    'type' => $item['type'],
	    'value' => $raw,
	    'display' => is_array($val) ? join(', ', $val) : (string) $val,
	);
	if (!empty($val) && preg_match('/\n/', $val)) $val = "\n\t".preg_replace('/\n/', "\n\t", $val);
	$rtext .= $label.": $val\n";
    }
    $onepass = ($uid==0)?cc_onetime_ticket($email):"";
    if ($form['priuid'] < 0) {
	$touid = empty($form['priuser'])?0:$form['priuser']['uid'];
    } else {
	$touid = $form['priuid'];
    }
    $now = time();
    $parg = $onepass?"&p=".urlencode($onepass):"";

    if ($store!=_DB_STORE_NONE) {
	$message_handler = icms_getModuleHandler('message', 'ccenter');
	$message = $message_handler->create();
	$message->setValues(array(
	    'uid'=>$uid, 'touid'=>$touid,
	    'ctime'=>$now, 'mtime'=>$now, 'atime'=>$now,
	    'fidref'=>$form['formid'],
	    'email'=>$email,
	    'onepass'=>$onepass));
	if ($store==_DB_STORE_YES) {
	    $message->setBody(mod_ccenter_MessageBody::fromFields($fields));
	}
	if (!$message_handler->insert($message, true)) return array("Error in DATABASE insert");
	$id = (int) $message->getVar('msgid', 'n');
	if (empty($id)) return array("Internal Error in Store Message");
    } else {
	$id = 0;
    }
    $member_handler = icms::handler('icms_member');
    if ($touid) {
	$toUser = $member_handler->getUser($touid);
	$toUname = $toUser->getVar('uname');
    } else {
	$toUser = false;
	$toUname = _MD_CONTACT_NOTYET;
    }
    $atext = "";		// reply sender
    $btext = "";		// to contact and monitors
    if (count($attach)) {
	$atext = $btext = "\n"._MD_ATTACHMENT."\n";
	foreach ($attach as $i=>$file) {
	    move_attach_file('', $file, $id);
	    $a = cc_attach_image($id, $file, true);
	    $atext .= "$a$parg\n";
	    $btext .= "$a\n";
	}
	rmdir(ICMS_UPLOAD_PATH.cc_attach_path(0, ''));
    }
    $dirname = basename(dirname(__FILE__));
    $uname = icms::$user?icms::$user->getVar('uname'):$icmsConfig['anonymous'];
    $tags = array('SUBJECT'=>$form['title'],
		  'TO_USER'=>$toUname,
		  'FROM_USER'=>$uname,
		  'FROM_EMAIL'=>$email,
		  'REMOTE_ADDR'=>$_SERVER["REMOTE_ADDR"],
		  'HTTP_USER_AGENT'=>$_SERVER["HTTP_USER_AGENT"],
		  'MSGID'=>$id);
    $tpl = get_attr_value(null, 'from_confirm_tpl', 'form_confirm.tpl');
    $msgurl = ICMS_URL.($id?"/modules/$dirname/message.php?id=$id":'/');
    if ($email) {		// reply automaticaly
		$tags['VALUES'] = "$rtext$atext";
		$tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n$msgurl$parg";
		cc_notify_mail($tpl, $tags, $email, $toUser?$toUser->getVar('email'):'');
    }
    $tags['VALUES'] = "$rtext$btext";
    $tags['MSG_URL'] = ($store==_DB_STORE_NONE)?'':"\n"._MD_NOTIFY_URL."\n".$msgurl;

    $notification_handler = icms::handler('icms_data_notification');
    $notification_handler->triggerEvent('global', 0, 'new', $tags);
    $notification_handler->triggerEvent('form', $form['formid'], 'new', $tags);
    // force subscribe sender and recipient
    if ($id) $notification_handler->subscribe('message', $id, 'comment');
    if ($touid) {
		if ($id) $notification_handler->subscribe('message', $id, 'comment', null, null, $touid);
		cc_notify_mail(get_attr_value(null, 'charge_notify_tpl', $tpl), $tags, $toUser, $from);
	} elseif ($form['cgroup']) { // contact group notify
			$users = $member_handler->getUsersByGroup($form['cgroup'], true);
			cc_notify_mail(get_attr_value(null, 'group_notify_tpl', $tpl), $tags, $users, $from);
    }

    if ($id) $msgurl .= $parg;
    $redirect = get_attr_value(null, 'redirect');
    if (!empty($redirect)) {
		$msgurl = preg_match('/^\\//', $redirect)?ICMS_URL.$redirect:$redirect;
    }
    redirect_header($msgurl, 3, _MD_CONTACT_DONE);
    exit;
}