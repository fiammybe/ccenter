<?php
// show message list
// $Id$

include "../../mainfile.php";
include "functions.php";
include_once ICMS_ROOT_PATH.'/class/pagenav.php';

$xoopsOption['template_main'] = "ccenter_charge.html";
$uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0;

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
}

include ICMS_ROOT_PATH."/header.php";

// query from login user
if (icms::$user->isAdmin($icmsModule->getVar('mid'))) {
    if (isset($_GET['touid'])) {
		$uid = (int) $_GET['touid'];
	}
}

$labels=array('mtime'=>_MD_MODDATE, 'formid'=>_MD_CONTACT_FORM,
	      'uid'=>_MD_CONTACT_FROM, 'status'=>_CC_STATUS);
$orders=array('mtime'=>'ASC', 'formid'=>'ASC', 'uid'=>'ASC', 'status'=>'ASC',
	      'stat'=>'- a', 'orders'=>array('status','mtime'));

$listctrl = new ListCtrl('charge', $orders);

$cond = " AND ".$listctrl->sqlcondition();

if (isset($_GET['form'])) {
    $cond .= " AND formid=".(int) $_GET['form'];
}

$sqlx = "FROM ".CCMES." m,".FORMS." WHERE touid=$uid $cond AND fidref = formid";

$res = icms::$xoopsDB->query("SELECT count(msgid) $sqlx");
list($total) = icms::$xoopsDB->fetchRow($res);

$max = icms::$module->config['max_lists'];
$start = isset($_GET['start']) ? (int) $_GET['start'] : 0;

$nav = new icms_view_PageNav($total, $max, $start, "start");
$xoopsTpl->assign('pagenav', $nav->renderNav());
$xoopsTpl->assign('statctrl', $listctrl->renderStat());
$xoopsTpl->assign('total', $total);
$xoopsTpl->assign('xoops_pagetitle', htmlspecialchars(icms::$module->getVar('name')." - "._MD_CCENTER_CHARGE));
$xoopsTpl->assign('labels', $listctrl->getLabels($labels));

$res = icms::$xoopsDB->query("SELECT m.*, title $sqlx ".$listctrl->sqlorder(), $max, $start);

$qlist = array();
while ($data = icms::$xoopsDB->fetchArray($res)) {
    $qlist[] = cc_message_entry($data);
}
$xoopsTpl->assign('qlist', $qlist);

include ICMS_ROOT_PATH."/footer.php";