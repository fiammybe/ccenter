<?php
// show messages file
// $Id: message.php 1058 2013-08-25 09:15:13Z st.flohrer@gmail.com $

include "../../mainfile.php";
include "functions.php";

$xoopsOption['template_main'] = "ccenter_message.html";
$uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0;

$msgid = (int) $_GET['id'];

$data = cc_get_message($msgid);

// change to accept status when change user access
if ($uid && $uid == $data['touid'] && $data['status']==_STATUS_NONE) {
    change_message_status($msgid, $uid, _STATUS_ACCEPT);
    $data['status'] = _STATUS_ACCEPT;
}

// recording contactee access time
$now = time();
if ($uid == $data['uid'] && $now>$data['atime']) {
    icms_getModuleHandler('message', 'ccenter')->updateFields($msgid, array('atime' => $now));
}

include ICMS_ROOT_PATH."/header.php";

$breadcrumbs = new CcBreadcrumbs();
$breadcrumbs->set(_MD_CCENTER_RECEPTION, 'reception.php');

$pass = isset($_GET['p'])?$_GET['p']:'';
$add = $pass?"p=".urlencode($pass):"";
$to_uname = icms_member_user_Object::getUnameFromId($data['touid']);
$form = icms_getModuleHandler('form', 'ccenter')->find($data['fidref'])->toRow();
$items = get_form_attribute($form['defs']);
$body = mod_ccenter_MessageBody::fromStored($data['body']);
$values = cc_display_values($body->toLegacyArray(), $items, $data['msgid'], $add, $body->displayLabels());
$data['comment'] = icms_core_DataFilter::checkVar($data['comment'], 'text', 'input');
$isadmin = $uid && icms::$user->isAdmin(icms::$module->getVar('mid'));
$title = $data['title'];
if ($isadmin) {
    $breadcrumbs->set($title, "reception.php?form=".$data['fidref']);
} else {
    $breadcrumbs->set($title, "index.php?form=".$data['fidref']);
}
if ($values) {	// a message of a form that keeps nothing has no values
    $lab = array_key_first($values);
    $breadcrumbs->set($lab.': '.$values[$lab], '');
}
$breadcrumbs->assign();
$has_mail = !empty($data['email']);
$xoopsTpl->assign('token', icms::$security->getTokenHTML());
$xoopsTpl->assign(
    array('subject'=>$title,
	  'sender'=>icms_member_user_Handler::getUserLink($data['uid']),
	  'sendto'=>$data['touid']?icms_member_user_Handler::getUserLink($data['touid']):_MD_CONTACT_NOTYET,
	  'cdate'=>formatTimestamp($data['ctime']),
	  'mdate'=>myTimestamp($data['mtime'], 'l', _MD_TIME_UNIT),
	  'adate'=>myTimestamp($data['atime'], 'l', _MD_TIME_UNIT),
	  'readit'=>($data['atime']>=$data['mtime']),
	  'data'=> $data,
	  'items'=>$values,
	  'status'=>$msg_status[$data['status']],
	  'is_eval'=>is_cc_evaluate($msgid, $uid, $pass),
	  'is_mine'=>$data['touid']==$uid,
	  'is_getmine'=>$data['touid']==0 && $uid && in_array($data['cgroup'], icms::$user->getGroups()),
	  'own_status'=>array_slice($msg_status, 1, $isadmin?4:3),
	  'xoops_pagetitle'=> htmlspecialchars(icms::$module->getVar('name')." | ".$data['title']),
	  'has_mail'=>$has_mail,
	));


include ICMS_ROOT_PATH.'/include/comment_view.php';

include ICMS_ROOT_PATH."/footer.php";
?>