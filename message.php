<?php
/**
 * ccenter is a form module
 * 
 * File: message.php
 * 
 * display messages
 * 
 * @copyright	Copyright QM-B (Steffen Flohrer) 2011
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		0.94
 * @author		Nobuhiro Yasutomi
 * @package		ccenter
 * --------------------------------------------------------------------------------------------------------
 * 				ccenter
 * @since		1.00
 * @author		QM-B
 * @package		ccenter
 * @version		$Id$
 * 
 */

include "header.php";

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
    icms::$xoopsDB->queryF("UPDATE ".CCMES." SET atime=$now WHERE msgid=$msgid");
}

include ICMS_ROOT_PATH."/header.php";

$breadcrumbs = new CcenterBreadcrumbs(_MD_CCENTER_RECEPTION, 'reception.php');

$pass = isset($_GET['p'])?$_GET['p']:'';
$add = $pass?"p=".urlencode($pass):"";
$to_uname = icms_member_user_Object::getUnameFromId($data['touid']);
$res = icms::$xoopsDB->query("SELECT * FROM ".FORMS." WHERE formid=".$data['fidref']);
$form = icms::$xoopsDB->fetchArray($res);
$items = get_form_attribute($form['defs']);
$values = cc_display_values(unserialize_text($data['body']), $items, $data['msgid'], $add);
$data['comment'] = icms_core_DataFilter::checkVar($data['comment'], 'text', 'input');
$isadmin = $uid && icms::$user->isAdmin(icms::$module->getVar('mid'));
$title = $data['title'];
list($lab) = array_keys($values);
if ($isadmin) {
    $breadcrumbs->set($title, "reception.php?form=".$data['fidref']);
} else {
    $breadcrumbs->set($title, "form.php?form=".$data['fidref']);
}
$breadcrumbs->set($lab.': '.$values[$lab], '');
$breadcrumbs->assign();
$has_mail = !empty($data['email']);
$icmsTpl->assign(
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
	  'icms_pagetitle'=> htmlspecialchars(icms::$module->getVar('name')." | ".$data['title']),
	  'has_mail'=>$has_mail,
	));


include ICMS_ROOT_PATH.'/include/comment_view.php';

include ICMS_ROOT_PATH."/footer.php";