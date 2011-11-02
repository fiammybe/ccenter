<?php
/**
 * ccenter is a form module
 * 
 * File: list.php
 * 
 * message-listings
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

$xoopsOption['template_main'] = "ccenter_list.html";
$uid = is_object(icms::$user) ? (int) icms::$user->getVar('uid') : 0;

if (!is_object(icms::$user)) {
    redirect_header(ICMS_URL.'/user.php', 3, _NOPERM);
}

include ICMS_ROOT_PATH."/header.php";

// query from login user
if (icms::$user->isAdmin(icms::$module->getVar('mid'))) {
    if (isset($_GET['uid'])) $uid = intval($_GET['uid']);
}

$labels=array('mtime'=>_MD_POSTDATE, 'formid'=>_MD_CONTACT_FORM,
	      'touid'=>_MD_CONTACT_FROM, 'status'=>_CC_STATUS);
$orders=array('mtime'=>'ASC', 'formid'=>'ASC', 'touid'=>'ASC', 'status'=>'ASC',
	      'orders'=>array('mtime'));

$listctrl = new ListCtrl('mylist', $orders);

$cond = " AND ".$listctrl->sqlcondition();

if (isset($_GET['form'])) {
    $cond .= " AND formid=".intval($_GET['form']);
}

$sqlx = "FROM ".CCMES." m,".FORMS." WHERE uid=$uid $cond AND fidref=formid";

$res = icms::$xoopsDB->query("SELECT count(msgid) $sqlx");
list($total) = icms::$xoopsDB->fetchRow($res);
$max = icms::$module->config['max_lists'];
$start = isset($_GET['start'])?intval($_GET['start']):0;

$nav = new icms_view_PageNav($total, $max, $start, "start");
$icmsTpl->assign('pagenav', $total>$max?$nav->renderNav():"");
$icmsTpl->assign('statctrl', $listctrl->renderStat());
$icmsTpl->assign('total', $total);
$icmsTpl->assign('xoops_pagetitle', htmlspecialchars(icms::$module->getVar('name')." - "._MD_CCENTER_QUERY));
$icmsTpl->assign('labels', $listctrl->getLabels($labels));

$res = icms::$xoopsDB->query("SELECT m.*, title $sqlx ".$listctrl->sqlorder(), $max, $start);

$list = array();

while ($data = icms::$xoopsDB->fetchArray($res)) {
    $list[] = cc_message_entry($data);
}
$icmsTpl->assign('list', $list);

include ICMS_ROOT_PATH."/footer.php";